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
	// 사용자 매핑 (사용자 매핑 탭)
	// ────────────────────────────────────────────────
	function bindUserMapping() {
		var $list   = $('#ivy-st-allowed-list');
		var $count  = $('#ivy-st-allowed-count');
		var $input  = $('#ivy-st-user-search');
		var $btn    = $('#ivy-st-search-btn');
		var $status = $('#ivy-st-search-status');
		var $result = $('#ivy-st-search-results');

		if (!$list.length) {
			return;
		}
		// eslint-disable-next-line no-console
		console.log('[ivy-support-ticket] user mapping bound');

		// ---- 등록된 사용자 해지 (이벤트 위임) ----
		$list.on('click', '.ivy-st-remove-btn', function () {
			var $li    = $(this).closest('.ivy-st-mapping-item');
			var userId = parseInt($li.attr('data-user-id'), 10);
			var name   = $li.find('.ivy-st-mapping-name').text();
			if (!userId) return;
			if (!window.confirm(name + ' — 등록을 해지할까요?')) return;

			var $remove = $(this);
			$remove.prop('disabled', true).text('처리 중...');

			$.post(IVY_ST.ajaxUrl, {
				action:   'ivy_st_user_remove',
				_wpnonce: IVY_ST.nonce,
				user_id:  userId
			})
				.done(function (resp) {
					if (resp && resp.success && resp.data) {
						renderEnrolled(resp.data.enrolled || []);
						syncSearchResultsAfterRemove(userId);
					} else {
						alert((resp && resp.data && resp.data.message) || '해지에 실패했습니다.');
						$remove.prop('disabled', false).text('해지');
					}
				})
				.fail(function () {
					alert('해지 요청 실패');
					$remove.prop('disabled', false).text('해지');
				});
		});

		// ---- 검색 ----
		function triggerSearch() {
			var q = $.trim($input.val());
			if (q.length < 2) {
				$status.removeClass('is-success is-error').text('2자 이상 입력해 주세요.');
				return;
			}
			$status.removeClass('is-success is-error').text('검색 중...');
			$result.empty();

			$.post(IVY_ST.ajaxUrl, {
				action:   'ivy_st_user_search',
				_wpnonce: IVY_ST.nonce,
				q:        q
			})
				.done(function (resp) {
					if (resp && resp.success) {
						var users = (resp.data && resp.data.results) || [];
						$status.text(users.length + '명 발견');
						renderSearchResults(users);
					} else {
						var msg = (resp && resp.data && resp.data.message) || '검색에 실패했습니다.';
						$status.addClass('is-error').text(msg);
					}
				})
				.fail(function () {
					$status.addClass('is-error').text('검색 요청 실패');
				});
		}

		$btn.on('click', triggerSearch);
		$input.on('keydown', function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				triggerSearch();
			}
		});

		// ---- 검색 결과에서 추가 ----
		$result.on('click', '.ivy-st-add-btn', function () {
			var $row    = $(this).closest('.ivy-st-mapping-item');
			var userId  = parseInt($row.attr('data-user-id'), 10);
			if (!userId) return;

			var $addBtn = $(this);
			$addBtn.prop('disabled', true).text('추가 중...');
			// eslint-disable-next-line no-console
			console.log('[ivy-support-ticket] user_add', userId);

			$.post(IVY_ST.ajaxUrl, {
				action:   'ivy_st_user_add',
				_wpnonce: IVY_ST.nonce,
				user_id:  userId
			})
				.done(function (resp) {
					if (resp && resp.success && resp.data) {
						renderEnrolled(resp.data.enrolled || []);
						$addBtn.replaceWith('<span class="ivy-st-already">이미 등록됨</span>');
					} else {
						var msg = (resp && resp.data && resp.data.message) || '추가에 실패했습니다.';
						alert(msg);
						$addBtn.prop('disabled', false).text('추가');
					}
				})
				.fail(function (xhr) {
					alert('추가 요청 실패 (HTTP ' + (xhr && xhr.status ? xhr.status : '0') + ')');
					$addBtn.prop('disabled', false).text('추가');
				});
		});

		// ────────────── 렌더링 헬퍼 ──────────────

		/** 서버가 돌려준 enrolled 배열로 등록된 사용자 목록 전체를 재렌더링한다. */
		function renderEnrolled(users) {
			$list.empty();
			if (!users.length) {
				$list.append('<li class="ivy-st-mapping-empty">아직 등록된 사용자가 없습니다. 위 검색으로 사용자를 추가하세요.</li>');
			} else {
				users.forEach(function (u) {
					$list.append(buildRow(u, /* searchCard */ false));
				});
			}
			$count.text('(' + users.length + ')');
		}

		function renderSearchResults(users) {
			$result.empty();
			if (!users.length) {
				$result.html('<li class="ivy-st-mapping-empty">검색 결과가 없습니다.</li>');
				return;
			}
			users.forEach(function (u) {
				$result.append(buildRow(u, /* searchCard */ true));
			});
		}

		/** 해지 직후 검색 결과에 동일 user가 남아 있으면 "추가 가능" 상태로 전환. */
		function syncSearchResultsAfterRemove(userId) {
			var $row = $result.find('.ivy-st-mapping-item').filter(function () {
				return parseInt($(this).attr('data-user-id'), 10) === userId;
			});
			if (!$row.length) return;
			$row.find('.ivy-st-already').remove();
			$row.find('.ivy-st-add-btn').remove();
			$row.append(addBtnHtml());
		}

		function buildRow(u, searchCard) {
			var $li = $('<li class="ivy-st-mapping-item"></li>').attr('data-user-id', String(u.id));
			var $info = $('<div class="ivy-st-mapping-info"></div>')
				.append($('<strong class="ivy-st-mapping-name"></strong>').text(u.display_name))
				.append($('<span class="ivy-st-mapping-email"></span>').text(u.email))
				.append($('<span class="ivy-st-mapping-roles"></span>').text((u.roles || []).join(', ')));
			if (u.pm_user_id) {
				$info.append('<span class="ivy-st-mapping-pm" title="pm 시스템에 매핑됨">✓ pm</span>');
			}
			$li.append($info);

			if (searchCard) {
				if (u.enrolled) {
					$li.append('<span class="ivy-st-already">이미 등록됨</span>');
				} else {
					$li.append(addBtnHtml());
				}
			} else {
				$li.append('<button type="button" class="button button-link-delete ivy-st-remove-btn">해지</button>');
			}
			return $li;
		}

		function addBtnHtml() {
			return '<button type="button" class="button button-primary ivy-st-add-btn">추가</button>';
		}
	}
})(jQuery);
