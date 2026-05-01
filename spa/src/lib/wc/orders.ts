import { request } from './store-api';

/** Store API order response shape (summary). */
export type StoreOrder = {
	id: number;
	status: string;
	order_key: string;
	needs_payment: boolean;
	needs_shipping: boolean;
	items: {
		id: number;
		quantity: number;
		name: string;
		images: { src: string; thumbnail: string; alt: string }[];
		totals: { line_total: string; line_total_tax: string };
	}[];
	totals: {
		total_items: string;
		total_shipping: string;
		total_tax: string;
		total_discount: string;
		total_fees: string;
		total_price: string;
		currency_code: string;
		currency_symbol: string;
		currency_minor_unit: number;
		currency_prefix?: string;
		currency_suffix?: string;
	};
	billing_address: {
		first_name: string;
		last_name: string;
		address_1: string;
		address_2: string;
		city: string;
		state: string;
		postcode: string;
		country: string;
		email: string;
		phone: string;
	};
	shipping_address: {
		first_name: string;
		last_name: string;
		address_1: string;
		address_2: string;
		city: string;
		state: string;
		postcode: string;
		country: string;
	};
};

/**
 * Fetch an order from the Store API. Requires the order_key + (for guests)
 * billing_email. For logged-in users the cookie session authorizes.
 *
 * We deliberately SKIP the Cart-Token header — the order endpoint
 * authenticates via key+email, and passing a cart token for a guest
 * session confuses WC's validation path ("Invalid billing email provided").
 */
export async function getOrder(id: number, key: string, email?: string): Promise<StoreOrder> {
	const query: Record<string, string> = { key };
	if (email) query.billing_email = email;
	return request<StoreOrder>(`/order/${id}`, { query, skipCartToken: true });
}

/** Our custom endpoint: list current user's orders. Requires logged-in cookie. */
export type MyOrdersResponse = {
	orders: {
		id: number;
		number: string;
		status: string;
		date_created: string | null;
		total: string;
		currency: string;
		item_count: number;
		order_key: string;
		billing_email: string;
	}[];
	page: number;
	per_page: number;
	total_pages: number;
	total_orders: number;
};

/** Payment instructions returned by /wchs/v1/order-payment/{id}. */
export type OrderFee = {
	name: string;
	total: string;
};

export type OrderPaymentInfo = {
	method: string;
	method_title: string;
	status: string;
	fees: OrderFee[];
	instructions: null | {
		type: 'bacs';
		message: string;
		accounts: { account_name?: string; account_number?: string; bank_name?: string; sort_code?: string; iban?: string; bic?: string }[];
	} | {
		type: 'cod';
		message: string;
	} | {
		type: 'offline';
		message: string | null;
		handle: string | null;
		link: string | null;
		show_qr: boolean;
		total: string;
	};
};

export async function getOrderPayment(id: number, key: string): Promise<OrderPaymentInfo | null> {
	try {
		const url = `/wp-json/wchs/v1/order-payment/${id}?key=${encodeURIComponent(key)}`;
		const res = await fetch(url, { credentials: 'include', headers: { Accept: 'application/json' } });
		if (!res.ok) return null;
		return res.json();
	} catch {
		return null;
	}
}

export async function listMyOrders(page = 1, perPage = 10): Promise<MyOrdersResponse | null> {
	// This endpoint lives outside the Store API base, so use a raw fetch
	// with the same credentialed options.
	const url = `/wp-json/wchs/v1/my-orders?per_page=${perPage}&page=${page}`;
	const res = await fetch(url, { credentials: 'include', headers: { Accept: 'application/json' } });
	if (res.status === 401) return null;
	if (!res.ok) throw new Error(`my-orders failed: ${res.status}`);
	return res.json();
}
