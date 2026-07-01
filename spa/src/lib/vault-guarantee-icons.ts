/** Guarantee card icons — clean stroke art tuned for 28px @ 24 viewBox. */
export const VAULT_GUARANTEE_ICONS: Record<string, string> = {
	purity: `
		<circle cx="12" cy="12" r="7.5" fill="none" stroke="currentColor" stroke-width="1.75"/>
		<path d="M8.6 12.1 10.85 14.35 15.55 9.55" fill="none" stroke="currentColor" stroke-width="2.15" stroke-linecap="round" stroke-linejoin="round"/>
	`,
	shipping: `
		<path d="M3.25 9.35h10.85v7.05H3.25z" fill="currentColor" opacity="0.1"/>
		<path d="M3.25 9.35h10.85v7.05H3.25z" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linejoin="round"/>
		<path d="M14.1 11.15h2.95l3.2 3.35v3.9H14.1V11.15z" fill="currentColor" opacity="0.08"/>
		<path d="M14.1 11.15h2.95l3.2 3.35v3.9H14.1V11.15z" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linejoin="round"/>
		<path d="M5.85 12.05h4.35" fill="none" stroke="currentColor" stroke-width="1.35" stroke-linecap="round" opacity="0.55"/>
		<path d="M16.35 11.15V9.35h-2.2" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round"/>
		<circle cx="7.85" cy="17.65" r="1.65" fill="none" stroke="currentColor" stroke-width="1.5"/>
		<circle cx="17.15" cy="17.65" r="1.65" fill="none" stroke="currentColor" stroke-width="1.5"/>
		<circle cx="7.85" cy="17.65" r="0.65" fill="currentColor" stroke="none"/>
		<circle cx="17.15" cy="17.65" r="0.65" fill="currentColor" stroke="none"/>
	`,
	coa: `
		<path d="M7.35 3.85h6.35l3.05 3.05V19.1a1.2 1.2 0 0 1-1.2 1.2H7.35a1.2 1.2 0 0 1-1.2-1.2V5.05a1.2 1.2 0 0 1 1.2-1.2z" fill="currentColor" opacity="0.1"/>
		<path d="M7.35 3.85h6.35l3.05 3.05V19.1a1.2 1.2 0 0 1-1.2 1.2H7.35a1.2 1.2 0 0 1-1.2-1.2V5.05a1.2 1.2 0 0 1 1.2-1.2z" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linejoin="round"/>
		<path d="M13.7 3.85v3.05h3.05" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linejoin="round"/>
		<path d="M8.85 10.1h6.3M8.85 12.65h6.3M8.85 15.2h4.35" fill="none" stroke="currentColor" stroke-width="1.35" stroke-linecap="round" opacity="0.7"/>
		<circle cx="14.85" cy="16.75" r="2.05" fill="none" stroke="currentColor" stroke-width="1.25"/>
		<path d="M13.95 16.75 14.55 17.35 15.85 16.05" fill="none" stroke="currentColor" stroke-width="1.15" stroke-linecap="round" stroke-linejoin="round"/>
	`,
};

export function vaultGuaranteeIconMarkup(icon: string | undefined): string {
	const key = icon?.trim() || 'purity';
	return VAULT_GUARANTEE_ICONS[key] ?? VAULT_GUARANTEE_ICONS.purity;
}
