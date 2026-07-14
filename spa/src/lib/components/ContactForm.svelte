<script lang="ts">
	import { onMount } from 'svelte';
	import { config } from '$lib/config.svelte';
	import type { ContactFormModuleConfig, SpacingPreset, ModuleResolved } from '$lib/config.svelte';

	let { config: formConfig, spacing_v = 'normal', spacing_h = 'normal', center_header = false, resolved }: {
		config: ContactFormModuleConfig;
		spacing_v?: SpacingPreset;
		spacing_h?: SpacingPreset;
		center_header?: boolean;
		resolved?: ModuleResolved;
	} = $props();

	// Per-module accent override. Scopes --accent to this form so the submit
	// button uses the override; falls back to site default when not set.
	const accentStyle = $derived(resolved?.accent_color ? `--accent: ${resolved.accent_color}` : '');

	let values = $state<Record<string, string>>({});
	let submitting = $state(false);
	let submitted = $state(false);
	let error = $state<string | null>(null);
	let turnstileEl = $state<HTMLElement | undefined>();
	let turnstileWidgetId = $state<string | null>(null);
	let turnstileToken = $state('');

	const siteKey = $derived(config.data.turnstile_site_key || '');
	const turnstileRequired = $derived(!!siteKey);
	const turnstileReady = $derived(!turnstileRequired || !!turnstileToken);

	onMount(() => {
		// Initialize form values
		const init: Record<string, string> = {};
		for (const field of formConfig.fields) {
			init[field.name] = '';
		}
		values = init;

		// Load Turnstile if needed
		if (siteKey && typeof window !== 'undefined') {
			loadTurnstile();
		}
	});

	function loadTurnstile() {
		if (document.querySelector('script[src*="challenges.cloudflare.com/turnstile"]')) {
			renderWidget();
			return;
		}
		const script = document.createElement('script');
		script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?onload=onTurnstileLoad';
		script.async = true;
		(window as any).onTurnstileLoad = () => renderWidget();
		document.head.appendChild(script);
	}

	function renderWidget() {
		if (!turnstileEl || !siteKey) return;
		const w = (window as any).turnstile;
		if (!w) {
			setTimeout(renderWidget, 100);
			return;
		}
		turnstileWidgetId = w.render(turnstileEl, {
			sitekey: siteKey,
			theme: 'auto',
			appearance: 'always',
			callback: (token: string) => { turnstileToken = token; },
			'expired-callback': () => { turnstileToken = ''; },
			'error-callback': () => { turnstileToken = ''; },
		});
	}

	function resetTurnstile() {
		turnstileToken = '';
		const w = (window as any).turnstile;
		if (w && turnstileWidgetId !== null) {
			w.reset(turnstileWidgetId);
		}
	}

	function validate(): string | null {
		for (const field of formConfig.fields) {
			const val = (values[field.name] ?? '').trim();
			if (field.required && !val) {
				return `${field.label} is required`;
			}
			if (field.type === 'email' && val && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
				return `Please enter a valid email address`;
			}
		}
		return null;
	}

	async function handleSubmit(e: Event) {
		e.preventDefault();
		if (submitting || submitted || !turnstileReady) return;

		const validationError = validate();
		if (validationError) {
			error = validationError;
			return;
		}

		submitting = true;
		error = null;

		try {
			const body: Record<string, unknown> = {
				fields: values,
				recipient_email: formConfig.recipient_email,
				subject_prefix: formConfig.subject_prefix,
			};

			if (turnstileToken) body.turnstile_token = turnstileToken;

			const res = await fetch('/wp-json/wchs/v1/contact', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				credentials: 'include',
				body: JSON.stringify(body),
			});

			if (!res.ok) {
				const data = await res.json().catch(() => null);
				throw new Error(data?.message || `Submission failed (${res.status})`);
			}

			submitted = true;
			// GA4 generate_lead event
			if (typeof window !== 'undefined' && window.dataLayer) {
				window.dataLayer.push({
					event: 'generate_lead',
					lead_source: 'contact_form',
				});
			}
			const emailField = formConfig.fields.find((f) => f.type === 'email' || /email/i.test(f.name));
			const phoneField = formConfig.fields.find((f) => /phone/i.test(f.name));
			const leadEmail = emailField ? (values[emailField.name] ?? '').trim() : '';
			const leadPhone = phoneField ? (values[phoneField.name] ?? '').trim() : '';
			if (leadEmail || leadPhone) {
				import('$lib/analytics').then((a) => {
					a.trackTriplePixelContact({ email: leadEmail || undefined, phone: leadPhone || undefined });
				}).catch(() => {});
			}
		} catch (e) {
			error = e instanceof Error ? e.message : 'Something went wrong. Please try again.';
			resetTurnstile();
		} finally {
			submitting = false;
		}
	}
</script>

{#if formConfig.fields.length > 0}
	<section class="contact-form" class:is-v-compact={spacing_v === 'compact'} class:is-v-spacious={spacing_v === 'spacious'} class:is-h-compact={spacing_h === 'compact'} class:is-h-spacious={spacing_h === 'spacious'} style={accentStyle}>
		{#if formConfig.title}
			<p class="contact-form__label" class:is-centered={center_header}>{formConfig.title}</p>
		{/if}

		{#if submitted}
			<div class="contact-form__success">
				<svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
				<p>{formConfig.success_message || 'Thank you! Your message has been sent.'}</p>
			</div>
		{:else}
			<form class="contact-form__form" onsubmit={handleSubmit} novalidate>
				{#each formConfig.fields as field}
					<div class="contact-form__field">
						<label class="contact-form__field-label" for="cf-{field.name}">
							{field.label}
							{#if field.required}<span class="contact-form__required">*</span>{/if}
						</label>
						{#if field.type === 'textarea'}
							<textarea
								id="cf-{field.name}"
								class="contact-form__input"
								bind:value={values[field.name]}
								required={field.required}
								rows="4"
							></textarea>
						{:else}
							<input
								type={field.type === 'email' ? 'email' : 'text'}
								id="cf-{field.name}"
								class="contact-form__input"
								bind:value={values[field.name]}
								required={field.required}
							/>
						{/if}
					</div>
				{/each}

				{#if siteKey}
					<div class="contact-form__turnstile" bind:this={turnstileEl}></div>
				{/if}

				{#if error}
					<p class="contact-form__error">{error}</p>
				{/if}

				<button type="submit" class="contact-form__submit" disabled={submitting || !turnstileReady}>
					{#if submitting}
						Sending...
					{:else if turnstileRequired && !turnstileReady}
						Verify you're human first
					{:else}
						Send Message
					{/if}
				</button>
			</form>
		{/if}
	</section>
{/if}

<style>
	.contact-form {
		--mod-pt: 32px;
		--mod-pb: 40px;
		--mod-px: 28px;
		--mod-max-w: 960px;
		max-width: var(--mod-max-w);
		width: 100%;
		margin: 0 auto;
		padding: var(--mod-pt) var(--mod-px) var(--mod-pb);
	}
	.contact-form.is-v-compact  { --mod-pt: 12px; --mod-pb: 12px; }
	.contact-form.is-v-spacious { --mod-pt: 56px; --mod-pb: 64px; }
	.contact-form.is-h-compact  { --mod-max-w: 100%; --mod-px: 12px; }
	.contact-form.is-h-spacious { --mod-max-w: 760px; --mod-px: 40px; }
	.contact-form__label {
		font-size: 12px;
		font-weight: 500;
		text-transform: uppercase;
		letter-spacing: 0.1em;
		color: var(--fg-muted);
		margin: 0 0 24px;
	}
	.contact-form__label.is-centered {
		text-align: center;
	}
	.contact-form__form {
		display: flex;
		flex-direction: column;
		gap: 16px;
	}
	.contact-form__field-label {
		display: block;
		font-size: 11px;
		font-weight: 500;
		text-transform: uppercase;
		letter-spacing: 0.08em;
		color: var(--fg-muted);
		margin-bottom: 6px;
	}
	.contact-form__required {
		color: var(--danger, #dc2626);
	}
	.contact-form__input {
		width: 100%;
		padding: 11px 14px;
		background: var(--bg);
		color: var(--fg);
		border: 1px solid var(--border);
		border-radius: var(--radius-sm);
		font-family: var(--font-sans);
		font-size: 14px;
		transition: border-color var(--dur-fast) var(--ease);
		resize: vertical;
	}
	.contact-form__input:focus {
		outline: none;
		border-color: var(--fg);
	}
	select.contact-form__input {
		cursor: pointer;
	}
	.contact-form__turnstile {
		margin: 4px 0;
	}
	.contact-form__error {
		font-size: 13px;
		color: var(--danger, #dc2626);
		margin: 0;
	}
	.contact-form__submit {
		padding: 14px 28px;
		background: var(--accent);
		color: var(--accent-fg);
		border: 1px solid var(--accent);
		border-radius: var(--radius-sm);
		font: inherit;
		font-size: 12px;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.1em;
		cursor: pointer;
		transition: background var(--dur-fast) var(--ease), color var(--dur-fast) var(--ease);
	}
	.contact-form__submit:hover:not(:disabled) {
		background: transparent;
		color: var(--accent);
	}
	.contact-form__submit:disabled {
		opacity: 0.5;
		cursor: not-allowed;
	}
	.contact-form__success {
		text-align: center;
		padding: 48px 24px;
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 16px;
		color: var(--success, #059669);
	}
	.contact-form__success p {
		font-size: 15px;
		color: var(--fg);
		margin: 0;
	}
</style>
