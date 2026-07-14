<script lang="ts">
	import { config } from '$lib/config.svelte';
	import { bridgeAwareHref } from '$lib/bridge-domain';

	const columns = $derived(config.data.footer?.columns ?? []);
	const tagline = $derived((config.data.footer as any)?.tagline ?? '');
	const socialLinks = $derived(((config.data as any).social_links ?? []) as Array<{platform: string; url: string}>);
	const brandName = $derived(config.data.brand_name);
	const year = new Date().getFullYear();

	// SVG glyphs for common social platforms. Keyed by platform slug the admin
	// writes into wchs_site_settings.social_links[].platform.
	const socialIcons: Record<string, string> = {
		instagram: '<rect x="3" y="3" width="18" height="18" rx="5" /><circle cx="12" cy="12" r="4" /><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none" />',
		facebook:  '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z" />',
		x:         '<path d="M4 4l16 16M20 4L4 20" />',
		twitter:   '<path d="M4 4l16 16M20 4L4 20" />',
		youtube:   '<rect x="2" y="5" width="20" height="14" rx="3" /><polygon points="10,9 16,12 10,15" fill="currentColor" stroke="none" />',
		linkedin:  '<rect x="3" y="3" width="18" height="18" rx="2" /><line x1="8" y1="11" x2="8" y2="17" /><circle cx="8" cy="8" r="0.8" fill="currentColor" stroke="none" /><path d="M12 17v-4a2 2 0 0 1 4 0v4M12 11v6" />',
		tiktok:    '<path d="M14 3v10a3 3 0 1 1-3-3M14 6a4 4 0 0 0 4 4" />',
		pinterest: '<circle cx="12" cy="12" r="9" /><path d="M9 20l3-11M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z" />',
	};
	const platformLabels: Record<string, string> = {
		instagram: 'Instagram', facebook: 'Facebook', x: 'X (Twitter)',
		twitter: 'Twitter', youtube: 'YouTube', linkedin: 'LinkedIn',
		tiktok: 'TikTok', pinterest: 'Pinterest',
	};

	// Newsletter signup state
	let emailInput = $state('');
	let status = $state<'idle' | 'submitting' | 'ok' | 'error' | 'rate_limited'>('idle');
	let errorMsg = $state('');
	async function submitNewsletter(e: Event) {
		e.preventDefault();
		if (status === 'submitting') return;
		status = 'submitting';
		errorMsg = '';
		try {
			const r = await fetch('/wp-json/wchs/v1/newsletter', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ email: emailInput }),
			});
			if (r.ok) {
				const subscribedEmail = emailInput.trim();
				status = 'ok';
				emailInput = '';
				if (subscribedEmail) {
					import('$lib/analytics').then((a) => {
						a.trackTriplePixelContact({ email: subscribedEmail });
					}).catch(() => {});
				}
			} else if (r.status === 429) {
				status = 'rate_limited';
			} else {
				const j = await r.json().catch(() => ({}));
				errorMsg = (j as { message?: string }).message || 'Subscription failed.';
				status = 'error';
			}
		} catch {
			status = 'error';
			errorMsg = 'Network error.';
		}
	}
</script>

{#if config.ready}
	<footer class="site-footer">
		<div class="site-footer__top">
			<div class="site-footer__brand">
				<p class="site-footer__name">{brandName}</p>
				{#if tagline}
					<p class="site-footer__tagline">{tagline}</p>
				{/if}
				{#if socialLinks.length > 0}
					<ul class="site-footer__social">
						{#each socialLinks as link (link.platform + link.url)}
							{#if link.url && socialIcons[link.platform]}
								<li>
									<a href={link.url} target="_blank" rel="noopener noreferrer" aria-label={platformLabels[link.platform] ?? link.platform}>
										<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
											{@html socialIcons[link.platform]}
										</svg>
									</a>
								</li>
							{/if}
						{/each}
					</ul>
				{/if}
			</div>

			{#if columns.length > 0}
				<div class="site-footer__cols">
					{#each columns as col (col.title)}
						<div class="site-footer__col">
							{#if col.title}<p class="site-footer__col-title">{col.title}</p>{/if}
							<ul>
								{#each col.links as link, i (i)}
									<li>
										{#if link.url.startsWith('http') || link.url.startsWith('mailto:') || link.url.startsWith('tel:')}
											<a href={link.url} target={link.url.startsWith('http') ? '_blank' : undefined} rel={link.url.startsWith('http') ? 'noopener noreferrer' : undefined}>{link.label}</a>
										{:else}
											<a href={bridgeAwareHref(link.url)}>{link.label}</a>
										{/if}
									</li>
								{/each}
							</ul>
						</div>
					{/each}
				</div>
			{/if}

			<div class="site-footer__newsletter">
				<p class="site-footer__col-title">Newsletter</p>
				<p class="site-footer__newsletter-blurb">Get updates on new products and offers.</p>
				<form class="site-footer__form" onsubmit={submitNewsletter}>
					<input
						type="email"
						required
						bind:value={emailInput}
						placeholder="your@email.com"
						aria-label="Email for newsletter"
						disabled={status === 'submitting' || status === 'ok'}
					/>
					<button type="submit" disabled={status === 'submitting' || status === 'ok'}>
						{status === 'submitting' ? '…' : status === 'ok' ? '✓ subscribed' : 'Subscribe'}
					</button>
				</form>
				{#if status === 'error'}
					<p class="site-footer__form-msg is-error">{errorMsg}</p>
				{:else if status === 'rate_limited'}
					<p class="site-footer__form-msg is-error">Too many attempts — try again in a bit.</p>
				{:else if status === 'ok'}
					<p class="site-footer__form-msg is-ok">Thanks, you're on the list.</p>
				{/if}
			</div>
		</div>

		<div class="site-footer__bottom">
			<span class="site-footer__copy">&copy; {year} {brandName}</span>
		</div>
	</footer>
{/if}

<style>
	.site-footer {
		border-top: 1px solid var(--border);
		padding: 48px 28px 24px;
		max-width: 1440px;
		margin: 0 auto;
	}
	@media (min-width: 640px) {
		.site-footer { padding: 60px 32px 32px; }
	}
	.site-footer__top {
		display: grid;
		grid-template-columns: 1fr;
		gap: 40px;
		padding-bottom: 32px;
		border-bottom: 1px solid var(--border);
		margin-bottom: 20px;
	}
	@media (min-width: 768px) {
		.site-footer__top {
			grid-template-columns: minmax(220px, 1.2fr) minmax(0, 2fr) minmax(220px, 1fr);
			gap: 48px;
			align-items: start;
		}
	}
	.site-footer__name {
		font-size: 18px;
		font-weight: 600;
		letter-spacing: -0.01em;
		color: var(--fg);
		margin: 0 0 10px;
	}
	.site-footer__tagline {
		font-size: 13px;
		line-height: 1.5;
		color: var(--fg-muted);
		margin: 0 0 20px;
		max-width: 340px;
	}
	.site-footer__social {
		display: flex;
		gap: 8px;
		list-style: none;
		padding: 0;
		margin: 0;
	}
	.site-footer__social a {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 36px;
		height: 36px;
		border: 1px solid var(--border);
		border-radius: 8px;
		color: var(--fg);
		transition: border-color var(--dur-fast) var(--ease), color var(--dur-fast) var(--ease);
	}
	.site-footer__social a:hover {
		color: var(--accent, var(--fg));
		border-color: var(--accent, var(--fg));
	}
	.site-footer__cols {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
		gap: 28px;
	}
	.site-footer__col-title {
		font-size: 11px;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.1em;
		color: var(--fg);
		margin: 0 0 12px;
	}
	.site-footer__col ul {
		list-style: none;
		padding: 0;
		margin: 0;
		display: flex;
		flex-direction: column;
		gap: 6px;
	}
	.site-footer__col a {
		color: var(--fg-muted);
		text-decoration: none;
		font-size: 13px;
		transition: color var(--dur-fast) var(--ease);
	}
	.site-footer__col a:hover { color: var(--fg); }
	.site-footer__newsletter-blurb {
		font-size: 13px;
		line-height: 1.5;
		color: var(--fg-muted);
		margin: 0 0 14px;
	}
	.site-footer__form {
		display: flex;
		gap: 6px;
	}
	.site-footer__form input {
		flex: 1 1 auto;
		min-width: 0;
		height: 36px;
		padding: 0 12px;
		background: var(--bg);
		color: var(--fg);
		border: 1px solid var(--border);
		border-radius: var(--radius-sm);
		font-family: var(--font-sans);
		font-size: 13px;
	}
	.site-footer__form input:focus {
		outline: none;
		border-color: var(--fg);
	}
	.site-footer__form button {
		flex: 0 0 auto;
		height: 36px;
		padding: 0 14px;
		background: var(--accent, var(--fg));
		color: var(--accent-fg, var(--bg));
		border: none;
		border-radius: var(--radius-sm);
		font-family: var(--font-sans);
		font-size: 12px;
		font-weight: 500;
		text-transform: uppercase;
		letter-spacing: 0.08em;
		cursor: pointer;
		transition: opacity var(--dur-fast) var(--ease);
	}
	.site-footer__form button:hover:not(:disabled) { opacity: 0.85; }
	.site-footer__form button:disabled { opacity: 0.55; cursor: default; }
	.site-footer__form-msg {
		font-size: 12px;
		margin: 10px 0 0;
	}
	.site-footer__form-msg.is-error { color: var(--danger, #dc2626); }
	.site-footer__form-msg.is-ok { color: var(--success, #059669); }
	.site-footer__bottom {
		display: flex;
		justify-content: center;
		padding-top: 4px;
	}
	.site-footer__copy {
		font-size: 11px;
		color: var(--fg-muted);
		letter-spacing: 0.02em;
	}
</style>
