/*! RAR Woo Cart & Checkout — Express Buy Now modal */
(function () {
	'use strict';
	var cfg = window.RAR_WCC_EXPRESS || {};
	if (!cfg.selector || window.__RAR_WCC_EXPRESS_LOADED__) { return; }
	window.__RAR_WCC_EXPRESS_LOADED__ = true;
	window.__nabiadExpressBuyNowV12 = true; // Stops the legacy WPCode snippet if still active.

	var i18n = cfg.i18n || {};
	var modal = null, frame = null, lastFocus = null, slowTimer = null, ordered = false;

	function esc(v) {
		return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]; });
	}

	function refreshFragments() {
		if (window.jQuery) { window.jQuery(document.body).trigger('wc_fragment_refresh'); }
	}

	function setLoader(html, isError) {
		if (!modal) { return; }
		var l = modal.querySelector('.rar-wcc-express-loader');
		l.style.display = 'flex';
		l.classList.toggle('is-error', !!isError);
		l.innerHTML = html;
	}

	function spinner(text) {
		return '<span class="rar-wcc-express-spinner" aria-hidden="true"></span><span>' + esc(text) + '</span>';
	}

	function closeModal() {
		window.clearTimeout(slowTimer);
		if (!modal) { return; }
		modal.classList.add('is-closing');
		var m = modal;
		modal = null; frame = null;
		window.setTimeout(function () { if (m.parentNode) { m.parentNode.removeChild(m); } }, 180);
		document.documentElement.classList.remove('rar-wcc-express-open');
		document.body.classList.remove('rar-wcc-express-open');
		if (!ordered) { refreshFragments(); }
		if (lastFocus && lastFocus.focus) { try { lastFocus.focus(); } catch (e) {} }
	}

	function trapFocus(e) {
		if (!modal || e.key !== 'Tab') { return; }
		var f = modal.querySelectorAll('button, a[href], iframe');
		if (!f.length) { return; }
		var first = f[0], last = f[f.length - 1];
		if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
		else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
	}

	function openModal() {
		if (modal) { return; }
		lastFocus = document.activeElement;
		modal = document.createElement('div');
		modal.className = 'rar-wcc-express-overlay';
		modal.innerHTML =
			'<div class="rar-wcc-express-dialog" role="dialog" aria-modal="true" aria-labelledby="rar-wcc-express-title">' +
				'<div class="rar-wcc-express-head">' +
					'<div class="rar-wcc-express-title-wrap"><div class="rar-wcc-express-shield" aria-hidden="true">' +
						'<svg viewBox="0 0 24 24" width="16" height="16"><path fill="currentColor" d="M12 2 4 5v6c0 5 3.4 9.7 8 11 4.6-1.3 8-6 8-11V5l-8-3Zm-1.2 14.2-3.5-3.5 1.4-1.4 2.1 2.1 4.9-4.9 1.4 1.4-6.3 6.3Z"/></svg>' +
					'</div><div><div class="rar-wcc-express-title" id="rar-wcc-express-title">' + esc(cfg.title) + '</div>' +
					'<div class="rar-wcc-express-subtitle">' + esc(cfg.subtitle) + '</div></div></div>' +
					'<button type="button" class="rar-wcc-express-close" aria-label="' + esc(i18n.close || 'Close') + '">&times;</button>' +
				'</div>' +
				'<div class="rar-wcc-express-frame-wrap">' +
					'<div class="rar-wcc-express-loader" role="status">' + spinner(cfg.loaderText) + '</div>' +
					'<iframe class="rar-wcc-express-frame" title="' + esc(cfg.title) + '"></iframe>' +
				'</div>' +
				'<div class="rar-wcc-express-foot">' + (cfg.footerText ? esc(cfg.footerText) + ' · ' : '') +
					'<a href="' + esc(cfg.fullCheckout) + '" target="_top">' + esc(cfg.openFullText) + '</a></div>' +
			'</div>';
		document.body.appendChild(modal);
		document.documentElement.classList.add('rar-wcc-express-open');
		document.body.classList.add('rar-wcc-express-open');
		frame = modal.querySelector('.rar-wcc-express-frame');
		frame.addEventListener('load', onFrameLoad);
		modal.querySelector('.rar-wcc-express-close').addEventListener('click', closeModal);
		modal.addEventListener('click', function (e) { if (e.target === modal) { closeModal(); } });
		modal.addEventListener('keydown', trapFocus);
		window.requestAnimationFrame(function () { if (modal) { modal.classList.add('is-open'); modal.querySelector('.rar-wcc-express-close').focus(); } });
	}

	function onFrameLoad() {
		if (!frame || !modal) { return; }
		try {
			var href = String(frame.contentWindow.location.href || '');
			if (href.indexOf('order-received') !== -1) { ordered = true; window.location.href = href; return; }
			var doc = frame.contentDocument;
			if (doc && doc.body && !doc.querySelector('form.checkout')) {
				// Cart empty / redirected elsewhere: show the page at top level instead.
				if (doc.querySelector('.cart-empty, .wc-empty-cart-message')) { window.location.href = cfg.fullCheckout; return; }
			}
		} catch (e) { /* cross-origin (gateway) — handled by frame.js redirect */ }
		window.clearTimeout(slowTimer);
		modal.querySelector('.rar-wcc-express-loader').style.display = 'none';
	}

	function showError(msg, retry) {
		setLoader(
			'<span class="rar-wcc-express-error-ico" aria-hidden="true">!</span><span>' + esc(msg || i18n.error) + '</span>' +
			'<span class="rar-wcc-express-actions"><button type="button" class="rar-wcc-express-retry">' + esc(i18n.retry || 'Try again') + '</button>' +
			'<a href="' + esc(cfg.fullCheckout) + '" target="_top">' + esc(cfg.openFullText) + '</a></span>', true);
		var b = modal && modal.querySelector('.rar-wcc-express-retry');
		if (b) { b.addEventListener('click', function () { if (retry) { retry(); } else { closeModal(); } }); }
	}

	function loadCheckout() {
		if (!frame) { return; }
		setLoader(spinner(cfg.loaderText));
		slowTimer = window.setTimeout(function () {
			setLoader(spinner(i18n.slow || cfg.loaderText) + '<a class="rar-wcc-express-slow-link" href="' + esc(cfg.fullCheckout) + '" target="_top">' + esc(cfg.openFullText) + '</a>');
		}, 12000);
		frame.src = cfg.checkoutUrl + (cfg.checkoutUrl.indexOf('?') === -1 ? '?' : '&') + 't=' + Date.now();
	}

	function productId(form, btn) {
		var el = form.querySelector('input[name="product_id"]') || form.querySelector('[name="add-to-cart"]');
		return (el && el.value) || btn.value || form.getAttribute('data-product_id') || '';
	}

	function nativeFallback(btn) {
		btn.setAttribute('data-rar-wcc-bypass', '1');
		try { btn.click(); } catch (e) { window.location.href = cfg.fullCheckout; }
		window.setTimeout(function () { btn.removeAttribute('data-rar-wcc-bypass'); }, 1500);
	}

	function restore(btn, text, html) {
		btn.classList.remove('rar-wcc-buy-now-loading');
		btn.disabled = false;
		btn.removeAttribute('aria-busy');
		if (html != null) { btn.innerHTML = html; } else { btn.textContent = text; }
	}

	function addAndOpen(form, btn) {
		var html = btn.innerHTML;
		btn.classList.add('rar-wcc-buy-now-loading');
		btn.setAttribute('aria-busy', 'true');
		btn.disabled = true;
		btn.textContent = cfg.loadingText;
		openModal();

		var fd = new FormData(form);
		fd.delete('add-to-cart');
		fd.set('rar_product_id', productId(form, btn));

		var attempt = function () {
			setLoader(spinner(cfg.loaderText));
			fetch(cfg.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					restore(btn, '', html);
					if (res && res.success) { refreshFragments(); loadCheckout(); return; }
					var data = (res && res.data) || {};
					if (data.fallback) { closeModal(); nativeFallback(btn); return; }
					showError(data.message, attempt);
				})
				.catch(function () { restore(btn, '', html); showError(i18n.error, attempt); });
		};
		attempt();
	}

	document.addEventListener('click', function (e) {
		var btn = e.target.closest && e.target.closest(cfg.selector);
		if (!btn || btn.getAttribute('data-rar-wcc-bypass') === '1') { return; }
		var form = btn.closest('form.cart');
		if (!form) { return; }

		var cls = document.body.className;
		if (/product-type-(external|grouped)/.test(cls)) { return; }

		var variation = form.querySelector('input[name="variation_id"]');
		var addBtn = form.querySelector('.single_add_to_cart_button');
		if (variation && (!variation.value || variation.value === '0')) {
			// Let the theme show "please select options"; our own button shows a hint.
			if (btn.classList.contains('rar-wcc-buy-now')) { e.preventDefault(); window.alert(i18n.chooseFirst); }
			return;
		}
		if (addBtn && (addBtn.disabled || addBtn.classList.contains('disabled') || addBtn.classList.contains('wc-variation-selection-needed'))) { return; }
		if (typeof form.checkValidity === 'function' && !form.checkValidity()) { if (form.reportValidity) { form.reportValidity(); } e.preventDefault(); return; }

		e.preventDefault();
		e.stopPropagation();
		if (e.stopImmediatePropagation) { e.stopImmediatePropagation(); }
		addAndOpen(form, btn);
	}, true);

	document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && modal) { closeModal(); } });

	window.addEventListener('message', function (e) {
		if (e.origin !== window.location.origin || !e.data || !e.data.rarWcc) { return; }
		switch (e.data.rarWcc) {
			case 'ready':
				if (modal) { window.clearTimeout(slowTimer); modal.querySelector('.rar-wcc-express-loader').style.display = 'none'; }
				break;
			case 'redirect':
				ordered = true;
				break;
			case 'close':
				closeModal();
				break;
		}
	});

	// Public API for themes/other plugins.
	window.RARWCCExpress = { close: closeModal, open: function (form, btn) { addAndOpen(form, btn || form.querySelector('button')); } };
})();
