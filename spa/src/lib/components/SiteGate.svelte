<script lang="ts">
	/**
	 * SiteGate — full-page modal for first-visit gates (age verification,
	 * RUO disclaimers, terms acceptance, etc.).
	 *
	 * Architecture follows the SlideCart pattern:
	 *   - Element is ALWAYS in the DOM (never {#if} toggled)
	 *   - Visibility toggled via CSS class binding
	 *   - Avoids Svelte 5 bug #14732 (opacity flash on mount)
	 *
	 * When `strict` is true: no close button, no click-outside, no Escape.
	 * The user MUST click Confirm or Decline.
	 */
	import { page } from '$app/state';
	import { config } from '$lib/config.svelte';
	import { gate } from '$lib/gate.svelte';
	import { shouldSuppressLandingPopups } from '$lib/bridge-domain';

	let modalEl: HTMLDivElement | undefined;
	const suppressPopups = $derived(shouldSuppressLandingPopups(page.url.pathname));
	const show = $derived(!suppressPopups && gate.open && gate.checked);
	const gateConfig = $derived(config.data.gate_modal);

	function confirm() {
		gate.accept(gateConfig.version);
	}

	function decline() {
		const url = gateConfig.decline_url || 'https://google.com';
		window.location.href = url;
	}

	function dismiss() {
		if (gateConfig.strict) return;
		gate.accept(gateConfig.version);
	}

	function onBackdropClick() {
		if (!gateConfig.strict) dismiss();
	}

	function onKeydown(e: KeyboardEvent) {
		if (!show) return;
		if (e.key === 'Escape' && !gateConfig.strict) {
			dismiss();
		}
	}

	// Scroll lock
	$effect(() => {
		if (typeof document === 'undefined') return;
		document.body.classList.toggle('wchs-gate-lock', show);
	});

	// Focus trap — cycle Tab within the modal when open
	$effect(() => {
		if (!show || !modalEl) return;

		const focusable = modalEl.querySelectorAll<HTMLElement>(
			'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
		);
		const first = focusable[0];
		const last = focusable[focusable.length - 1];

		// Auto-focus the first focusable element
		requestAnimationFrame(() => first?.focus());

		function trap(e: KeyboardEvent) {
			if (e.key !== 'Tab') return;
			if (focusable.length === 0) {
				e.preventDefault();
				return;
			}
			if (e.shiftKey) {
				if (document.activeElement === first) {
					e.preventDefault();
					last?.focus();
				}
			} else {
				if (document.activeElement === last) {
					e.preventDefault();
					first?.focus();
				}
			}
		}

		document.addEventListener('keydown', trap);
		return () => document.removeEventListener('keydown', trap);
	});
</script>

<svelte:window onkeydown={onKeydown} />

<!-- Backdrop -->
<div
	class="wchs-gate-backdrop"
	class:wchs-gate-show={show}
	aria-hidden="true"
	onclick={onBackdropClick}
	role="presentation"
></div>

<!-- Modal -->
<div
	class="wchs-gate-modal"
	class:wchs-gate-show={show}
	role="dialog"
	aria-modal="true"
	aria-label={gateConfig.title || 'Site notice'}
	bind:this={modalEl}
>
	{#if !gateConfig.strict}
		<button class="wchs-gate-close" onclick={dismiss} aria-label="Close">
			<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
				<path d="M6 6l12 12M18 6L6 18" />
			</svg>
		</button>
	{/if}

	{#if gateConfig.title}
		<h2 class="wchs-gate-title">{gateConfig.title}</h2>
	{/if}

	{#if gateConfig.content}
		<div class="wchs-gate-content">{@html gateConfig.content}</div>
	{/if}

	<div class="wchs-gate-actions">
		<button class="wchs-gate-confirm" onclick={confirm}>
			{gateConfig.confirm_text || 'Enter Site'}
		</button>
		{#if gateConfig.decline_text}
			<button class="wchs-gate-decline" onclick={decline}>
				{gateConfig.decline_text}
			</button>
		{/if}
	</div>
</div>

<style>
	/* ──────────────────────────────────────────────────────────
	   Backdrop — always in DOM, toggled via class
	   ────────────────────────────────────────────────────────── */
	.wchs-gate-backdrop {
		position: fixed;
		inset: 0;
		background: var(--overlay);
		z-index: 9996;
		opacity: 0;
		pointer-events: none;
		transition: opacity var(--dur-fast) var(--ease-out);
	}
	.wchs-gate-backdrop.wchs-gate-show {
		opacity: 1;
		pointer-events: auto;
	}

	/* ──────────────────────────────────────────────────────────
	   Modal card — centered, scale+fade entry
	   ────────────────────────────────────────────────────────── */
	.wchs-gate-modal {
		position: fixed;
		top: 50%;
		left: 50%;
		transform: translate(-50%, -50%) scale(0.96);
		z-index: 9997;
		background: var(--bg);
		border: 1px solid var(--border);
		max-width: 480px;
		width: calc(100vw - 48px);
		padding: 40px 36px 32px;
		text-align: center;
		opacity: 0;
		pointer-events: none;
		transition:
			opacity var(--dur-med) var(--ease-out),
			transform var(--dur-med) var(--ease-out);
	}
	.wchs-gate-modal.wchs-gate-show {
		opacity: 1;
		pointer-events: auto;
		transform: translate(-50%, -50%) scale(1);
	}

	/* ──────────────────────────────────────────────────────────
	   Close button (hidden in strict mode)
	   ────────────────────────────────────────────────────────── */
	.wchs-gate-close {
		position: absolute;
		top: 16px;
		right: 16px;
		background: none;
		border: none;
		color: var(--fg-muted);
		cursor: pointer;
		padding: 4px;
		display: flex;
		align-items: center;
		justify-content: center;
		transition: color var(--dur-fast) var(--ease);
	}
	.wchs-gate-close:hover {
		color: var(--fg);
	}

	/* ──────────────────────────────────────────────────────────
	   Content
	   ────────────────────────────────────────────────────────── */
	.wchs-gate-title {
		font-family: var(--font-heading, var(--font-sans));
		font-size: 22px;
		font-weight: var(--heading-weight, 500);
		letter-spacing: -0.03em;
		line-height: 1.3;
		margin: 0 0 16px;
		color: var(--fg);
	}
	.wchs-gate-content {
		font-size: 14px;
		line-height: 1.6;
		color: var(--fg-muted);
		margin: 0 0 28px;
	}
	.wchs-gate-content :global(a) {
		color: var(--accent);
		text-decoration: underline;
		text-underline-offset: 2px;
	}
	.wchs-gate-content :global(p) {
		margin: 0 0 12px;
	}
	.wchs-gate-content :global(p:last-child) {
		margin-bottom: 0;
	}
	.wchs-gate-content :global(strong) { font-weight: 700; }
	.wchs-gate-content :global(em) { font-style: italic; }
	.wchs-gate-content :global(ul),
	.wchs-gate-content :global(ol) { padding-left: 24px; margin: 0 0 10px; }
	.wchs-gate-content :global(li) { margin-bottom: 4px; }

	/* ──────────────────────────────────────────────────────────
	   Actions
	   ────────────────────────────────────────────────────────── */
	.wchs-gate-actions {
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 12px;
	}
	.wchs-gate-confirm {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		padding: 14px 32px;
		background: var(--accent);
		color: var(--accent-fg);
		border: 1px solid var(--accent);
		font-size: 12px;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.08em;
		cursor: pointer;
		transition: opacity var(--dur-fast) var(--ease);
		min-width: 200px;
	}
	.wchs-gate-confirm:hover {
		opacity: 0.85;
	}
	.wchs-gate-decline {
		background: none;
		border: none;
		color: var(--fg-muted);
		font-size: 12px;
		letter-spacing: 0.04em;
		cursor: pointer;
		padding: 8px 16px;
		transition: color var(--dur-fast) var(--ease);
	}
	.wchs-gate-decline:hover {
		color: var(--fg);
	}

	/* Scroll lock applied to html+body (html needed for iOS Safari) */
	:global(html:has(body.wchs-gate-lock)),
	:global(body.wchs-gate-lock) {
		overflow: hidden;
	}
</style>
