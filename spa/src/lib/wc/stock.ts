/** Shared purchasability checks aligned with WooCommerce stock status. */

type StockFields = {
	is_in_stock?: boolean;
	is_purchasable?: boolean;
	stock_availability?: { text?: string; class?: string };
};

export function isOutOfStock(product: StockFields): boolean {
	if (product.stock_availability?.class === 'out-of-stock') return true;
	return product.is_in_stock === false;
}

export function canPurchase(product: StockFields): boolean {
	if (product.is_purchasable === false) return false;
	return !isOutOfStock(product);
}
