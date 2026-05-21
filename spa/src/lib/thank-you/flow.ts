import type { Page } from '@sveltejs/kit';
import type { StoreOrder } from '$lib/wc/orders';
import {
	trackPurchase,
	trackOmnisendPlacedOrder,
	trackKlaviyoPlacedOrder,
	identifyKlaviyoContact,
	trackMetaPurchase,
	trackTikTokCompletePayment,
	identifyTikTokContact,
	trackPinterestCheckout,
	trackGoogleAdsConversion,
	identifyClarityContact,
} from '$lib/analytics';
import { config } from '$lib/config.svelte';

export const ORDER_RECEIVED_STORAGE_KEY = 'wchs_order_received';

export type OrderReference = {
	id: number | null;
	key: string | null;
	email: string | null;
	ref: string;
};

export function readOrderReference(page: Page): OrderReference {
	let id: number | null = null;
	let key: string | null = null;
	let email: string | null = null;

	const u = page.url.searchParams;
	const urlId = u.get('id');
	const urlKey = u.get('key');
	const urlEmail = u.get('email');

	if (urlId && urlKey) {
		id = parseInt(urlId, 10);
		key = urlKey;
		email = urlEmail;
		try {
			sessionStorage.setItem(
				ORDER_RECEIVED_STORAGE_KEY,
				JSON.stringify({ id, key, email }),
			);
		} catch {
			/* private mode */
		}
		history.replaceState(null, '', page.url.pathname);
	} else {
		try {
			const stashed = sessionStorage.getItem(ORDER_RECEIVED_STORAGE_KEY);
			if (stashed) {
				const parsed = JSON.parse(stashed);
				id = parsed.id;
				key = parsed.key;
				email = parsed.email;
			}
		} catch {
			/* private mode */
		}
	}

	return {
		id,
		key,
		email,
		ref: id && key ? `${id}:${key}:${email ?? ''}` : '',
	};
}

export function fireThankYouPurchaseEvents(order: StoreOrder): void {
	const fire = () => {
		trackPurchase(order);
		trackOmnisendPlacedOrder(order);
		trackKlaviyoPlacedOrder(order);
		trackMetaPurchase(order);
		trackTikTokCompletePayment(order);
		trackPinterestCheckout(order);
		if (config.data.google_ads_conversion_id && config.data.google_ads_conversion_label) {
			trackGoogleAdsConversion(
				order,
				config.data.google_ads_conversion_id,
				config.data.google_ads_conversion_label,
			);
		}
		const billingEmail = order.billing_address?.email;
		if (billingEmail) {
			identifyKlaviyoContact(billingEmail, {
				first_name: order.billing_address?.first_name,
				last_name: order.billing_address?.last_name,
			});
			identifyTikTokContact(billingEmail);
			identifyClarityContact(billingEmail);
		}
	};

	try {
		const firedKey = `wchs_purchase_fired_${order.id}`;
		if (!sessionStorage.getItem(firedKey)) {
			fire();
			sessionStorage.setItem(firedKey, '1');
		}
	} catch {
		fire();
	}
}

