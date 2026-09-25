/*! RAR Woo Cart & Checkout — Bangladesh address & phone UX */
(function ($) {
	'use strict';
	if (window.__RAR_WCC_ADDRESS_LOADED__) { return; }
	window.__RAR_WCC_ADDRESS_LOADED__ = true;

	var cfg = window.RAR_WCC_ADDRESS || {};
	var cityMap = cfg.cityMap || {};
	var aliases = cfg.aliases || {};
	var i18n = cfg.i18n || {};
	var allCities = null;

	var BN = { '০': '0', '১': '1', '২': '2', '৩': '3', '৪': '4', '৫': '5', '৬': '6', '৭': '7', '৮': '8', '৯': '9' };
	var OPS = { '013': 'Grameenphone', '017': 'Grameenphone', '014': 'Banglalink', '019': 'Banglalink', '016': 'Airtel', '018': 'Robi', '015': 'Teletalk' };

	function norm(v) { return String(v || '').toLowerCase().replace(/&/g, 'and').replace(/[^a-z0-9]/g, ''); }

	function resolveDistrict(name) {
		var k = norm(name);
		if (!k) { return ''; }
		if (aliases[k] && cityMap[aliases[k]]) { return aliases[k]; }
		var match = '';
		$.each(cityMap, function (district) { if (norm(district) === k) { match = district; return false; } });
		return match;
	}

	function everyCity() {
		if (allCities) { return allCities; }
		var set = {};
		$.each(cityMap, function (_, list) { $.each(list, function (_, c) { set[c] = 1; }); });
		allCities = Object.keys(set).sort(function (a, b) { return a.localeCompare(b); });
		return allCities;
	}

	/* ── Phone ─────────────────────────────────────────────── */

	function toLocal(v) {
		var d = String(v || '').replace(/[০-৯]/g, function (c) { return BN[c]; }).replace(/\D+/g, '');
		if (d.indexOf('00880') === 0) { d = d.slice(4); } else if (d.indexOf('880') === 0) { d = d.slice(2); } else if (d.length === 10 && d.charAt(0) === '1') { d = '0' + d; }
		return /^01[3-9]\d{8}$/.test(d) ? d : '';
	}

	function phoneHint($input) {
		if (!cfg.operatorHint) { return; }
		var $row = $input.closest('.form-row');
		var $hint = $row.find('.rar-wcc-phone-hint');
		if (!$hint.length) { $hint = $('<span class="rar-wcc-phone-hint" aria-live="polite"></span>').appendTo($row.find('.woocommerce-input-wrapper').first().length ? $row.find('.woocommerce-input-wrapper').first() : $row); }
		var raw = $.trim($input.val());
		var digits = raw.replace(/[০-৯]/g, function (c) { return BN[c]; }).replace(/\D+/g, '');
		var local = toLocal(raw);
		$row.removeClass('rar-wcc-phone-ok rar-wcc-phone-bad');
		if (!raw) { $hint.text(''); return; }
		if (local) {
			$row.addClass('rar-wcc-phone-ok');
			$hint.text('✓ ' + (OPS[local.slice(0, 3)] || i18n.phoneOk || 'Valid'));
		} else if (digits.length >= 11) {
			$row.addClass('rar-wcc-phone-bad');
			$hint.text(i18n.phoneInvalid || 'Invalid number');
		} else {
			$hint.text('');
		}
	}

	function normalizePhoneInput($input) {
		var v = $input.val();
		var converted = String(v || '').replace(/[০-৯]/g, function (c) { return BN[c]; });
		if (converted !== v) { $input.val(converted); }
	}

	/* ── Town / City ───────────────────────────────────────── */

	function selectedDistrict(sel) {
		var $s = $(sel);
		if (!$s.length) { return ''; }
		return $s.is('select') ? $.trim($s.find('option:selected').text()) : $.trim($s.val());
	}

	function ensureCountry(sel) {
		var $c = $(sel);
		if (!$c.length) { return; }
		if ($c.is('select') && !$c.find('option[value="BD"]').length) { $c.append($('<option/>', { value: 'BD', text: 'Bangladesh' })); }
		if ($c.val() !== 'BD') { $c.val('BD'); }
	}

	function ensureCitySelect(sel, fieldName) {
		var $old = $(sel);
		if (!$old.length) { return $(); }
		if ($old.is('select')) { return $old; }
		var current = $.trim($old.val() || '');
		var $select = $('<select/>', {
			id: sel.replace('#', ''),
			name: fieldName,
			'class': ($old.attr('class') || '') + ' rar-wcc-city-select',
			autocomplete: 'address-level2',
			'data-placeholder': cfg.cityPlaceholder || ''
		});
		if ($old.prop('required') || $old.closest('.form-row').hasClass('validate-required')) { $select.attr('aria-required', 'true'); }
		$old.replaceWith($select);
		if (current) { $select.data('previous-city', current); }
		return $select;
	}

	function initSearch($select) {
		if (!$select.length || !cfg.searchableCity) { return; }
		var fn = $.fn.selectWoo ? 'selectWoo' : ($.fn.select2 ? 'select2' : '');
		if (!fn) { return; }
		try { if ($select.hasClass('select2-hidden-accessible')) { $select[fn]('destroy'); } } catch (e) {}
		var options = {
			width: '100%',
			placeholder: cfg.cityPlaceholder || '',
			allowClear: false,
			matcher: function (params, data) {
				if (!params.term || !$.trim(params.term)) { return data; }
				if (!data.text) { return null; }
				return norm(data.text).indexOf(norm(params.term)) !== -1 ? data : null;
			}
		};
		if (cfg.allowCustomCity) {
			options.tags = true;
			options.createTag = function (params) {
				var t = $.trim(params.term);
				if (t.length < 2) { return null; }
				return { id: t, text: (i18n.useTyped || 'Use “%s”').replace('%s', t), newTag: true };
			};
			options.templateSelection = function (data) { return data.newTag ? data.id : data.text; };
		}
		$select[fn](options);
		$select.off('select2:open.rarwcc').on('select2:open.rarwcc', function () {
			window.setTimeout(function () { var f = document.querySelector('.select2-container--open .select2-search__field'); if (f) { f.focus(); } }, 0);
		});
	}

	function populate(ctx, keepCurrent) {
		if (!cfg.searchableCity) { return; }
		var $select = ensureCitySelect(ctx.city, ctx.cityName);
		if (!$select.length) { return; }
		var previous = keepCurrent ? ($select.val() || $select.data('previous-city') || '') : '';
		var district = resolveDistrict(selectedDistrict(ctx.state));
		var list = district && cityMap[district] ? cityMap[district] : everyCity();
		var signature = (district || '*') + '|' + list.length;

		if ($select.data('rar-sig') !== signature) {
			var frag = document.createDocumentFragment();
			var ph = document.createElement('option');
			ph.value = '';
			ph.textContent = district ? (i18n.selectCity || 'Select town / city') : (i18n.selectFirst || i18n.selectCity || '');
			frag.appendChild(ph);
			for (var i = 0; i < list.length; i++) {
				var o = document.createElement('option');
				o.value = list[i];
				o.textContent = list[i];
				frag.appendChild(o);
			}
			$select.empty()[0].appendChild(frag);
			$select.data('rar-sig', signature);
		}

		if (previous && list.indexOf(previous) !== -1) {
			$select.val(previous);
		} else if (previous && cfg.allowCustomCity && keepCurrent) {
			$select.append($('<option/>', { value: previous, text: previous })).val(previous);
		} else {
			$select.val('');
		}
		$select.removeData('previous-city');
		initSearch($select);
	}

	function refineCheckout() {
		if (!cfg.checkoutEnabled) { return; }
		if (cfg.orderNotesLabel) {
			var $label = $('label[for="order_comments"]');
			if ($label.length && $label.data('rar-done') !== cfg.orderNotesLabel) {
				var $opt = $label.find('.optional').detach();
				$label.contents().filter(function () { return this.nodeType === 3; }).remove();
				$label.prepend(document.createTextNode(cfg.orderNotesLabel + ' '));
				if ($opt.length) { $label.append($opt); }
				$label.data('rar-done', cfg.orderNotesLabel);
			}
		}
	}

	var checkoutCtx = { state: '#billing_state', city: '#billing_city', cityName: 'billing_city', country: '#billing_country' };
	var cartCtx = { state: '#calc_shipping_state', city: '#calc_shipping_city', cityName: 'calc_shipping_city', country: '#calc_shipping_country' };

	function bootCheckout(keep) {
		if (!cfg.checkoutEnabled || !$(checkoutCtx.state).length) { return; }
		if (cfg.hideCountry) { ensureCountry(checkoutCtx.country); }
		populate(checkoutCtx, keep);
		refineCheckout();
		var $p = $('#billing_phone');
		if ($p.length && $p.val()) { phoneHint($p); }
	}
	function bootCart(keep) {
		if (!cfg.cartEnabled || !$(cartCtx.state).length) { return; }
		if (cfg.hideCountry) { ensureCountry(cartCtx.country); }
		populate(cartCtx, keep);
	}

	var $body = $(document.body);
	$body.on('change', '#billing_state', function () {
		window.setTimeout(function () { populate(checkoutCtx, false); $body.trigger('update_checkout'); }, 60);
	});
	$body.on('change', '#billing_city', function () { $body.trigger('update_checkout'); });
	$body.on('change', '#calc_shipping_state', function () { window.setTimeout(function () { populate(cartCtx, false); }, 60); });
	$body.on('country_to_state_changed', function () { window.setTimeout(function () { bootCheckout(true); bootCart(true); }, 30); });
	$body.on('updated_checkout', function () { window.setTimeout(function () { bootCheckout(true); }, 30); });
	$body.on('updated_wc_div updated_cart_totals updated_shipping_method', function () { window.setTimeout(function () { bootCart(true); }, 60); });
	$body.on('input', '#billing_phone', function () { normalizePhoneInput($(this)); phoneHint($(this)); });
	$body.on('blur', '#billing_phone', function () {
		var $i = $(this);
		if (cfg.phoneValidation) {
			var local = toLocal($i.val());
			if (local && $i.val() !== local) { $i.val(local); }
		}
		phoneHint($i);
	});

	$(function () { bootCheckout(true); bootCart(true); });
})(jQuery);
