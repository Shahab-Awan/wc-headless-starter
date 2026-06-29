const STORAGE_KEY = 'wchs_stock_memory_v1';
const BADGE_TTL_MS = 14 * 24 * 60 * 60 * 1000;

type StockEntry = {
	oosAt: number;
	restockedAt?: number;
};

let memory: Record<string, StockEntry> = {};
let loaded = false;

function load(): Record<string, StockEntry> {
	if (typeof localStorage === 'undefined') return {};
	try {
		const raw = localStorage.getItem(STORAGE_KEY);
		if (!raw) return {};
		const parsed = JSON.parse(raw) as unknown;
		return parsed && typeof parsed === 'object' ? (parsed as Record<string, StockEntry>) : {};
	} catch {
		return {};
	}
}

function persist() {
	if (typeof localStorage === 'undefined') return;
	localStorage.setItem(STORAGE_KEY, JSON.stringify(memory));
}

function ensureLoaded() {
	if (loaded || typeof window === 'undefined') return;
	memory = load();
	loaded = true;
}

/** Track stock transitions and return whether to show a restock badge. */
export function noteProductStockStatus(productId: number, outOfStock: boolean): boolean {
	ensureLoaded();
	const key = String(productId);
	const now = Date.now();
	const entry = memory[key];

	if (outOfStock) {
		memory[key] = { oosAt: now };
		persist();
		return false;
	}

	if (!entry?.oosAt) return false;

	if (entry.restockedAt && now - entry.restockedAt > BADGE_TTL_MS) {
		delete memory[key];
		persist();
		return false;
	}

	if (!entry.restockedAt) {
		memory[key] = { oosAt: entry.oosAt, restockedAt: now };
		persist();
		return true;
	}

	return true;
}
