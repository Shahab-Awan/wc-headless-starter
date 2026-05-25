(function () {
	'use strict';

	var cfg = typeof wchsProtectedShipping === 'object' ? wchsProtectedShipping : null;
	if (!cfg) return;

	function hideStrayProtectionProductRows() {
		document.querySelectorAll('.wfacp_mini_cart_items .cart_item, .wfacp_mini_cart_item').forEach(function (row) {
			var link = row.querySelector('a[href*="shipping-protection"]');
			var name = row.querySelector('.wfacp_product_name, .product-name, .product-name__text');
			var text = name ? (name.textContent || '') : '';
			if (link || /shipping\s*protection/i.test(text)) {
				row.style.display = 'none';
				row.setAttribute('data-wchs-hidden-ship-product', '1');
			}
		});
	}

	function removeInjected() {
		document.querySelectorAll('.wchs-protected-shipping-injected').forEach(function (el) {
			el.remove();
		});
	}

	function syncPriceFromDom() {
		var cell = document.querySelector('.wchs-protected-shipping-row__total');
		if (cell && cell.innerHTML) {
			cfg.priceHtml = cell.innerHTML;
		}
	}

	function injectFunnelKitRow() {
		removeInjected();
		if (!cfg.enabled || !cfg.priceHtml) {
			return;
		}
		var host =
			document.querySelector('.wfacp_mini_cart_items') ||
			document.querySelector('.wfacp_order_summary .wfacp_mini_cart_items');
		if (!host) {
			return;
		}
		var row = document.createElement('div');
		row.className = 'wchs-protected-shipping-injected wfacp_mini_cart_item';
		row.innerHTML =
			'<div class="wchs-protected-shipping-injected__body">' +
			'<span class="wchs-protected-shipping-injected__name"></span>' +
			'</div>' +
			'<span class="wchs-protected-shipping-injected__price"></span>' +
			'<button type="button" class="wchs-protected-shipping-injected__remove" data-wchs-protected-shipping-remove aria-label="Remove protected shipping">×</button>';
		row.querySelector('.wchs-protected-shipping-injected__name').textContent = cfg.label || 'Protected Shipping';
		row.querySelector('.wchs-protected-shipping-injected__price').innerHTML = cfg.priceHtml;
		host.appendChild(row);
	}

	function declineProtectedShipping() {
		var body = new URLSearchParams();
		body.set('action', 'wchs_remove_protected_shipping');
		body.set('_wpnonce', cfg.nonce || '');
		return fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		}).then(function () {
			cfg.enabled = false;
			cfg.priceHtml = '';
			removeInjected();
			hideStrayProtectionProductRows();
			if (typeof jQuery !== 'undefined') {
				jQuery(document.body).trigger('update_checkout');
			}
		});
	}

	function bindRemove() {
		document.querySelectorAll('[data-wchs-protected-shipping-remove]').forEach(function (btn) {
			if (btn.getAttribute('data-wchs-bound') === '1') return;
			btn.setAttribute('data-wchs-bound', '1');
			btn.addEventListener('click', function (e) {
				e.preventDefault();
				btn.disabled = true;
				declineProtectedShipping().finally(function () {
					btn.disabled = false;
				});
			});
		});
	}

	function refresh() {
		hideStrayProtectionProductRows();
		syncPriceFromDom();
		injectFunnelKitRow();
		bindRemove();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', refresh);
	} else {
		refresh();
	}

	if (typeof jQuery !== 'undefined') {
		jQuery(document.body).on('updated_checkout wfacp_updated_checkout', refresh);
	}
})();
