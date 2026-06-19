import type { GateModalConfig } from '$lib/config.svelte';
import { gate } from '$lib/gate.svelte';
import { shouldSuppressLandingPopups } from '$lib/bridge-domain';

const POPUP_SCRIPT_IDS = new Set(['omnisend', 'klaviyo', 'cookiebot']);

export function shouldSkipLandingPopupScript(scriptId: string): boolean {
	return POPUP_SCRIPT_IDS.has(scriptId);
}

export function applyLandingPopupSuppression(
	pathname: string,
	gateConfig: GateModalConfig,
	isAdmin: boolean
): boolean {
	const suppress = shouldSuppressLandingPopups(pathname);
	if (typeof document !== 'undefined') {
		document.documentElement.toggleAttribute('data-wchs-suppress-popups', suppress);
	}
	if (suppress) {
		gate.open = false;
		gate.checked = true;
	} else {
		gate.check(gateConfig, isAdmin);
	}
	return suppress;
}
