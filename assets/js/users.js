/* global IVY_ST, jQuery */
/**
 * 설정 → 사용자 매핑 탭. 검색·추가·해지 인터랙션 (admin-ajax 기반).
 */
(function ($) {
	'use strict';

	$(function () {
		var $list   = $('#ivy-st-allowed-list');
		var $count  = $('#ivy-st-allowed-count');
		var $input  = $('#ivy-st-user-search');
		var $btn    = $('#ivy-st-search-btn');
		var $status = $('#ivy-st-search-status');
		var $result = $('#ivy-st-search-results');

		if (!$list.length) {
			return;
		}

		// ------------------------------ 등록된 사용자 해지 ------------------------------
		// 폼 안의 "submit" 버튼 클릭 동작과 충돌하지 않도록 type=button 으로만 처리.
		$list.on('click', '.ivy-st-remove-btn', function () {
			var $li     = $(this).closest('.ivy-st-mapping-item');
			var userId  = $li.data('user-id');
			var name    = $li.find('.ivy-st-mapping-name').text();
			if (!userId) return;
			if (!window.confirm(name + ' — 등록을 해지할까요?')) return;

			$(this).prop('disabled', true).text('처리 중...');
			$.post(IVY_ST.ajaxUrl, {
				action:   'ivy_st_user_remove',
				_wpnonce: IVY_ST.nonce,
				user_id:  userId
			})
				.done(function (resp) {
					if (resp && resp.success) {
						$li.fadeOut(180, function () {
							$li.remove();
							refreshCount();
							ensureEmptyState();
							// 검색 결과에 같은 user가 표시되어 있다면 "추가 가능" 상태로 갱신
							var $row = $result.find('.ivy-st-mapping-item[data-user-id="' + userId + '"]');
							if ($row.length) {
								$row.find('.ivy-st-action-btn').replaceWith(addBtnHtml());
								$row.find('.ivy-st-mapping-pm').remove();
							}
						});
					} else {
						alert((resp && resp.data && resp.data.message) || '해지에 실패했습니다.');
					}
				})
				.always(function (resp) {
					if (!resp || !resp.success) {
						// 실패 시 버튼 복원
						var $btnRow = $li.find('.ivy-st-remove-btn');
						$btnRow.prop('disabled', false).text('해지');
					}
				});
		});

		// ------------------------------ 검색 ------------------------------
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
		// form submit 동작은 막고 검색만 트리거 (settings 폼 안에 위치하므로)
		$input.on('keydown', function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				triggerSearch();
			}
		});

		// ------------------------------ 추가 ------------------------------
		$result.on('click', '.ivy-st-add-btn', function () {
			var $row    = $(this).closest('.ivy-st-mapping-item');
			var userId  = $row.data('user-id');
			if (!userId) return;

			$(this).prop('disabled', true).text('추가 중...');

			$.post(IVY_ST.ajaxUrl, {
				action:   'ivy_st_user_add',
				_wpnonce: IVY_ST.nonce,
				user_id:  userId
			})
				.done(function (resp) {
					if (resp && resp.success && resp.data && resp.data.user) {
						appendEnrolled(resp.data.user);
						refreshCount();
						ensureEmptyState();
						// 검색 결과 행을 "이미 등록됨"으로 변경
						$row.find('.ivy-st-action-btn').replaceWith('<span class="ivy-st-already">이미 등록됨</span>');
					} else {
						var msg = (resp && resp.data && resp.data.message) || '추가에 실패했습니다.';
						alert(msg);
						$(this).prop('disabled', false).text('추가');
					}
				}.bind(this))
				.fail(function () {
					alert('추가 요청 실패');
					$(this).prop('disabled', false).text('추가');
				}.bind(this));
		});

		// ------------------------------ 렌더 헬퍼 ------------------------------
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

		function buildRow(u, searchCard) {
			var $li = $('<li class="ivy-st-mapping-item"></li>').attr('data-user-id', u.id);
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
			return '<button type="button" class="button button-primary ivy-st-action-btn ivy-st-add-btn">추가</button>';
		}

		function appendEnrolled(u) {
			// "비어 있음" placeholder 제거
			$list.find('.ivy-st-mapping-empty').remove();
			$list.append(buildRow(u, /* searchCard */ false));
		}

		function refreshCount() {
			var n = $list.find('.ivy-st-mapping-item').length;
			$count.text('(' + n + ')');
		}

		function ensureEmptyState() {
			if ($list.find('.ivy-st-mapping-item').length === 0 && $list.find('.ivy-st-mapping-empty').length === 0) {
				$list.append('<li class="ivy-st-mapping-empty">아직 등록된 사용자가 없습니다. 위 검색으로 사용자를 추가하세요.</li>');
			}
		}
	});
})(jQuery);
