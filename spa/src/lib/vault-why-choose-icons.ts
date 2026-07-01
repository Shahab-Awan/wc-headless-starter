/** Accent helpers for Vault Why Choose cards. Icons: Lucide (@lucide/svelte, ISC). */

export const VAULT_WHY_CHOOSE_ACCENTS = [
	'violet',
	'green',
	'amber',
	'rose',
	'blue',
	'teal',
] as const;

export type VaultWhyChooseAccent = (typeof VAULT_WHY_CHOOSE_ACCENTS)[number];

export function normalizeVaultWhyChooseAccent(raw: string | undefined): VaultWhyChooseAccent {
	const key = raw?.trim() || 'violet';
	return (VAULT_WHY_CHOOSE_ACCENTS as readonly string[]).includes(key)
		? (key as VaultWhyChooseAccent)
		: 'violet';
}
