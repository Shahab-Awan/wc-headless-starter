import { request } from './store-api';
import { isCartCrossSellBlockedProduct } from '$lib/config.svelte';
import { canPurchase } from './stock';

/** Drop ancillary products (shipping protection, BAC water) from storefront listings. */
export function filterCatalogProducts(products: StoreProduct[]): StoreProduct[] {
	return products.filter((p) => !isCartCrossSellBlockedProduct(p.id, p.slug));
}

export type StoreProductAttributeTerm = { id: number; name: string; slug: string; default?: boolean };

export type StoreProductAttribute = {
	id: number;
	name: string;
	taxonomy: string | null;
	has_variations: boolean;
	terms: StoreProductAttributeTerm[];
};

export type StoreProductVariationRef = {
	id: number;
	attributes: { name: string; value: string }[];
};

/**
 * Per-tier row as emitted by our headless-cro-extension mu-plugin.
 * Monetary fields are integer minor units (cents).
 */
export type WchsCroTierRow = {
	min_qty: number;
	unit_price: number;
	savings_per_unit: number;
	savings_pct: number;
	line_total_at_min_qty: number;
};

export type WchsCoaMetric = { label: string; value: string };

export type WchsCroProduct = {
	regular_price: number;
	tier_type: 'fixed' | 'percentage' | null;
	tiers: WchsCroTierRow[];
	cross_sell_ids: number[];
	coa_url?: string;
	coa_batch?: string;
	coa_lab?: string;
	coa_metrics?: WchsCoaMetric[];
};

export type StoreProductCategory = { id: number; name: string; slug: string };

export type StoreProduct = {
	id: number;
	name: string;
	slug: string;
	parent: number;
	type: 'simple' | 'variable' | 'variation';
	permalink: string;
	sku: string;
	description: string;
	short_description: string;
	on_sale: boolean;
	average_rating: string;
	review_count: number;
	sold_individually: boolean;
	is_on_backorder: boolean;
	low_stock_remaining: number | null;
	stock_availability: { text: string; class: string };
	categories: StoreProductCategory[];
	tags: { id: number; name: string; slug: string }[];
	prices: {
		price: string;
		regular_price: string;
		sale_price: string;
		price_range: { min_amount: string; max_amount: string } | null;
		currency_code: string;
		currency_symbol: string;
		currency_minor_unit: number;
	};
	images: { id: number; src: string; thumbnail: string; srcset?: string; sizes?: string; alt: string }[];
	add_to_cart: {
		text: string;
		description: string;
		minimum: number;
		maximum: number;
		multiple_of: number;
	};
	is_in_stock: boolean;
	is_purchasable: boolean;
	has_options: boolean;
	attributes: StoreProductAttribute[];
	variations: StoreProductVariationRef[];
	extensions?: {
		wchs_cro?: WchsCroProduct;
	};
};

export type StoreProductVariation = {
	id: number;
	type: 'variation';
	prices: StoreProduct['prices'];
	images: StoreProduct['images'];
	is_in_stock: boolean;
	is_purchasable: boolean;
	extensions?: StoreProduct['extensions'];
};

export type ProductListParams = {
	per_page?: number;
	page?: number;
	featured?: boolean;
	on_sale?: boolean;
	search?: string;
	category?: string;
	orderby?: 'date' | 'popularity' | 'price' | 'rating' | 'title';
	order?: 'asc' | 'desc';
	min_price?: number;
	max_price?: number;
};

export async function listProducts(params: ProductListParams = {}): Promise<StoreProduct[]> {
	const rows = await request<StoreProduct[]>('/products', { query: params });
	return filterCatalogProducts(rows);
}

export type StoreCategory = {
	id: number;
	name: string;
	slug: string;
	count: number;
	parent: number;
	description?: string;
};

export async function listCategories(opts?: { parent?: number }): Promise<StoreCategory[]> {
	const query: Record<string, string | number> = { per_page: 100, orderby: 'name', order: 'asc' };
	if (opts?.parent !== undefined) query.parent = opts.parent;
	return request<StoreCategory[]>('/products/categories', { query });
}

export async function getProduct(slug: string): Promise<StoreProduct | null> {
	const results = await request<StoreProduct[]>('/products', { query: { slug } });
	const product = results[0] ?? null;
	if (product && isCartCrossSellBlockedProduct(product.id, product.slug)) return null;
	return product;
}

/**
 * Fetch a set of products by ID. Used to render cross-sell strips where
 * we have an `extensions.wchs_cro.cross_sell_ids` list but need the full
 * product objects (name, image, price) to render cards.
 */
export async function getProductsByIds(ids: number[]): Promise<StoreProduct[]> {
	if (ids.length === 0) return [];
	const rows = await request<StoreProduct[]>('/products', {
		query: { include: ids.join(','), per_page: ids.length }
	});
	return filterCatalogProducts(rows);
}

/**
 * Fetch full details for a set of variation IDs. Use this on a PDP after
 * the parent product tells you which variations exist.
 */
export async function getVariations(ids: number[]): Promise<StoreProductVariation[]> {
	if (ids.length === 0) return [];
	return request<StoreProductVariation[]>('/products', {
		query: { type: 'variation', include: ids.join(','), per_page: ids.length }
	});
}

/**
 * Given a parent variable product's variations[] ref list and a chosen
 * attribute selection, find the matching variation id. Returns null if
 * the selection is incomplete or no variation matches.
 */
export function findVariationId(
	variations: StoreProductVariationRef[],
	selection: Record<string, string>
): number | null {
	for (const v of variations) {
		const match = v.attributes.every((attr) => selection[attr.name] === attr.value);
		const complete = v.attributes.every((attr) => selection[attr.name] !== undefined);
		if (match && complete) return v.id;
	}
	return null;
}

/** Build a selection map from variation attribute rows. */
export function selectionFromVariationAttrs(
	attrs: { name: string; value: string }[]
): Record<string, string> {
	const sel: Record<string, string> = {};
	for (const a of attrs) sel[a.name] = a.value;
	return sel;
}

/**
 * Prefer WC default attributes when that variation is purchasable; otherwise
 * the first in-stock variation. Returns null when nothing can be bought.
 */
export function findPurchasableDefaultSelection(
	product: StoreProduct,
	variations: StoreProductVariation[]
): Record<string, string> | null {
	if (!product.has_options || product.attributes.length === 0) return null;

	const defaults: Record<string, string> = {};
	for (const attr of product.attributes) {
		const def = attr.terms?.find((t) => t.default);
		if (def) defaults[attr.name] = def.name;
	}
	if (Object.keys(defaults).length === product.attributes.length) {
		const id = findVariationId(product.variations, defaults);
		const v = variations.find((x) => x.id === id);
		if (v && canPurchase(v)) return defaults;
	}

	for (const ref of product.variations) {
		const v = variations.find((x) => x.id === ref.id);
		if (!v || !canPurchase(v)) continue;
		return selectionFromVariationAttrs(ref.attributes);
	}
	return null;
}
