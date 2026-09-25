/*! RAR Woo Cart & Checkout — admin */
(function ($) {
	'use strict';
	var cfg = window.RAR_WCC_ADMIN || {};
	var i18n = cfg.i18n || {};

	/* Settings tabs */
	var $form = $('#rar-wcc-settings');
	if ($form.length) {
		var dirty = false;
		$('.rar-wcc-tab').on('click', function () {
			var tab = $(this).data('tab');
			$('.rar-wcc-tab').removeClass('is-active').attr('aria-selected', 'false');
			$(this).addClass('is-active').attr('aria-selected', 'true');
			$('.rar-wcc-tabpanel').attr('hidden', true).filter('[data-panel="' + tab + '"]').removeAttr('hidden');
			$('#rar-wcc-tab-input').val(tab);
			if (window.history && window.history.replaceState) {
				var u = new URL(window.location.href);
				u.searchParams.set('tab', tab);
				u.searchParams.delete('rar_msg');
				window.history.replaceState(null, '', u.toString());
			}
		});
		$form.on('input change', ':input', function () {
			dirty = true;
			$('.rar-wcc-dirty').removeAttr('hidden');
			if (this.type === 'color') { $(this).siblings('.rar-wcc-color-code').text(this.value); }
		});
		$form.on('submit', function () { dirty = false; });
		window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = i18n.unsaved || ''; } });
	}

	/* Copy buttons */
	$(document).on('click', '[data-copy]', function () {
		var el = document.querySelector($(this).data('copy'));
		if (!el) { return; }
		el.select();
		try { navigator.clipboard.writeText(el.value); } catch (e) { document.execCommand('copy'); }
		var $b = $(this), t = $b.text();
		$b.text('✓ ' + (i18n.saved || 'Copied'));
		setTimeout(function () { $b.text(t); }, 1500);
	});

	/* Incomplete orders: inline status + note */
	function update(id, data, done) {
		$.post(cfg.ajaxUrl, $.extend({ action: 'rar_wcc_incomplete_update', nonce: cfg.nonce, id: id }, data))
			.done(function (res) { if (res && res.success) { done(res.data); } else { window.alert((res && res.data && res.data.message) || i18n.error); } })
			.fail(function () { window.alert(i18n.error); });
	}

	$(document).on('change', '.rar-wcc-status-select', function () {
		var $s = $(this);
		update($s.data('id'), { status: $s.val() }, function (d) {
			$s.attr('class', 'rar-wcc-status-select is-' + d.status + ' is-saved');
			setTimeout(function () { $s.removeClass('is-saved'); }, 1200);
		});
	});

	$(document).on('click', '.rar-wcc-note', function () {
		var $n = $(this);
		var current = $n.find('span').not('.rar-wcc-muted').text();
		var note = window.prompt(i18n.notePrompt, current);
		if (note === null) { return; }
		update($n.data('id'), { admin_note: note }, function () {
			$n.html(note ? '📝 <span></span>' : '<span class="rar-wcc-muted"></span>');
			$n.find('span').text(note || i18n.addNote);
		});
	});
})(jQuery);
