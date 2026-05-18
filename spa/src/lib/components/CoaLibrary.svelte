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

	const filtered = $derived.by(() => {
		const q = query.trim().toLowerCase();
		if (!q) return products;
		return products
			.map((p) => {
				const certs = p.certificates.filter((c) => certMatchesQuery(p, c, q));
				if (!certs.length && !p.name.toLowerCase().includes(q)) return null;
				return { ...p, certificates: certs.length ? certs : p.certificates };
			})
			.filter((p): p is CoaLibraryProduct => p !== null);
	});

	function certMatchesQuery(p: CoaLibraryProduct, c: CoaLibraryCertificate, q: string): boolean {
		if (p.name.toLowerCase().includes(q)) return true;
		if (c.batch.toLowerCase().includes(q)) return true;
		if (c.lab.toLowerCase().includes(q)) return true;
		if (c.variation_label.toLowerCase().includes(q)) return true;
		return false;
	}

	function certBatch(c: CoaLibraryCertificate): string {
		return c.batch.trim();
	}

	function labCertificatesTitle(name: string, variationLabel: string): string {
		if (variationLabel) return `${name} — ${variationLabel}`;
		return `${name} Lab Certificates`;
	}

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
				placeholder="Search by product name, batch number or lab…"
				bind:value={query}
				autocomplete="off"
			/>
		</label>
	</div>

	{#if loading}
		<p class="coa-lib__status" role="status">Loading certificates…</p>
	{:else if error}
		<p class="coa-lib__status coa-lib__status--error" role="alert">{error}</p>
	{:else if !filtered.length}
		<p class="coa-lib__status">
			{query.trim() ? 'No certificates match your search.' : 'No certificates have been published yet.'}
		</p>
	{:else}
		<ul class="coa-lib__list">
			{#each filtered as product (product.id)}
				<li class="coa-lib__product">
					<div class="coa-lib__product-top">
						<h2 class="coa-lib__product-name">
							<svg class="coa-lib__flask" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
								<path d="M9 3h6v7l5 9a2 2 0 0 1-1.7 3H5.7A2 2 0 0 1 4 19l5-9V3z"/>
								<path d="M9 3h6"/>
							</svg>
							{product.name}
						</h2>
					</div>
					<ul class="coa-lib__certs">
						{#each product.certificates as cert (cert.id)}
							<li class="coa-lib__card">
								<div class="coa-lib__card-body">
									<h3 class="coa-lib__cert-title">
										{labCertificatesTitle(product.name, cert.variation_label)}
									</h3>
									{#if certBatch(cert) || cert.lab.trim()}
										<p class="coa-lib__meta">
											{#if certBatch(cert)}
												<span>Batch: {certBatch(cert)}</span>
											{/if}
											{#if cert.lab.trim()}
												<span class="coa-lib__meta-lab">{cert.lab.trim()}</span>
											{/if}
										</p>
									{/if}
									<a class="coa-lib__view-product" href="/product/{product.slug}">
										View product
										<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
											<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
											<path d="M15 3h6v6M10 14L21 3"/>
										</svg>
									</a>
								</div>
								<div class="coa-lib__card-actions">
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
								</div>
							</li>
						{/each}
					</ul>
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
		gap: 40px 24px;
		align-items: start;
	}
	.coa-lib__product {
		min-width: 0;
	}
	.coa-lib__product-top {
		margin-bottom: 12px;
	}
	.coa-lib__product-name {
		display: flex;
		align-items: center;
		gap: 10px;
		margin: 0;
		font-size: 17px;
		font-weight: 700;
		color: var(--fg);
		line-height: 1.3;
	}
	.coa-lib__flask {
		color: var(--accent);
		flex-shrink: 0;
	}
	.coa-lib__cert-title {
		margin: 0 0 8px;
		font-size: 15px;
		font-weight: 700;
		color: var(--fg);
		line-height: 1.35;
	}
	.coa-lib__view-product {
		display: inline-flex;
		align-items: center;
		gap: 5px;
		margin-top: 10px;
		font-size: 14px;
		font-weight: 400;
		color: color-mix(in srgb, var(--fg) 52%, transparent);
		text-decoration: none;
	}
	.coa-lib__view-product:hover {
		color: var(--accent);
	}
	.coa-lib__certs {
		list-style: none;
		margin: 0;
		padding: 0;
		display: flex;
		flex-direction: column;
		gap: 12px;
	}
	.coa-lib__card {
		display: flex;
		align-items: flex-start;
		justify-content: space-between;
		gap: 16px;
		min-height: 88px;
		padding: 18px 22px;
		border: 1px solid var(--border);
		border-radius: 16px;
		background: var(--bg);
		box-shadow: 0 1px 2px color-mix(in srgb, var(--fg) 4%, transparent);
	}
	.coa-lib__card-body {
		flex: 1;
		min-width: 0;
	}
	.coa-lib__card-actions {
		flex-shrink: 0;
		display: flex;
		flex-direction: column;
		align-items: stretch;
		padding-top: 4px;
	}
	.coa-lib__meta {
		margin: 0;
		display: flex;
		flex-wrap: wrap;
		align-items: baseline;
		gap: 6px 18px;
		font-size: 13px;
		color: color-mix(in srgb, var(--fg) 52%, transparent);
		line-height: 1.5;
	}
	.coa-lib__meta-lab {
		flex-basis: 100%;
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
			gap: 40px;
		}
	}
	@media (max-width: 640px) {
		.coa-lib {
			padding: 24px 16px 56px;
		}
		.coa-lib__card {
			flex-direction: column;
			align-items: stretch;
			padding: 18px 20px;
		}
		.coa-lib__card-actions {
			width: 100%;
		}
		.coa-lib__download {
			width: 100%;
		}
	}
</style>
