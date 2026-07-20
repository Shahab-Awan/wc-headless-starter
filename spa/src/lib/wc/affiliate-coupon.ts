export type AffiliateCouponStatus = 'active' | 'expired' | 'exhausted';

export type AffiliateCouponStats = {
	found: boolean;
	code: string;
	status: AffiliateCouponStatus;
	discount_type: string;
	amount: string;
	amount_label: string;
	usage_limit: number | null;
	usage_limit_per_user: number | null;
	coupon_recorded_uses: number;
	usage_remaining: number | null;
	expires_at: string | null;
	minimum_amount: string | null;
	orders_count: number;
	orders_revenue: string;
	orders_discount_total: string;
	currency: string;
	currency_symbol: string;
};

export class AffiliateCouponError extends Error {
	status: number;

	constructor(message: string, status: number) {
		super(message);
		this.name = 'AffiliateCouponError';
		this.status = status;
	}
}

export async function fetchAffiliateCouponStats(code: string): Promise<AffiliateCouponStats> {
	const res = await fetch('/wp-json/wchs/v1/affiliate-coupon', {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		credentials: 'same-origin',
		body: JSON.stringify({ code: code.trim() }),
	});

	const data = await res.json().catch(() => null);

	if (!res.ok) {
		const message =
			data && typeof data === 'object' && typeof (data as { message?: unknown }).message === 'string'
				? (data as { message: string }).message
				: `Lookup failed (${res.status})`;
		throw new AffiliateCouponError(message, res.status);
	}

	return data as AffiliateCouponStats;
}

export function formatMoney(amount: string, symbol: string, currency: string): string {
	const n = Number(amount);
	if (Number.isFinite(n)) {
		try {
			return new Intl.NumberFormat(undefined, {
				style: 'currency',
				currency: currency || 'USD',
			}).format(n);
		} catch {
			/* fall through */
		}
	}
	return `${symbol}${amount}`;
}

export function formatExpiresAt(iso: string | null): string {
	if (!iso) return 'No expiry';
	const d = new Date(iso);
	if (Number.isNaN(d.getTime())) return 'No expiry';
	return new Intl.DateTimeFormat(undefined, {
		day: 'numeric',
		month: 'short',
		year: 'numeric',
	}).format(d);
}
