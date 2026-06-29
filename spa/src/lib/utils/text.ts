/**
 * Decode HTML entities in plain text from WordPress / WooCommerce REST.
 * Term names are often stored as `Structure &amp; Compound` — Svelte text
 * nodes show that literally unless decoded first.
 */
export function decodeHtmlEntities(value: string): string {
	if (!value || !value.includes('&')) return value;

	if (typeof document !== 'undefined') {
		const el = document.createElement('textarea');
		el.innerHTML = value;
		return el.value;
	}

	return value
		.replace(/&amp;/gi, '&')
		.replace(/&lt;/gi, '<')
		.replace(/&gt;/gi, '>')
		.replace(/&quot;/gi, '"')
		.replace(/&#0?39;/gi, "'")
		.replace(/&apos;/gi, "'");
}
