(function () {
	'use strict';

	var ROW_SEL =
		'.cart_item, .wfacp_mini_cart_item, .wfacp_product_row, .wfacp_cart_item, .wfacp-product-row';

	function getProtectId() {
		var body = document.body;
		if (!body) return 0;
		var raw = body.getAttribute('data-wchs-ship-protect-id');
		return raw ? parseInt(raw, 10) : 0;
	}

	function rowProductId(row) {
		if (!row || row.nodeType !== 1) return 0;
		var direct =
			row.getAttribute('data-product_id') || row.getAttribute('data-product-id');
		if (direct) return parseInt(direct, 10) || 0;
		var inner = row.querySelector('[data-product_id], [data-product-id]');
		if (!inner) return 0;
		return (
			parseInt(
				inner.getAttribute('data-product_id') || inner.getAttribute('data-product-id'),
				10
			) || 0
		);
	}

	function isRemoveControl(el) {
		if (!el || el.nodeType !== 1) return false;
		var cls = (el.className && String(el.className)) || '';
		if (/remove|delete|wfacp_remove/i.test(cls)) return true;
		if (el.getAttribute('aria-label') && /remove/i.test(el.getAttribute('aria-label'))) {
			return true;
		}
		return false;
	}

	function lockRow(row) {
		if (!row || row.getAttribute('data-wchs-ship-protect-locked') === '1') return;
		row.setAttribute('data-wchs-ship-protect-locked', '1');

		row.querySelectorAll(
			'.quantity .minus, .quantity .plus, .qty-minus, .qty-plus, .wfacp_minus, .wfacp_plus'
		).forEach(function (btn) {
			if (isRemoveControl(btn)) return;
			btn.style.display = 'none';
			btn.disabled = true;
		});

		row.querySelectorAll(
			'.quantity button:not([class*="remove"]):not(.wfacp_remove_item), .wfacp_qty_btn'
		).forEach(function (btn) {
			if (isRemoveControl(btn)) return;
			btn.style.display = 'none';
			btn.disabled = true;
		});

		row.querySelectorAll('input.qty, input[type="number"]').forEach(function (input) {
			input.readOnly = true;
			input.tabIndex = -1;
		});
	}

	function collectProtectionRows(protectId) {
		var rows = [];
		var seen = new Set();

		function add(row) {
			if (!row || seen.has(row)) return;
			seen.add(row);
			rows.push(row);
		}

		document.querySelectorAll('.wchs-shipping-protection-line').forEach(function (row) {
			if (row.matches(ROW_SEL)) add(row);
		});

		if (!protectId) return rows;

		document.querySelectorAll(ROW_SEL).forEach(function (row) {
			if (rowProductId(row) === protectId) add(row);
		});

		return rows;
	}

	function lockAll() {
		var protectId = getProtectId();
		collectProtectionRows(protectId).forEach(lockRow);
	}

	function boot() {
		lockAll();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}

	if (typeof jQuery !== 'undefined') {
		jQuery(document.body).on('updated_checkout updated_wc_div wfacp_updated_checkout', lockAll);
	}
})();
