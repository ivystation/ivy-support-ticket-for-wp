/* global IVY_ST, jQuery */
(function ($) {
	'use strict';

	$(function () {
		var $form    = $('#ivy-st-comment-form');
		var $body    = $('#ivy-st-comment-body');
		var $result  = $('#ivy-st-comment-result');

		if (!$form.length) {
			return;
		}

		$form.on('submit', function (e) {
			e.preventDefault();

			var ticketId = $form.data('ticket-id');
			var $btn     = $form.find('button[type=submit]');
			var nonce    = $form.find('input[name=_wpnonce]').val();

			$btn.prop('disabled', true);
			$result
				.removeClass('is-success is-error')
				.addClass('is-pending')
				.text(IVY_ST.i18n.commenting);

			$.post(IVY_ST.ajaxUrl, {
				action:   'ivy_st_add_comment',
				_wpnonce: nonce,
				ticketId: ticketId,
				body:     $body.val()
			})
				.done(function (resp) {
					$result.removeClass('is-pending');
					if (resp && resp.success) {
						$result.addClass('is-success').text(IVY_ST.i18n.commentDone);
						// 페이지 새로고침으로 댓글 영역 갱신 (목록 캐시 단순화).
						setTimeout(function () { window.location.reload(); }, 400);
					} else {
						var msg = (resp && resp.data && resp.data.message) || IVY_ST.i18n.genericError;
						$result.addClass('is-error').text(msg);
						$btn.prop('disabled', false);
					}
				})
				.fail(function (xhr) {
					$result
						.removeClass('is-pending')
						.addClass('is-error')
						.text(IVY_ST.i18n.genericError + ' (HTTP ' + (xhr && xhr.status ? xhr.status : '0') + ')');
					$btn.prop('disabled', false);
				});
		});
	});
})(jQuery);
