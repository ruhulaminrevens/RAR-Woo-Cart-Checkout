/*! RAR Woo Cart & Checkout — runs inside the Express checkout modal (iframe) */
(function ($) {
	'use strict';
	var inFrame = false;
	try { inFrame = window.top !== window.self; } catch (e) { inFrame = true; }

	function tell(type, data) {
		try { window.parent.postMessage($.extend({ rarWcc: type }, data || {}), window.location.origin); } catch (e) {}
	}

	if (!inFrame) {
		// Opened directly (e.g. new tab) — behave like a normal checkout page.
		document.documentElement.classList.remove('rar-wcc-in-frame');
		document.body && document.body.classList.remove('rar-wcc-in-frame');
		return;
	}

	// Every redirect after a successful order (thank-you page or an external
	// payment gateway such as bKash/SSLCommerz/Nagad) must leave the modal,
	// because most gateways refuse to load inside an iframe.
	$('form.checkout').on('checkout_place_order_success', function (e, result) {
		if (result && result.redirect) {
			tell('redirect', { url: result.redirect });
			try { window.top.location.href = result.redirect; } catch (err) { window.location.href = result.redirect; }
			return false;
		}
	});

	$(document.body).on('checkout_error', function () { tell('error'); });
	$(document.body).on('updated_checkout', function () { tell('updated'); });

	// Links inside the modal (terms, product names…) open at top level.
	$(document).on('click', 'a[href]', function () {
		var a = this;
		if (a.target || a.getAttribute('href').charAt(0) === '#' || $(a).closest('.woocommerce-form-coupon-toggle, .rar-wcc-qty, .wc_payment_methods, .woocommerce-terms-and-conditions-link').length) { return; }
		if (/^(tel|mailto|javascript):/i.test(a.getAttribute('href'))) { return; }
		a.target = '_top';
	});

	document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { tell('close'); } });

	$(function () { tell('ready'); });
	$(window).on('load', function () { tell('ready'); });
})(jQuery);
