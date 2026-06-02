<script lang="ts">
	import { coaDownloadFilename, downloadCoaFile } from '$lib/wc/coa';
	import {
		fetchCoaLibrary,
		type CoaLibraryCertificate,
		type CoaLibraryProduct,
	} from '$lib/wc/coa-library';

	let query = $state('');
	let loading = $state(true);
	let error = $state('');
	let products = $state<CoaLibraryProduct[]>([]);
	let downloadingId = $state<number | null>(null);

	$effect(() => {
		let cancelled = false;
		loading = true;
		error = '';
		fetchCoaLibrary()
			.then((rows) => {
				if (!cancelled) products = rows;
			})
			.catch(() => {
				if (!cancelled) error = 'Could not load certificates. Please try again.';
			})
			.finally(() => {
				if (!cancelled) loading = false;
			});
		return () => {
			cancelled = true;
		};
	});

	function certMatchesQuery(p: CoaLibraryProduct, c: CoaLibraryCertificate, q: string): boolean {
		const hay = [p.name, c.variation_label, c.batch, c.lab, certCardTitle(c)]
			.join(' ')
			.toLowerCase();
		return hay.includes(q);
	}

	/** Label inside a product group — variation mg/size, or General when parent has a COA. */
	function certCardTitle(c: CoaLibraryCertificate): string {
		const variation = c.variation_label.trim();
		if (variation) return variation;
		return 'General';
	}

	function displayMeta(value: string): string {
		const v = value.trim();
		if (!v || v.toLowerCase() === 'array') return '';
		return v;
	}

	function sortCertificates(certs: CoaLibraryCertificate[]): CoaLibraryCertificate[] {
		return [...certs].sort((a, b) => {
			const aMain = a.variation_label.trim() ? 1 : 0;
			const bMain = b.variation_label.trim() ? 1 : 0;
			if (aMain !== bMain) return aMain - bMain;
			return a.variation_label.localeCompare(b.variation_label, undefined, { sensitivity: 'base' });
		});
	}

	const filteredProducts = $derived.by((): CoaLibraryProduct[] => {
		const q = query.trim().toLowerCase();
		const out: CoaLibraryProduct[] = [];
		for (const p of products) {
			const certs = q
				? p.certificates.filter((c) => certMatchesQuery(p, c, q))
				: p.certificates;
			if (!certs.length) continue;
			out.push({ ...p, certificates: sortCertificates(certs) });
		}
		return out;
	});

	async function onDownload(
		e: MouseEvent,
		productSlug: string,
		cert: CoaLibraryCertificate
	) {
		e.preventDefault();
		if (!cert.coa_url || downloadingId === cert.id) return;
		downloadingId = cert.id;
		const filename = coaDownloadFilename(productSlug, cert.batch, cert.coa_url);
		try {
			await downloadCoaFile(cert.coa_url, filename);
		} catch {
			const anchor = document.createElement('a');
			anchor.href = cert.coa_url;
			anchor.download = filename;
			anchor.rel = 'noopener';
			document.body.appendChild(anchor);
			anchor.click();
			anchor.remove();
		} finally {
			downloadingId = null;
		}
	}
</script>

<section class="coa-lib" aria-labelledby="coa-lib-title">
	<header class="coa-lib__hero">
		<div class="coa-lib__hero-icon" aria-hidden="true">
			<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.75">
				<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
				<path d="M14 2v6h6M9 15l2 2 4-4"/>
			</svg>
		</div>
		<h1 id="coa-lib-title" class="coa-lib__title">Certificates of Analysis</h1>
		<p class="coa-lib__lead">
			Access Certificates of Analysis (COA) for our products. Each batch is independently tested for purity and identity.
		</p>
	</header>

	<div class="coa-lib__search-wrap">
		<label class="coa-lib__search" for="coa-lib-search">
			<svg class="coa-lib__search-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
				<circle cx="11" cy="11" r="7"/><path d="M20 20l-3-3"/>
			</svg>
			<input
				id="coa-lib-search"
				type="search"
				placeholder="Search by product, variation, batch, or lab…"
				bind:value={query}
				autocomplete="off"
			/>
		</label>
	</div>

	{#if loading}
		<p class="coa-lib__status" role="status">Loading certificates…</p>
	{:else if error}
		<p class="coa-lib__status coa-lib__status--error" role="alert">{error}</p>
	{:else if !filteredProducts.length}
		<p class="coa-lib__status">
			{query.trim() ? 'No certificates match your search.' : 'No certificates have been published yet.'}
		</p>
	{:else}
		<ul class="coa-lib__list">
			{#each filteredProducts as product (product.id)}
				<li class="coa-lib__product">
					<article class="coa-lib__group">
						<h2 class="coa-lib__group-title">
							<svg class="coa-lib__flask" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
								<path d="M9 3h6v7l5 9a2 2 0 0 1-1.7 3H5.7A2 2 0 0 1 4 19l5-9V3z"/>
								<path d="M9 3h6"/>
							</svg>
							{product.name}
						</h2>
						<ul class="coa-lib__rows">
							{#each product.certificates as cert (cert.id)}
								<li class="coa-lib__row">
									<div class="coa-lib__row-main">
										<span class="coa-lib__row-label">{certCardTitle(cert)}</span>
										{#if displayMeta(cert.batch) || displayMeta(cert.lab)}
											<p class="coa-lib__row-meta">
												{#if displayMeta(cert.batch)}
													<span>Batch: {displayMeta(cert.batch)}</span>
												{/if}
												{#if displayMeta(cert.lab)}
													<span>{displayMeta(cert.lab)}</span>
												{/if}
											</p>
										{/if}
									</div>
									<a
										class="coa-lib__download"
										href={cert.coa_url}
										download={coaDownloadFilename(product.slug, cert.batch, cert.coa_url)}
										aria-busy={downloadingId === cert.id}
										onclick={(e) => onDownload(e, product.slug, cert)}
									>
										<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
											<path d="M12 3v12M7 10l5 5 5-5"/><path d="M5 21h14"/>
										</svg>
										{downloadingId === cert.id ? '…' : 'COA'}
									</a>
								</li>
							{/each}
						</ul>
						<a class="coa-lib__view-product" href="/product/{product.slug}">
							View product
							<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
								<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
								<path d="M15 3h6v6M10 14L21 3"/>
							</svg>
						</a>
					</article>
				</li>
			{/each}
		</ul>
	{/if}
</section>

<style>
	.coa-lib {
		width: 100%;
		max-width: 1200px;
		margin: 0 auto;
		padding: 32px 28px 80px;
		box-sizing: border-box;
	}
	.coa-lib__hero {
		text-align: center;
		margin-bottom: 32px;
	}
	.coa-lib__hero-icon {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 56px;
		height: 56px;
		border-radius: 14px;
		background: color-mix(in srgb, var(--accent) 14%, transparent);
		color: var(--accent);
		margin-bottom: 16px;
	}
	.coa-lib__title {
		font-size: clamp(28px, 4vw, 40px);
		font-weight: 700;
		color: var(--fg);
		margin: 0 0 12px;
		letter-spacing: -0.02em;
	}
	.coa-lib__lead {
		margin: 0 auto;
		max-width: 560px;
		font-size: 15px;
		line-height: 1.55;
		color: color-mix(in srgb, var(--fg) 65%, transparent);
	}
	.coa-lib__search-wrap {
		margin-bottom: 36px;
	}
	.coa-lib__search {
		display: flex;
		align-items: center;
		gap: 12px;
		padding: 0 18px;
		height: 52px;
		border: 1px solid var(--border);
		border-radius: 999px;
		background: var(--bg);
	}
	.coa-lib__search:focus-within {
		border-color: color-mix(in srgb, var(--accent) 50%, var(--border));
		box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 12%, transparent);
	}
	.coa-lib__search-icon {
		flex-shrink: 0;
		color: color-mix(in srgb, var(--fg) 45%, transparent);
	}
	.coa-lib__search input {
		flex: 1;
		border: 0;
		background: transparent;
		font-size: 15px;
		color: var(--fg);
		outline: none;
		min-width: 0;
	}
	.coa-lib__search input::placeholder {
		color: color-mix(in srgb, var(--fg) 45%, transparent);
	}
	.coa-lib__status {
		text-align: center;
		color: color-mix(in srgb, var(--fg) 60%, transparent);
		padding: 48px 0;
	}
	.coa-lib__status--error {
		color: var(--accent);
	}
	.coa-lib__list {
		list-style: none;
		margin: 0;
		padding: 0;
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		gap: 20px 20px;
		align-items: stretch;
	}
	.coa-lib__product {
		min-width: 0;
	}
	.coa-lib__group {
		height: 100%;
		display: flex;
		flex-direction: column;
		padding: 18px 20px 16px;
		border: 1px solid var(--border);
		border-radius: 16px;
		background: var(--bg);
		box-shadow: 0 1px 2px color-mix(in srgb, var(--fg) 4%, transparent);
	}
	.coa-lib__group-title {
		display: flex;
		align-items: center;
		gap: 10px;
		margin: 0 0 14px;
		padding-bottom: 12px;
		border-bottom: 1px solid var(--border);
		font-size: 17px;
		font-weight: 700;
		color: var(--fg);
		line-height: 1.3;
	}
	.coa-lib__flask {
		color: var(--accent);
		flex-shrink: 0;
	}
	.coa-lib__rows {
		list-style: none;
		margin: 0;
		padding: 0;
		flex: 1;
	}
	.coa-lib__row {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 12px;
		padding: 12px 0;
		border-top: 1px solid var(--border);
	}
	.coa-lib__row:first-child {
		border-top: 0;
		padding-top: 0;
	}
	.coa-lib__row-main {
		flex: 1;
		min-width: 0;
	}
	.coa-lib__row-label {
		display: block;
		font-size: 15px;
		font-weight: 700;
		color: var(--fg);
		line-height: 1.35;
	}
	.coa-lib__row-meta {
		margin: 4px 0 0;
		font-size: 12px;
		color: color-mix(in srgb, var(--fg) 52%, transparent);
		line-height: 1.4;
	}
	.coa-lib__row-meta span + span::before {
		content: ' · ';
	}
	.coa-lib__view-product {
		display: inline-flex;
		align-items: center;
		gap: 5px;
		margin-top: 14px;
		padding-top: 12px;
		border-top: 1px solid var(--border);
		font-size: 14px;
		font-weight: 400;
		color: color-mix(in srgb, var(--fg) 52%, transparent);
		text-decoration: none;
	}
	.coa-lib__view-product:hover {
		color: var(--accent);
	}
	.coa-lib__download {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		gap: 8px;
		min-width: 108px;
		padding: 11px 22px;
		border-radius: 999px;
		background: var(--accent);
		color: var(--accent-fg, #fff);
		font-size: 14px;
		font-weight: 600;
		text-decoration: none;
		border: none;
		cursor: pointer;
	}
	.coa-lib__download:hover {
		filter: brightness(1.06);
	}
	.coa-lib__download[aria-busy='true'] {
		opacity: 0.75;
		pointer-events: none;
	}
	@media (max-width: 900px) {
		.coa-lib__list {
			grid-template-columns: 1fr;
			gap: 16px;
		}
	}
	@media (max-width: 640px) {
		.coa-lib {
			padding: 24px 16px 56px;
		}
		.coa-lib__row {
			flex-direction: column;
			align-items: stretch;
		}
		.coa-lib__download {
			width: 100%;
		}
	}
</style>
