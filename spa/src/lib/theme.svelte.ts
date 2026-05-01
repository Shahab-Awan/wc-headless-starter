/**
 * Theme store — light/dark with system preference fallback.
 *
 * Behavior:
 *   - On first load, respect `prefers-color-scheme` unless the user has
 *     explicitly chosen a theme (persisted in localStorage).
 *   - On toggle, flip + persist.
 *   - Applied as `data-theme="light|dark"` on <html>.
 *   - The +layout.svelte runs `init()` on mount; server-rendered HTML does
 *     NOT set a theme, so there's a tiny flash on first hydration. The
 *     inline head script in app.html prevents that by reading localStorage
 *     synchronously before any CSS paints.
 */

const KEY = 'wchs_theme';

export type Theme = 'light' | 'dark';
export type ThemeDefault = 'system' | 'light' | 'dark';

function systemPref(): Theme {
	if (typeof window === 'undefined') return 'light';
	return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

/**
 * Resolve an admin-configured default to a concrete light/dark value.
 * 'system' → honor prefers-color-scheme. 'light'/'dark' → take literally.
 */
function resolveDefault(def: ThemeDefault): Theme {
	if (def === 'light' || def === 'dark') return def;
	return systemPref();
}

/**
 * Read a cookie value. Cookies are scoped by hostname (ports are ignored),
 * so a cookie set at localhost:5175 is visible at localhost:8099 — this
 * is what lets us sync the theme across the SPA and native WP pages in dev.
 */
function readCookie(name: string): string | null {
	if (typeof document === 'undefined') return null;
	const match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
	return match ? decodeURIComponent(match[1]) : null;
}

function writeCookie(name: string, value: string) {
	if (typeof document === 'undefined') return;
	// 1-year lifetime, path=/, samesite=lax for cross-site nav safety
	const maxAge = 60 * 60 * 24 * 365;
	document.cookie = `${name}=${encodeURIComponent(value)}; path=/; max-age=${maxAge}; samesite=lax`;
}

function read(): Theme | null {
	// Cookie first — cross-port (same-hostname) sync with native WP pages
	const fromCookie = readCookie(KEY);
	if (fromCookie === 'light' || fromCookie === 'dark') return fromCookie;
	// localStorage fallback — for same-origin SPA-only sessions
	if (typeof localStorage === 'undefined') return null;
	const v = localStorage.getItem(KEY);
	return v === 'light' || v === 'dark' ? v : null;
}

function apply(theme: Theme) {
	if (typeof document === 'undefined') return;
	document.documentElement.setAttribute('data-theme', theme);
	document.documentElement.style.colorScheme = theme;
}

class ThemeStore {
	current = $state<Theme>('light');
	explicit = $state(false); // true if user has clicked toggle
	siteDefault = $state<ThemeDefault>('system');
	/**
	 * Preview-mode override set from the admin canvas toolbar theme toggle.
	 * When set, the admin has forced a specific theme and subsequent config
	 * updates (e.g. `patch.theme_default` in config.initPreviewMode) must not
	 * clobber it. Non-null only inside preview iframes — NEVER persisted to
	 * cookie/localStorage so a visitor's real preference is untouched.
	 */
	previewOverride = $state<Theme | null>(null);

	/**
	 * @param siteDefault - optional admin-configured default. Only applied
	 *   when the visitor has no explicit preference in cookie/localStorage.
	 *   Safe to call multiple times — once during early boot (pre-config, uses
	 *   'system') and again after /wchs/v1/config lands with the real value.
	 */
	init(siteDefault: ThemeDefault = 'system') {
		this.siteDefault = siteDefault;
		// Preview mode: URL `?theme=light|dark` wins over stored prefs so the
		// admin's forced theme survives hydration. Only active when ?preview=1.
		if (typeof window !== 'undefined') {
			const sp = new URLSearchParams(window.location.search);
			if (sp.has('preview')) {
				const u = sp.get('theme');
				if (u === 'light' || u === 'dark') {
					this.setPreviewOverride(u);
					return;
				}
			}
		}
		const stored = read();
		if (stored) {
			this.current = stored;
			this.explicit = true;
		} else {
			this.current = resolveDefault(siteDefault);
		}
		apply(this.current);

		// Live-update if user is following system and changes OS setting.
		// Only fires when (a) no explicit user pref AND (b) site default is
		// 'system' — if the admin forced light/dark we ignore OS changes.
		if (typeof window !== 'undefined') {
			const mq = window.matchMedia('(prefers-color-scheme: dark)');
			mq.addEventListener('change', (e) => {
				if (!this.explicit && this.siteDefault === 'system') {
					this.current = e.matches ? 'dark' : 'light';
					apply(this.current);
				}
			});
		}
	}

	/** Re-resolve when site default arrives from /wchs/v1/config. Called by layout. */
	applySiteDefault(siteDefault: ThemeDefault) {
		this.siteDefault = siteDefault;
		if (this.explicit) return; // user-selected wins, no change
		if (this.previewOverride) return; // preview-forced theme wins
		const next = resolveDefault(siteDefault);
		if (next !== this.current) {
			this.current = next;
			apply(this.current);
		}
	}

	/**
	 * Admin preview toolbar forced a specific theme. Applies immediately,
	 * flags explicit so applySiteDefault stays out of the way, and sets
	 * previewOverride so config.initPreviewMode's theme_default branch
	 * defers until the admin clears it (e.g. by picking a different button).
	 */
	setPreviewOverride(t: Theme) {
		this.previewOverride = t;
		this.current = t;
		this.explicit = true;
		apply(t);
	}

	toggle() {
		this.current = this.current === 'dark' ? 'light' : 'dark';
		this.explicit = true;
		apply(this.current);
		if (typeof localStorage !== 'undefined') {
			localStorage.setItem(KEY, this.current);
		}
		writeCookie(KEY, this.current);
	}

	clearPreference() {
		this.explicit = false;
		if (typeof localStorage !== 'undefined') localStorage.removeItem(KEY);
		if (typeof document !== 'undefined') {
			document.cookie = KEY + '=; path=/; max-age=0; samesite=lax';
		}
		this.current = systemPref();
		apply(this.current);
	}
}

export const theme = new ThemeStore();
