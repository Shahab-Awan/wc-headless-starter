import type { StoreProduct } from '$lib/wc/products';

export const MOST_POPULAR_MATCHERS = [
	{ slug: 'bpc-157', name: 'bpc-157' },
	{ slug: 'retatrutide', name: 'retatrutide' },
	{ slug: 'ghk-cu', name: 'ghk-cu' },
] as const;

function normalizeKey(value: string): string {
	return value.toLowerCase().replace(/[^a-z0-9]+/g, '');
}

export function pickPopularProducts(products: StoreProduct[], limit = 3): StoreProduct[] {
	const out: StoreProduct[] = [];
	const used = new Set<number>();
	for (const matcher of MOST_POPULAR_MATCHERS) {
		if (out.length >= limit) break;
		const nameKey = normalizeKey(matcher.name);
		const hit = products.find((p) => {
			if (used.has(p.id)) return false;
			const slug = p.slug.toLowerCase();
			if (slug === matcher.slug || slug.includes(matcher.slug)) return true;
			return normalizeKey(p.name).includes(nameKey);
		});
		if (hit) {
			out.push(hit);
			used.add(hit.id);
		}
	}
	return out;
}
