/**
 * Shared price formatter.
 *
 * All price rendering in the SPA goes through this helper so we have
 * one place that correctly handles:
 *   - non-USD minor units (JPY = 0 decimals, BHD/KWD = 3)
 *   - currency prefix/suffix layouts (e.g. "99,99 €" vs "$99.99")
 *   - empty/null values
 *
 * Callers pass a `CurrencyMeta` object that comes from whatever Store
 * API response they're rendering (`product.prices`, `cart.totals`,
 * `order.totals`). There is NO global currency state — the Store API
 * is the authoritative source per-request.
 */

export type CurrencyMeta = {
	currency_minor_unit: number;
	currency_symbol: string;
	currency_prefix?: string;
	currency_suffix?: string;
	currency_code?: string;
};

/**
 * Format a price value given in Store API "minor units". Always appends the
 * 3-letter ISO currency code when available (USD/AUD/GBP/EUR/JPY) so customers
 * — and especially those visiting cross-border — know exactly which currency
 * they're being charged in. Pass `appendCode: false` to suppress (rare; tight
 * spaces only).
 *
 *   formatPrice('2499', { currency_minor_unit: 2, currency_symbol: '$', currency_code: 'AUD' })
 *   → "$24.99 AUD"
 *
 *   formatPrice('100', { currency_minor_unit: 0, currency_symbol: '¥', currency_code: 'JPY' })
 *   → "¥100 JPY"
 *
 *   formatPrice('500', { currency_minor_unit: 3, currency_symbol: 'BD',
 *                        currency_suffix: ' BD', currency_code: 'BHD' }, false)
 *   → "0.500 BD"  (suffix already encodes the code — opt out of double display)
 *
 * Returns an empty string for null/undefined/empty inputs so templates
 * can render without explicit guards.
 */
export function formatPrice(
	minorUnits: string | number | null | undefined,
	meta: CurrencyMeta,
	appendCode = true
): string {
	if (minorUnits == null || minorUnits === '') return '';
	const minor = meta.currency_minor_unit ?? 2;
	const n = Number(minorUnits) / Math.pow(10, minor);
	if (!Number.isFinite(n)) return '';
	const body = n.toFixed(minor);
	const prefix = meta.currency_prefix ?? meta.currency_symbol ?? '';
	const suffix = meta.currency_suffix ?? '';
	const base = `${prefix}${body}${suffix}`;
	if (appendCode && meta.currency_code) {
		// Skip if the existing suffix already contains the code (e.g.
		// stores using "0.500 BHD" via currency_suffix).
		if (suffix.includes(meta.currency_code)) return base;
		return `${base} ${meta.currency_code}`;
	}
	return base;
}

/**
 * Numeric price for analytics/GA4 events (NOT formatted). GA4 expects
 * a number, not a string like "$24.99". This applies the same minor-
 * unit math as formatPrice but returns the raw number.
 *
 *   priceAsNumber('2499', { currency_minor_unit: 2 })  → 24.99
 */
export function priceAsNumber(
	minorUnits: string | number | null | undefined,
	meta: Pick<CurrencyMeta, 'currency_minor_unit'>
): number {
	if (minorUnits == null || minorUnits === '') return 0;
	const minor = meta.currency_minor_unit ?? 2;
	const n = Number(minorUnits) / Math.pow(10, minor);
	return Number.isFinite(n) ? n : 0;
}
