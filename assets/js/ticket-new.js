/* global IVY_ST, IvyST, jQuery, tinyMCE */
(function ($) {
	'use strict';

	$(function () {
		var $form   = $('#ivy-st-new-form');
		var $btn    = $('#ivy-st-submit');
		var $result = $('#ivy-st-new-result');

		if (!$form.length) {
			return;
		}

		// 첨부 업로더 — 폼 직렬화 시 hidden #ivy-st-attach-data 가 attachments JSON으로 함께 전송된다.
		if (window.IvyST && window.IvyST.AttachUploader) {
			window.IvyST.AttachUploader.bind({
				fileInput: '#ivy-st-attach-input',
				listEl:    '#ivy-st-attach-list',
				dataInput: '#ivy-st-attach-data',
				maxItems:  5
			});
		}

		$form.on('submit', function (e) {
			e.preventDefault();

			// wp_editor (TinyMCE 활성화 시)는 hidden textarea로 자동 동기화되지 않을 때가 있어
			// 제출 직전에 명시적으로 trigger.
			if (typeof tinyMCE !== 'undefined' && tinyMCE.get('ivy-st-description')) {
				tinyMCE.get('ivy-st-description').save();
			}

			$btn.prop('disabled', true);
			$result
				.removeClass('notice-success notice-error')
				.addClass('notice notice-info ivy-st-result-show')
				.html('<p>' + IVY_ST.i18n.submitting + '</p>');

			var data = $form.serializeArray();
			data.push({ name: 'action', value: 'ivy_st_create_ticket' });

			$.post(IVY_ST.ajaxUrl, $.param(data))
				.done(function (resp) {
					if (resp && resp.success) {
						$result
							.removeClass('notice-info notice-error')
							.addClass('notice notice-success')
							.html('<p>' + IVY_ST.i18n.created + ' (' + (resp.data.ticket && resp.data.ticket.ticketNumber ? resp.data.ticket.ticketNumber : '') + ')</p>');
						// 짧은 지연 후 상세 페이지로 이동.
						setTimeout(function () {
							if (resp.data && resp.data.showUrl) {
								window.location.href = resp.data.showUrl;
							}
						}, 600);
					} else {
						var msg = (resp && resp.data && resp.data.message) || IVY_ST.i18n.genericError;
						$result
							.removeClass('notice-info notice-success')
							.addClass('notice notice-error')
							.html('<p>' + msg + '</p>');
						$btn.prop('disabled', false);
					}
				})
				.fail(function (xhr) {
					$result
						.removeClass('notice-info notice-success')
						.addClass('notice notice-error')
						.html('<p>' + IVY_ST.i18n.genericError + ' (HTTP ' + (xhr && xhr.status ? xhr.status : '0') + ')</p>');
					$btn.prop('disabled', false);
				});
		});
	});
})(jQuery);
