/** Tiered shipping protection fees (major units). Subtotal excludes the protection line. */

export type ShippingProtectionTier = {
	up_to: number | null;
	fee: number;
};

export const SHIPPING_PROTECTION_TIERS: ShippingProtectionTier[] = [
	{ up_to: 100, fee: 8 },
	{ up_to: 300, fee: 12 },
	{ up_to: null, fee: 16 }
];

export function shippingProtectionFeeMajor(subtotalMajor: number): number {
	const sub = Math.max(0, subtotalMajor);
	for (const tier of SHIPPING_PROTECTION_TIERS) {
		if (tier.up_to === null || sub < tier.up_to) return tier.fee;
	}
	return SHIPPING_PROTECTION_TIERS[SHIPPING_PROTECTION_TIERS.length - 1]?.fee ?? 16;
}

export function shippingProtectionTierIndex(subtotalMajor: number): number {
	const sub = Math.max(0, subtotalMajor);
	for (let i = 0; i < SHIPPING_PROTECTION_TIERS.length; i++) {
		const tier = SHIPPING_PROTECTION_TIERS[i];
		if (tier.up_to === null || sub < tier.up_to) return i;
	}
	return SHIPPING_PROTECTION_TIERS.length - 1;
}

export function shippingProtectionTierSummary(): string {
	return 'Under $100: $8 · $100–$299: $12 · $300+: $16';
}
