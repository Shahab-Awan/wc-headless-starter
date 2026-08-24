/**
 * Alyve Research / why-alyve age gate store.
 * Separate from the main-site SiteGate so bridge pages can require
 * confirmation without conflicting with landing-popup suppression.
 */

import type { BridgeAgeGateConfig } from './config.svelte';

const LS_KEY = 'wchs_bridge_age_gate';

type StoredGate = {
	version: number;
	ts: number;
};

class BridgeAgeGateStore {
	open = $state(false);
	checked = $state(false);

	check(gateConfig: BridgeAgeGateConfig | undefined, isAdmin = false): void {
		if (!gateConfig?.enabled || isAdmin) {
			this.open = false;
			this.checked = true;
			return;
		}

		const stored = this.read();
		if (stored && stored.version >= gateConfig.version) {
			this.open = false;
		} else {
			this.open = true;
		}
		this.checked = true;
	}

	/** Leave why-alyve without accepting — close gate UI for this navigation only. */
	resetForPath(isBridgeLanding: boolean, gateConfig: BridgeAgeGateConfig | undefined, isAdmin = false): void {
		if (!isBridgeLanding) {
			this.open = false;
			this.checked = true;
			return;
		}
		this.check(gateConfig, isAdmin);
	}

	accept(version: number): void {
		this.write({ version, ts: Date.now() });
		this.open = false;
	}

	private read(): StoredGate | null {
		if (typeof localStorage === 'undefined') return null;
		try {
			const raw = localStorage.getItem(LS_KEY);
			if (!raw) return null;
			const parsed = JSON.parse(raw);
			if (typeof parsed.version === 'number') return parsed as StoredGate;
			return null;
		} catch {
			return null;
		}
	}

	private write(data: StoredGate): void {
		if (typeof localStorage === 'undefined') return;
		try {
			localStorage.setItem(LS_KEY, JSON.stringify(data));
		} catch {
			// quota
		}
	}
}

export const bridgeAgeGate = new BridgeAgeGateStore();
