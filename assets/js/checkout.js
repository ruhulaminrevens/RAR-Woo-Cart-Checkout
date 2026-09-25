/*! RAR Woo Cart & Checkout — checkout quantity editor + incomplete-order capture */
(function ($) {
	'use strict';
	if (window.__RAR_WCC_CHECKOUT_LOADED__) { return; }
	window.__RAR_WCC_CHECKOUT_LOADED__ = true;

	var cfg = window.RAR_WCC_CHECKOUT || {};
	var $body = $(document.body);

	/* ── Quantity editor ───────────────────────────────────── */
	var busy = false;

	function setQty($wrap, qty) {
		if (busy) { return; }
		busy = true;
		var $review = $('.woocommerce-checkout-review-order-table');
		$review.addClass('rar-wcc-busy');
		$.post(cfg.qtyUrl, { key: $wrap.data('key'), qty: qty, nonce: cfg.nonce })
			.done(function (res) {
				if (res && res.success) {
					if (res.data.empty) { window.location.reload(); return; }
					$.each(res.data.chosen || [], function (i, method) {
						var $inputs = $('[name="shipping_method[' + i + ']"]');
						if ($inputs.filter('[type="radio"]').length) {
							$inputs.filter('[value="' + method + '"]').prop('checked', true);
						} else if ($inputs.length && method) {
							$inputs.val(method);
						}
					});
					$body.trigger('update_checkout');
					$body.trigger('wc_fragment_refresh');
				} else {
					window.alert((res && res.data && res.data.message) || 'Error');
					$review.removeClass('rar-wcc-busy');
				}
			})
			.fail(function () { $review.removeClass('rar-wcc-busy'); })
			.always(function () { busy = false; });
	}

	if (cfg.qtyEditor) {
		$(document).on('click', '.rar-wcc-qty-btn', function (e) {
			e.preventDefault();
			var $wrap = $(this).closest('.rar-wcc-qty');
			var cur = parseInt($wrap.find('.rar-wcc-qty-val').text().replace(/\D+/g, ''), 10) || 1;
			var next = cur + parseInt($(this).data('step'), 10);
			var max = parseInt($wrap.data('max'), 10) || 0;
			if (next < 1 || (max > 0 && next > max)) { return; }
			$wrap.find('.rar-wcc-qty-val').text(next);
			setQty($wrap, next);
		});
		$(document).on('click', '.rar-wcc-qty-remove', function (e) {
			e.preventDefault();
			setQty($(this).closest('.rar-wcc-qty'), 0);
		});
		$body.on('updated_checkout', function () { $('.woocommerce-checkout-review-order-table').removeClass('rar-wcc-busy'); });
	}

	/* ── Incomplete-order capture ──────────────────────────── */
	if (!cfg.capture || !cfg.captureUrl) { return; }

	var BN = { '০': '0', '১': '1', '২': '2', '৩': '3', '৪': '4', '৫': '5', '৬': '6', '৭': '7', '৮': '8', '৯': '9' };
	var timer = null, last = '', done = false;

	function validPhone(v) {
		var d = String(v || '').replace(/[০-৯]/g, function (c) { return BN[c]; }).replace(/\D+/g, '');
		if (d.indexOf('880') === 0) { d = d.slice(2); }
		return /^01[3-9]\d{8}$/.test(d);
	}

	function payload() {
		return {
			phone: $('#billing_phone').val() || '',
			name: $.trim(($('#billing_first_name').val() || '') + ' ' + ($('#billing_last_name').val() || '')),
			email: $('#billing_email').val() || '',
			address: $.trim(($('#billing_address_1').val() || '') + ' ' + ($('#billing_address_2').val() || '')),
			state: $('#billing_state').val() || '',
			city: $('#billing_city').val() || '',
			note: $('#order_comments').val() || '',
			source: cfg.source || 'checkout',
			nonce: cfg.nonce
		};
	}

	function send(beacon) {
		if (done) { return; }
		var p = payload();
		if (!validPhone(p.phone)) { return; }
		var sig = JSON.stringify(p);
		if (sig === last) { return; }
		last = sig;
		if (beacon && navigator.sendBeacon) {
			var fd = new FormData();
			$.each(p, function (k, v) { fd.append(k, v); });
			navigator.sendBeacon(cfg.captureUrl, fd);
			return;
		}
		$.post(cfg.captureUrl, p);
	}

	function schedule() {
		window.clearTimeout(timer);
		timer = window.setTimeout(function () { send(false); }, 1500);
	}

	$(document).on('input change', '#billing_phone, #billing_first_name, #billing_last_name, #billing_email, #billing_address_1, #billing_address_2, #billing_state, #billing_city, #order_comments', schedule);
	document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') { send(true); } });
	window.addEventListener('pagehide', function () { send(true); });
	$('form.checkout').on('checkout_place_order', function () { window.clearTimeout(timer); send(false); });
	$('form.checkout').on('checkout_place_order_success', function () { done = true; });
})(jQuery);
