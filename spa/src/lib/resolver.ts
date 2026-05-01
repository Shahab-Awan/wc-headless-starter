/**
 * Client-side mirror of wp/mu-plugins/wchs-admin/class-resolver-service.php.
 *
 * Merges site defaults + per-module overrides into each module's `resolved`
 * block, so live-preview changes (accent, typography) re-propagate to
 * modules without a REST round-trip.
 *
 * Keep in sync with ResolverService::deep_merge + ResolverService::site_defaults.
 */

type TokenLayer = Record<string, unknown>;

export type SiteDefaults = {
	accent_color: string | null;
	typography: {
		heading_font: string;
		heading_weight: string;
		body_font: string;
		body_size: string;
	};
};

export type ModuleResolved = {
	accent_color?: string | null;
	typography?: Partial<SiteDefaults['typography']>;
};

function deepMerge(...layers: TokenLayer[]): TokenLayer {
	const out: TokenLayer = {};
	for (const layer of layers) {
		if (!layer) continue;
		for (const [key, value] of Object.entries(layer)) {
			if (value == null) continue;
			const existing = out[key];
			if (
				typeof value === 'object' && !Array.isArray(value) &&
				typeof existing === 'object' && existing !== null && !Array.isArray(existing)
			) {
				out[key] = deepMerge(existing as TokenLayer, value as TokenLayer);
			} else {
				out[key] = value;
			}
		}
	}
	return out;
}

function sourceMap(
	defaults: TokenLayer,
	pageOv: TokenLayer,
	moduleOv: TokenLayer,
): Record<string, 'default' | 'page' | 'module'> {
	const out: Record<string, 'default' | 'page' | 'module'> = {};
	const walk = (layer: TokenLayer, source: 'default' | 'page' | 'module', prefix = '') => {
		if (!layer) return;
		for (const [key, value] of Object.entries(layer)) {
			const path = prefix ? `${prefix}.${key}` : key;
			if (value == null) continue;
			if (typeof value === 'object' && !Array.isArray(value)) {
				walk(value as TokenLayer, source, path);
			} else {
				out[path] = source;
			}
		}
	};
	walk(defaults, 'default');
	walk(pageOv, 'page');
	walk(moduleOv, 'module');
	return out;
}

export function siteDefaults(config: {
	accent_color?: string | null;
	typography: SiteDefaults['typography'];
}): SiteDefaults {
	return {
		accent_color: config.accent_color ?? null,
		typography: {
			heading_font: config.typography.heading_font,
			heading_weight: config.typography.heading_weight,
			body_font: config.typography.body_font,
			body_size: config.typography.body_size,
		},
	};
}

/**
 * Attach `resolved` + `inherited` to each module in `modules`, based on
 * current site defaults + each module's own `overrides`. Mutates shallowly
 * (returns the same module objects with two keys added) — callers should
 * wrap with a spread if they want to force reactivity on a fresh array.
 */
export function resolveModules<M extends { overrides?: ModuleResolved }>(
	modules: M[],
	defaults: SiteDefaults,
	pageOverrides: ModuleResolved = {},
): Array<M & { resolved: TokenLayer; inherited: Record<string, string> }> {
	return modules.map((m) => {
		const overrides = (m.overrides ?? {}) as TokenLayer;
		const defaultsLayer = defaults as unknown as TokenLayer;
		const pageLayer = pageOverrides as TokenLayer;
		const resolved = deepMerge(defaultsLayer, pageLayer, overrides);
		const inherited = sourceMap(defaultsLayer, pageLayer, overrides);
		return Object.assign({}, m, { resolved, inherited });
	});
}
