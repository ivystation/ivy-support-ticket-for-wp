/* global IVY_ST, jQuery */
(function ($) {
	'use strict';

	// 연결 테스트 버튼: 저장된 API Key로 health 엔드포인트를 호출하고 결과를 옆에 표시.
	$(function () {
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
	});
})(jQuery);
