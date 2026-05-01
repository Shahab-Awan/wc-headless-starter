/**
 * Site gate store — Svelte 5 runes.
 *
 * Controls a configurable first-visit modal (age verification, RUO
 * disclaimers, terms acceptance, etc.). Acceptance is persisted to
 * localStorage with a version number so admins can bump the version
 * to re-show the gate to all users.
 */

import type { GateModalConfig } from './config.svelte';

const LS_KEY = 'wchs_gate';

type StoredGate = {
	version: number;
	ts: number;
};

class GateStore {
	/** True = modal should be visible. */
	open = $state(false);

	/**
	 * True after the store has checked localStorage vs config.
	 * Prevents the gate from being visible during SSR / initial
	 * hydration — the always-in-DOM element stays hidden (opacity 0)
	 * until this flips to true.
	 */
	checked = $state(false);

	/**
	 * Called after config.load() resolves. Reads localStorage and
	 * compares against the config version to decide if the gate
	 * should show. Admins always bypass.
	 */
	check(gateConfig: GateModalConfig, isAdmin = false): void {
		if (!gateConfig.enabled || isAdmin) {
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

	/** User accepted the gate — persist and close. */
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
			// Quota exceeded — fail silently.
		}
	}
}

export const gate = new GateStore();
