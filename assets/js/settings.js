/* global IVY_ST, jQuery */
/**
 * 설정 페이지 인터랙션 (단일 파일).
 *  - API 연결 탭: 연결 테스트 버튼
 *  - 사용자 매핑 탭: 검색 / 추가 / 해지 (admin-ajax 기반, 서버 응답으로 목록 전체 재렌더링)
 */
(function ($) {
	'use strict';

	if (typeof IVY_ST === 'undefined') {
		// localize_script가 같은 핸들에 prepended되어 있어야 한다 — 이 메시지가 보이면 캐시·로딩 순서 이슈.
		// eslint-disable-next-line no-console
		console.error('[ivy-support-ticket] IVY_ST 글로벌이 정의되지 않았습니다.');
		return;
	}

	$(function () {
		bindConnectionTest();
		bindUserMapping();
	});

	// ────────────────────────────────────────────────
	// 연결 테스트 (API 연결 탭)
	// ────────────────────────────────────────────────
	function bindConnectionTest() {
		var $btn    = $('#ivy-st-test-connection');
		var $result = $('#ivy-st-test-result');
		if (!$btn.length) {
			return;
		}

		$btn.on('click', function () {
			$result
				.removeClass('is-success is-error')
				.addClass('is-pending')
				.text(IVY_ST.i18n.testing);

			$.post(IVY_ST.ajaxUrl, {
				action:   'ivy_st_test_connection',
				_wpnonce: IVY_ST.nonce
			})
				.done(function (resp) {
					$result.removeClass('is-pending');
					if (resp && resp.success) {
						var orgName = resp.data && resp.data.organization && resp.data.organization.name
							? resp.data.organization.name
							: '';
						var label = IVY_ST.i18n.success + (orgName ? ' (' + orgName + ')' : '');
						$result.addClass('is-success').text(label);
					} else {
						var msg = resp && resp.data && resp.data.message
							? resp.data.message
							: IVY_ST.i18n.failed;
						$result.addClass('is-error').text(IVY_ST.i18n.failed + ': ' + msg);
					}
				})
				.fail(function (xhr) {
					$result
						.removeClass('is-pending')
						.addClass('is-error')
						.text(IVY_ST.i18n.failed + ' (HTTP ' + (xhr && xhr.status ? xhr.status : '0') + ')');
				});
		});
	}

	// ────────────────────────────────────────────────
	// 사용자 매핑 — 듀얼 리스트 UI (사용자 매핑 탭)
	// ────────────────────────────────────────────────
	function bindUserMapping() {
		var $leftList    = $('#ivy-st-left-list');
		var $rightList   = $('#ivy-st-right-list');
		var $leftSearch  = $('#ivy-st-left-search');
		var $rightSearch = $('#ivy-st-right-search');
		var $moveRight   = $('#ivy-st-move-right');
		var $moveLeft    = $('#ivy-st-move-left');
		var $pageInfo    = $('#ivy-st-page-info-text');
		var $prevBtn     = $('#ivy-st-left-prev');
		var $nextBtn     = $('#ivy-st-left-next');

		if (!$leftList.length) {
			return;
		}

		var state       = { page: 1, pages: 1, total: 0, q: '', loading: false };
		var searchTimer = null;

		fetchLeft(1, '');

		// 왼쪽 검색 (디바운스 300ms)
		$leftSearch.on('input', function () {
			clearTimeout(searchTimer);
			var q = $.trim($(this).val());
			searchTimer = setTimeout(function () {
				state.q = q;
				fetchLeft(1, state.q);
			}, 300);
		});

		// 페이지네이션
		$prevBtn.on('click', function () {
			if (state.page > 1) fetchLeft(state.page - 1, state.q);
		});
		$nextBtn.on('click', function () {
			if (state.page < state.pages) fetchLeft(state.page + 1, state.q);
		});

		// 항목 선택 토글
		$leftList.on('click', '.ivy-st-dual-item', function () {
			$(this).toggleClass('is-selected');
		});
		$rightList.on('click', '.ivy-st-dual-item', function () {
			$(this).toggleClass('is-selected');
		});

		// 오른쪽 패널 클라이언트 검색
		$rightSearch.on('input', function () {
			var q = $.trim($(this).val()).toLowerCase();
			$rightList.find('.ivy-st-dual-item').each(function () {
				var label = ($(this).attr('data-label') || '').toLowerCase();
				$(this).toggle(q === '' || label.indexOf(q) !== -1);
			});
		});

		// → 등록
		$moveRight.on('click', function () {
			var ids = getSelectedIds($leftList);
			if (!ids.length) return;
			setArrowsDisabled(true);
			processUsers(ids, 'user_add', function (enrolled) {
				if (enrolled) renderRight(enrolled);
				fetchLeft(state.page, state.q);
				setArrowsDisabled(false);
			});
		});

		// ← 해지
		$moveLeft.on('click', function () {
			var ids = getSelectedIds($rightList);
			if (!ids.length) return;
			setArrowsDisabled(true);
			processUsers(ids, 'user_remove', function (enrolled) {
				if (enrolled) renderRight(enrolled);
				fetchLeft(state.page, state.q);
				setArrowsDisabled(false);
			});
		});

		// ── 헬퍼 ──

		function getSelectedIds($list) {
			var ids = [];
			$list.find('.ivy-st-dual-item.is-selected').each(function () {
				var id = parseInt($(this).attr('data-user-id'), 10);
				if (id) ids.push(id);
			});
			return ids;
		}

		function setArrowsDisabled(disabled) {
			$moveRight.prop('disabled', disabled);
			$moveLeft.prop('disabled', disabled);
		}

		/** AJAX add/remove를 순차 실행하고 마지막 enrolled 목록을 콜백으로 전달. */
		function processUsers(ids, action, callback) {
			var lastEnrolled = null;
			function next(i) {
				if (i >= ids.length) {
					callback(lastEnrolled);
					return;
				}
				$.post(IVY_ST.ajaxUrl, {
					action:   'ivy_st_' + action,
					_wpnonce: IVY_ST.nonce,
					user_id:  ids[i]
				})
					.done(function (resp) {
						if (resp && resp.success && resp.data && resp.data.enrolled) {
							lastEnrolled = resp.data.enrolled;
						}
					})
					.always(function () {
						next(i + 1);
					});
			}
			next(0);
		}

		function fetchLeft(page, q) {
			if (state.loading) return;
			state.loading = true;
			$leftList.html('<li class="ivy-st-dual-empty">로딩 중...</li>');

			$.post(IVY_ST.ajaxUrl, {
				action:   'ivy_st_user_list',
				_wpnonce: IVY_ST.nonce,
				paged:    page,
				q:        q
			})
				.done(function (resp) {
					if (resp && resp.success && resp.data) {
						state.page  = resp.data.paged;
						state.pages = resp.data.total_pages;
						state.total = resp.data.total;
						renderLeft(resp.data.results || []);
						updatePagination();
					} else {
						$leftList.html('<li class="ivy-st-dual-empty">로드 실패</li>');
					}
				})
				.fail(function () {
					$leftList.html('<li class="ivy-st-dual-empty">로드 실패</li>');
				})
				.always(function () {
					state.loading = false;
				});
		}

		function renderLeft(users) {
			$leftList.empty();
			if (!users.length) {
				$leftList.html('<li class="ivy-st-dual-empty">사용자가 없습니다.</li>');
				return;
			}
			users.forEach(function (u) {
				$leftList.append(buildItem(u));
			});
		}

		function renderRight(users) {
			$rightList.empty();
			if (!users.length) {
				$rightList.html('<li class="ivy-st-dual-empty">등록된 사용자가 없습니다.</li>');
				return;
			}
			users.forEach(function (u) {
				$rightList.append(buildItem(u));
			});
		}

		function buildItem(u) {
			return $('<li class="ivy-st-dual-item"></li>')
				.attr('data-user-id', String(u.id))
				.attr('data-label', (u.display_name || '') + ' ' + (u.email || ''))
				.append($('<span>').text((u.display_name || '') + ' '))
				.append($('<span class="ivy-st-dual-email">').text('(' + (u.email || '') + ')'));
		}

		function updatePagination() {
			$pageInfo.text('Page ' + state.page + ' of ' + state.pages);
			$prevBtn.prop('disabled', state.page <= 1);
			$nextBtn.prop('disabled', state.page >= state.pages);
		}
	}
})(jQuery);
