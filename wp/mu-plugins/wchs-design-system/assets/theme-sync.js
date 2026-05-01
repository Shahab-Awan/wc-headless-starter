/**
 * wchs-design-system / theme-sync.js
 *
 * Runs in the footer of every native WP page. Wires the floating
 * theme toggle button's click handler and keeps the theme in sync
 * across the SPA and the native WP pages.
 *
 * Cross-origin sync: localStorage is scoped per-origin (localhost:5175
 * and localhost:8099 are DIFFERENT origins), so we use a cookie as
 * the primary source of truth. Cookies are scoped per-hostname — ports
 * are ignored — so a cookie set at one port is visible at any other
 * port on the same hostname.
 *
 * The critical "set data-theme before first paint" work is done by an
 * inline <script> in wp_head printed by ThemeSync.php. This footer
 * script handles interactive wiring (click handler, cross-tab sync).
 */

(function () {
	var STORAGE_KEY = 'wchs_theme';
	var COOKIE_MAX_AGE = 60 * 60 * 24 * 365; // 1 year

	function readCookie(name) {
		try {
			var m = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
			return m ? decodeURIComponent(m[1]) : null;
		} catch (e) {
			return null;
		}
	}

	function writeCookie(name, value) {
		try {
			document.cookie =
				name + '=' + encodeURIComponent(value) +
				'; path=/; max-age=' + COOKIE_MAX_AGE + '; samesite=lax';
		} catch (e) {}
	}

	function readTheme() {
		var fromCookie = readCookie(STORAGE_KEY);
		if (fromCookie === 'light' || fromCookie === 'dark') return fromCookie;
		try {
			var stored = localStorage.getItem(STORAGE_KEY);
			if (stored === 'light' || stored === 'dark') return stored;
		} catch (e) {}
		if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
			return 'dark';
		}
		return 'light';
	}

	function apply(theme) {
		document.documentElement.setAttribute('data-theme', theme);
		document.documentElement.style.colorScheme = theme;
	}

	function persist(theme) {
		try { localStorage.setItem(STORAGE_KEY, theme); } catch (e) {}
		writeCookie(STORAGE_KEY, theme);
	}

	function toggle() {
		var current = document.documentElement.getAttribute('data-theme') || readTheme();
		var next = current === 'dark' ? 'light' : 'dark';
		apply(next);
		persist(next);
		try {
			document.dispatchEvent(new CustomEvent('wchs:theme-change', { detail: { theme: next } }));
		} catch (e) {}
	}

	window.wchsTheme = {
		toggle: toggle,
		get: function () {
			return document.documentElement.getAttribute('data-theme') || readTheme();
		},
		set: function (t) {
			if (t !== 'light' && t !== 'dark') return;
			apply(t);
			persist(t);
		}
	};

	function wire() {
		var btn = document.getElementById('wchs-theme-toggle');
		if (btn && !btn.dataset.wchsWired) {
			btn.dataset.wchsWired = '1';
			btn.addEventListener('click', toggle);
		}

		var burger = document.querySelector('.site-header__burger');
		var menu = document.querySelector('.mobile-menu');
		if (burger && menu && !burger.dataset.wchsWired) {
			burger.dataset.wchsWired = '1';
			burger.addEventListener('click', function () {
				var open = menu.hidden;
				menu.hidden = !open;
				burger.setAttribute('aria-expanded', open ? 'true' : 'false');
			});
		}

		// FK offer page: hide middle buy-blocks (keep first + last).
		// CSS :nth-of-type can't target by class, so we do it in JS.
		var buyBlocks = document.querySelectorAll('.wfocu-buy-block');
		if (buyBlocks.length > 2) {
			for (var i = 1; i < buyBlocks.length - 1; i++) {
				buyBlocks[i].style.display = 'none';
			}
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', wire);
	} else {
		wire();
	}

	window.addEventListener('storage', function (e) {
		if (e.key !== STORAGE_KEY) return;
		if (e.newValue === 'light' || e.newValue === 'dark') {
			apply(e.newValue);
			writeCookie(STORAGE_KEY, e.newValue);
		}
	});
})();
