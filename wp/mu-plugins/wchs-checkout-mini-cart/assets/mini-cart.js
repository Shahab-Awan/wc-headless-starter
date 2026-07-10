/**
 * WCHS checkout mini-cart — remove / coupon / qty AJAX + sync shipping after FunnelKit update_checkout.
 */
(function () {
	'use strict';

	if (window.__wchsMiniCartBound) return;
	window.__wchsMiniCartBound = true;

	var cfg = window.wchsMiniCart || {};
	if (!cfg.ajaxUrl || !cfg.nonce) return;

	var refreshTimer = null;
	var refreshing = false;

	function rootFrom(el) {
		return el && el.closest ? el.closest('[data-wchs-mini-cart]') : null;
	}

	function activeRoot() {
		return document.querySelector('[data-wchs-mini-cart]');
	}

	function setBusy(root, on) {
		if (!root) return;
		root.classList.toggle('is-busy', !!on);
		var overlay = root.querySelector('.wchs-mini-cart__busy');
		if (overlay) overlay.hidden = !on;
	}

	function triggerCheckoutRefresh() {
		try {
			if (window.jQuery) {
				// FunnelKit/ShipStation rates land on updated_checkout — refresh us then.
				window.jQuery(document.body).trigger('update_checkout');
				window.jQuery(document.body).trigger('wc_fragment_refresh');
			}
			document.body.dispatchEvent(new CustomEvent('wchs_mini_cart_updated', { bubbles: true }));
		} catch (_) {
			/* ignore */
		}
	}

	function replaceRoot(root, html) {
		var wrap = document.createElement('div');
		wrap.innerHTML = html.trim();
		var next = wrap.querySelector('[data-wchs-mini-cart]') || wrap.firstElementChild;
		if (!next || !root.parentNode) return root;
		root.parentNode.replaceChild(next, root);
		return next;
	}

	function post(action, fields) {
		var body = new URLSearchParams();
		body.set('action', action);
		body.set('nonce', cfg.nonce);
		Object.keys(fields || {}).forEach(function (k) {
			body.set(k, fields[k]);
		});
		return fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		}).then(function (res) {
			return res.json().then(function (json) {
				if (!res.ok || !json || !json.success) {
					var msg =
						(json && json.data && json.data.message) ||
						'Something went wrong. Please try again.';
					throw new Error(msg);
				}
				return json.data;
			});
		});
	}

	function refreshFromServer() {
		var root = activeRoot();
		if (!root || refreshing) return;
		var title = root.getAttribute('data-title') || 'Your Cart';
		refreshing = true;
		post('wchs_mini_cart_refresh', { title: title })
			.then(function (data) {
				root = activeRoot() || root;
				if (data && data.html) {
					replaceRoot(root, data.html);
				}
			})
			.catch(function () {
				/* keep current totals */
			})
			.then(function () {
				refreshing = false;
			});
	}

	function scheduleRefreshFromCheckout() {
		if (refreshTimer) clearTimeout(refreshTimer);
		refreshTimer = setTimeout(function () {
			refreshTimer = null;
			refreshFromServer();
		}, 50);
	}

	function run(root, action, fields) {
		if (!root || root.classList.contains('is-busy')) return;
		var title = root.getAttribute('data-title') || 'Your Cart';
		fields = fields || {};
		fields.title = title;
		setBusy(root, true);
		post(action, fields)
			.then(function (data) {
				if (data && data.html) {
					replaceRoot(root, data.html);
				}
				// Shipping rates update after FunnelKit recalculates — second paint via updated_checkout.
				triggerCheckoutRefresh();
			})
			.catch(function (err) {
				setBusy(root, false);
				var msgEl = root.querySelector('[data-coupon-msg]');
				if (msgEl) {
					msgEl.hidden = false;
					msgEl.classList.add('is-error');
					msgEl.textContent = err.message || 'Request failed.';
				} else {
					window.alert(err.message || 'Request failed.');
				}
			});
	}

	document.addEventListener('click', function (e) {
		var btn = e.target && e.target.closest ? e.target.closest('[data-action]') : null;
		if (!btn) return;
		var root = rootFrom(btn);
		if (!root) return;

		var action = btn.getAttribute('data-action');
		if (action === 'remove') {
			e.preventDefault();
			run(root, 'wchs_mini_cart_remove', { key: btn.getAttribute('data-key') || '' });
			return;
		}
		if (action === 'remove-coupon') {
			e.preventDefault();
			run(root, 'wchs_mini_cart_remove_coupon', { code: btn.getAttribute('data-code') || '' });
			return;
		}
		if (action === 'qty-inc' || action === 'qty-dec') {
			e.preventDefault();
			var key = btn.getAttribute('data-key') || '';
			var current = parseInt(btn.getAttribute('data-qty') || '1', 10);
			if (!key || !Number.isFinite(current)) return;
			var next = action === 'qty-inc' ? current + 1 : current - 1;
			if (next < 1) {
				run(root, 'wchs_mini_cart_remove', { key: key });
			} else {
				run(root, 'wchs_mini_cart_update_qty', { key: key, qty: String(next) });
			}
		}
	});

	document.addEventListener('submit', function (e) {
		var form = e.target && e.target.closest ? e.target.closest('[data-coupon-form]') : null;
		if (!form) return;
		var root = rootFrom(form);
		if (!root) return;
		e.preventDefault();
		var input = form.querySelector('input[name="coupon_code"]');
		var code = input ? String(input.value || '').trim() : '';
		var msgEl = root.querySelector('[data-coupon-msg]');
		if (msgEl) {
			msgEl.hidden = true;
			msgEl.textContent = '';
		}
		if (!code) {
			if (msgEl) {
				msgEl.hidden = false;
				msgEl.classList.add('is-error');
				msgEl.textContent = 'Enter a coupon code.';
			}
			return;
		}
		run(root, 'wchs_mini_cart_apply_coupon', { code: code });
	});

	// When address / ShipStation rates change in FunnelKit, pull fresh shipping + total.
	if (window.jQuery) {
		window.jQuery(document.body).on('updated_checkout', scheduleRefreshFromCheckout);
	}
})();
