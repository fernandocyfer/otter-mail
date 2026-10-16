(function ($) {
	'use strict';

	if (typeof ottermailCart === 'undefined') {
		return;
	}

	var timer;

	function capture() {
		var email = $('input[name="billing_email"]').val();
		if (!email || email.indexOf('@') === -1) {
			return;
		}
		$.post(ottermailCart.ajaxUrl, {
			action: 'ottermail_capture_email',
			nonce: ottermailCart.nonce,
			email: email,
			first_name: $('input[name="billing_first_name"]').val() || ''
		});
	}

	$(document.body).on('change blur', 'input[name="billing_email"]', function () {
		clearTimeout(timer);
		timer = setTimeout(capture, 800);
	});

	$(document.body).on('updated_checkout', function () {
		clearTimeout(timer);
		timer = setTimeout(capture, 800);
	});
})(jQuery);
