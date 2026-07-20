import {
	type AffiliateCouponStats,
	AffiliateCouponError,
} from './affiliate-coupon';

export type AffiliateUser = {
	id: number;
	name: string;
	email: string;
	username: string;
	coupon_code: string | null;
};

export type AffiliateDashboard = {
	user: AffiliateUser;
	coupon: AffiliateCouponStats | null;
};

async function readError(res: Response): Promise<string> {
	const data = await res.json().catch(() => null);
	if (data && typeof data === 'object' && typeof (data as { message?: unknown }).message === 'string') {
		return (data as { message: string }).message;
	}
	return `Request failed (${res.status})`;
}

async function postJson<T>(path: string, body: Record<string, unknown>): Promise<T> {
	const res = await fetch(path, {
		method: 'POST',
		headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
		credentials: 'include',
		body: JSON.stringify(body),
	});
	if (!res.ok) throw new AffiliateCouponError(await readError(res), res.status);
	return (await res.json()) as T;
}

export async function affiliateRegister(input: {
	name: string;
	email: string;
	password: string;
}): Promise<AffiliateDashboard> {
	const data = await postJson<{ ok: boolean; data: AffiliateDashboard }>(
		'/wp-json/wchs/v1/affiliate/register',
		input
	);
	return data.data;
}

export async function affiliateLogin(input: {
	login: string;
	password: string;
}): Promise<AffiliateDashboard> {
	const data = await postJson<{ ok: boolean; data: AffiliateDashboard }>(
		'/wp-json/wchs/v1/affiliate/login',
		input
	);
	return data.data;
}

export async function fetchAffiliateMe(): Promise<AffiliateDashboard | null> {
	const ac = new AbortController();
	const timer = setTimeout(() => ac.abort(), 10000);
	try {
		const res = await fetch('/wp-json/wchs/v1/affiliate/me', {
			method: 'GET',
			headers: { Accept: 'application/json' },
			credentials: 'include',
			signal: ac.signal,
		});
		if (res.status === 401 || res.status === 403) return null;
		if (!res.ok) throw new AffiliateCouponError(await readError(res), res.status);
		const data = (await res.json()) as { ok: boolean; data: AffiliateDashboard };
		return data.data;
	} finally {
		clearTimeout(timer);
	}
}

export async function affiliateForgotCoupon(email: string): Promise<{ code: string; codes: string[]; message: string }> {
	return postJson('/wp-json/wchs/v1/affiliate/forgot-coupon', { email });
}

export async function affiliateForgotPassword(login: string): Promise<{ message: string }> {
	return postJson('/wp-json/wchs/v1/affiliate/forgot-password', { login });
}

export async function affiliateResetPassword(): Promise<{ message: string }> {
	const res = await fetch('/wp-json/wchs/v1/affiliate/reset-password', {
		method: 'POST',
		headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
		credentials: 'include',
		body: '{}',
	});
	if (!res.ok) throw new AffiliateCouponError(await readError(res), res.status);
	return (await res.json()) as { message: string };
}

export { AffiliateCouponError, formatExpiresAt, formatMoney, type AffiliateCouponStats } from './affiliate-coupon';
export { fetchAffiliateCouponStats } from './affiliate-coupon';
