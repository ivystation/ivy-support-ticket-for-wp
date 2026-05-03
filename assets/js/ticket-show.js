/* global IVY_ST, IvyST, jQuery */
(function ($) {
	'use strict';

	$(function () {
		// 1) 첨부 다운로드 — presign-get → 새 창
		$(document).on('click', '.ivy-st-att-download', function (e) {
			e.preventDefault();
			var $a    = $(this);
			var r2Key = $a.data('r2key');
			if (!r2Key) {
				return;
			}
			var $orig = $a.text();
			$a.text((IVY_ST.i18n.downloading || '준비 중...'));

			$.post(IVY_ST.ajaxUrl, {
				action:   'ivy_st_presign_get',
				_wpnonce: IVY_ST.nonce,
				r2Key:    r2Key
			})
				.done(function (resp) {
					$a.text($orig);
					if (resp && resp.success && resp.data && resp.data.downloadUrl) {
						window.open(resp.data.downloadUrl, '_blank', 'noopener,noreferrer');
					} else {
						var msg = (resp && resp.data && resp.data.message) || IVY_ST.i18n.genericError;
						alert(msg);
					}
				})
				.fail(function (xhr) {
					$a.text($orig);
					alert((IVY_ST.i18n.genericError || '요청 실패') + ' (HTTP ' + (xhr && xhr.status ? xhr.status : '0') + ')');
				});
		});

		// 2) 댓글 첨부 업로더 바인딩
		if (window.IvyST && window.IvyST.AttachUploader) {
			window.IvyST.AttachUploader.bind({
				fileInput: '#ivy-st-comment-attach-input',
				listEl:    '#ivy-st-comment-attach-list',
				dataInput: '#ivy-st-comment-attach-data',
				maxItems:  5
			});
		}

		// 3) 댓글 작성
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
			var attachments = $('#ivy-st-comment-attach-data').val() || '[]';

			$btn.prop('disabled', true);
			$result
				.removeClass('is-success is-error')
				.addClass('is-pending')
				.text(IVY_ST.i18n.commenting);

			$.post(IVY_ST.ajaxUrl, {
				action:      'ivy_st_add_comment',
				_wpnonce:    nonce,
				ticketId:    ticketId,
				body:        $body.val(),
				attachments: attachments
			})
				.done(function (resp) {
					$result.removeClass('is-pending');
					if (resp && resp.success) {
						$result.addClass('is-success').text(IVY_ST.i18n.commentDone);
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
