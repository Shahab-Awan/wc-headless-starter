/**
 * Shadow cart — localStorage mirror of active cart line items.
 *
 * WHY
 *   The Store API Cart-Token JWT expires after 48h (sliding). When the
 *   SPA sends an expired token, WC does NOT return 401 — it silently
 *   issues a fresh empty cart with a new token. Without this shadow,
 *   users would lose their cart after 48h with no warning and no way
 *   to recover.
 *
 *   On every successful cart mutation we mirror the line items here.
 *   On every cart fetch we compare: if the shadow has items but the
 *   fetched cart is empty, we replay the adds against the new cart.
 *
 * SCHEMA
 *   Single JSON object in localStorage key `wchs_shadow_cart_v1`:
 *
 *   {
 *     version: 1,
 *     updatedAt: number,  // ms since epoch
 *     items: [{ id, quantity, variation?: [{attribute, value}] }]
 *   }
 *
 *   Version bump forces reset. Any parse error = treat as empty.
 *
 * SECURITY
 *   localStorage is origin-scoped, same-origin only, not sent on
 *   requests. Shadow contents are not secret (they're just product IDs
 *   + quantities + attribute selections). No tokens, no PII.
 */

export const SHADOW_CART_KEY = 'wchs_shadow_cart_v1';
const KEY = SHADOW_CART_KEY;
const VERSION = 1;
const MAX_AGE_MS = 14 * 24 * 60 * 60 * 1000; // 14 days — wider than JWT TTL

export type ShadowItem = {
	id: number;
	quantity: number;
	variation?: { attribute: string; value: string }[];
};

type ShadowSnapshot = {
	version: number;
	updatedAt: number;
	items: ShadowItem[];
};

function emptySnapshot(): ShadowSnapshot {
	return { version: VERSION, updatedAt: Date.now(), items: [] };
}

export function readShadow(): ShadowSnapshot {
	if (typeof localStorage === 'undefined') return emptySnapshot();
	try {
		const raw = localStorage.getItem(KEY);
		if (!raw) return emptySnapshot();
		const parsed = JSON.parse(raw) as ShadowSnapshot;
		if (!parsed || parsed.version !== VERSION || !Array.isArray(parsed.items)) {
			return emptySnapshot();
		}
		// Discard shadows older than MAX_AGE_MS
		if (typeof parsed.updatedAt !== 'number' || Date.now() - parsed.updatedAt > MAX_AGE_MS) {
			return emptySnapshot();
		}
		// Sanitize each item — only keep known fields, coerce types
		parsed.items = parsed.items
			.map((it) => sanitizeItem(it))
			.filter((it): it is ShadowItem => it !== null);
		return parsed;
	} catch {
		return emptySnapshot();
	}
}

function sanitizeItem(raw: unknown): ShadowItem | null {
	if (!raw || typeof raw !== 'object') return null;
	const obj = raw as Record<string, unknown>;
	const id = Number(obj.id);
	const quantity = Number(obj.quantity);
	if (!Number.isInteger(id) || id <= 0) return null;
	if (!Number.isInteger(quantity) || quantity <= 0 || quantity > 9999) return null;

	const clean: ShadowItem = { id, quantity };

	if (Array.isArray(obj.variation)) {
		const v: { attribute: string; value: string }[] = [];
		for (const entry of obj.variation) {
			if (!entry || typeof entry !== 'object') continue;
			const e = entry as Record<string, unknown>;
			if (typeof e.attribute === 'string' && typeof e.value === 'string') {
				// Cap lengths
				v.push({
					attribute: e.attribute.slice(0, 200),
					value: e.value.slice(0, 200)
				});
			}
		}
		if (v.length) clean.variation = v;
	}

	return clean;
}

export function writeShadow(items: ShadowItem[]): void {
	if (typeof localStorage === 'undefined') return;
	const snap: ShadowSnapshot = {
		version: VERSION,
		updatedAt: Date.now(),
		items: items.map((it) => sanitizeItem(it)).filter((it): it is ShadowItem => it !== null)
	};
	try {
		localStorage.setItem(KEY, JSON.stringify(snap));
	} catch {
		// localStorage quota exceeded / disabled — fail silently
	}
}

export function clearShadow(): void {
	if (typeof localStorage === 'undefined') return;
	try {
		localStorage.removeItem(KEY);
	} catch {
		// ignore
	}
}

/**
 * Diff shadow vs active cart. Returns items present in shadow that are
 * missing from the active cart (by id + variation signature). Used to
 * detect silent token expiry.
 */
export function itemsMissingFromActive(
	shadow: ShadowItem[],
	active: { id: number; variation?: { attribute: string; value: string }[] }[]
): ShadowItem[] {
	function sig(it: { id: number; variation?: { attribute: string; value: string }[] }): string {
		const parts: string[] = [String(it.id)];
		if (it.variation && it.variation.length) {
			parts.push(
				...it.variation
					.slice()
					.sort((a, b) => a.attribute.localeCompare(b.attribute))
					.map((v) => `${v.attribute}=${v.value}`)
			);
		}
		return parts.join('|');
	}
	const activeSigs = new Set(active.map(sig));
	return shadow.filter((s) => !activeSigs.has(sig(s)));
}
