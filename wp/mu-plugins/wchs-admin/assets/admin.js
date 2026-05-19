(function () {
	'use strict';

	// ── Toast notification system ─────────────────────────────
	var toastContainer;
	window.wchsToast = function (message, type, duration) {
		type = type || 'success';
		duration = duration || 3000;
		if (!toastContainer) {
			toastContainer = document.createElement('div');
			toastContainer.className = 'wchs-toast-container';
			document.body.appendChild(toastContainer);
		}
		var toast = document.createElement('div');
		toast.className = 'wchs-toast wchs-toast--' + type;
		var icons = {
			success: '<svg viewBox="0 0 16 16" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 8 6.5 11.5 13 4.5"/></svg>',
			error: '<svg viewBox="0 0 16 16" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round"><path d="M4 4l8 8M12 4l-8 8"/></svg>',
		};
		toast.innerHTML = (icons[type] || icons.success) + '<span>' + message + '</span><div class="wchs-toast__progress" style="animation-duration:' + duration + 'ms"></div>';
		toastContainer.appendChild(toast);
		var dismiss = function () {
			toast.classList.add('is-leaving');
			toast.addEventListener('animationend', function () { toast.remove(); });
		};
		var timer = setTimeout(dismiss, duration);
		toast.addEventListener('click', function () { clearTimeout(timer); dismiss(); });
	};

	// ── Collapsible sections + context-aware canvas preview ─────
	// A section with data-preview-path switches the artboard iframe to
	// a dedicated preview route while expanded (e.g. Product card →
	// /preview/product-card). Collapsing reverts to the original src.
	document.addEventListener('click', function (e) {
		var toggle = e.target.closest('.wchs-section__toggle');
		if (!toggle) return;
		var section = toggle.closest('.wchs-section');
		section.classList.toggle('wchs-section--collapsed');
		var previewPath = section.dataset.previewPath;
		if (!previewPath) return;
		var iframes = document.querySelectorAll('.wchs-artboard__iframe');
		var expanded = !section.classList.contains('wchs-section--collapsed');
		iframes.forEach(function (ifr) {
			if (expanded) {
				if (!ifr.dataset.originalSrc) ifr.dataset.originalSrc = ifr.src;
				var origin = new URL(ifr.src).origin;
				var themeQ = (typeof window.__wchsPreviewTheme === 'function')
					? '&theme=' + encodeURIComponent(window.__wchsPreviewTheme())
					: '';
				ifr.src = origin + previewPath + '?preview=1' + themeQ;
			} else if (ifr.dataset.originalSrc) {
				ifr.src = ifr.dataset.originalSrc;
				delete ifr.dataset.originalSrc;
			}
		});
	});

	// ── Icon picker (trigger + popover) ────────────────────────
	document.addEventListener('click', function (e) {
		// Toggle popover on trigger click
		var trigger = e.target.closest('.wchs-icon-picker__trigger');
		if (trigger) {
			var popover = trigger.closest('.wchs-icon-picker').querySelector('.wchs-icon-picker__popover');
			// Close all other popovers first
			document.querySelectorAll('.wchs-icon-picker__popover.is-open').forEach(function (p) {
				if (p !== popover) p.classList.remove('is-open');
			});
			// Force reflow for animation on open
			if (!popover.classList.contains('is-open')) {
				popover.style.display = 'flex';
				popover.offsetHeight; // reflow
				popover.classList.add('is-open');
			} else {
				popover.classList.remove('is-open');
				setTimeout(function () { popover.style.display = ''; }, 150);
			}
			return;
		}

		// Select icon from popover
		var btn = e.target.closest('.wchs-icon-picker__btn');
		if (btn) {
			var picker = btn.closest('.wchs-icon-picker');
			var popover = picker.querySelector('.wchs-icon-picker__popover');
			var hidden = picker.querySelector('.wchs-icon-picker__value');
			var preview = picker.querySelector('.wchs-icon-picker__preview');

			picker.querySelectorAll('.wchs-icon-picker__btn').forEach(function (b) { b.classList.remove('is-selected'); });
			btn.classList.add('is-selected');

			var iconName = btn.dataset.icon;
			if (hidden) {
				hidden.value = iconName;
				hidden.dispatchEvent(new Event('input', { bubbles: true }));
			}

			// Update trigger preview
			if (preview) {
				if (iconName && btn.querySelector('svg')) {
					preview.innerHTML = btn.querySelector('svg').outerHTML.replace(/width="\d+"/, 'width="18"').replace(/height="\d+"/, 'height="18"');
				} else {
					preview.textContent = 'No icon';
				}
			}

			// Close popover
			popover.classList.remove('is-open');
			setTimeout(function () { popover.style.display = ''; }, 150);
			return;
		}

		// Close popovers when clicking outside
		if (!e.target.closest('.wchs-icon-picker')) {
			document.querySelectorAll('.wchs-icon-picker__popover.is-open').forEach(function (p) {
				p.classList.remove('is-open');
				setTimeout(function () { p.style.display = ''; }, 150);
			});
		}
	});

	// ── Accent color swatches ──────────────────────────────────
	var swatches = document.querySelectorAll('.wchs-swatch');
	var accentInput = document.getElementById('wchs-accent-color');
	swatches.forEach(function (s) {
		s.addEventListener('click', function () {
			swatches.forEach(function (x) { x.classList.remove('active'); });
			s.classList.add('active');
			if (accentInput) {
				accentInput.value = s.dataset.color || '';
				// Synthetic input event so the panel's scheduleSync listener
				// picks up the change — programmatic `.value = ` assignments
				// don't fire input/change on their own.
				accentInput.dispatchEvent(new Event('input', { bubbles: true }));
			}
		});
	});

	// ── Per-module accent-override swatches (inside module modals) ──
	// Delegated because the modal body is cloned at edit time, so swatches
	// don't exist at document-load. Find the sibling hidden input within
	// the same .wchs-field to set its value + fire input so streamModule
	// picks it up for live preview.
	document.addEventListener('click', function (e) {
		var s = e.target.closest('.wchs-override-swatch');
		if (!s) return;
		e.preventDefault();
		var wrap = s.closest('.wchs-overrides-row') || s.closest('.wchs-field');
		if (!wrap) return;
		wrap.querySelectorAll('.wchs-override-swatch').forEach(function (x) { x.classList.remove('active'); });
		s.classList.add('active');
		var hidden = wrap.querySelector('[data-field="overrides_accent_color"]');
		if (hidden) {
			hidden.value = s.dataset.overrideValue || '';
			hidden.dispatchEvent(new Event('input', { bubbles: true }));
		}
	});

	// ── Module source conditional fields (used inside modal) ──
	function updateSourceFields(el) {
		var source = el.querySelector('[data-role="source"]');
		var catField = el.querySelector('[data-role="category-field"]');
		var idsField = el.querySelector('[data-role="ids-field"]');
		if (!source) return;
		var val = source.value;
		if (catField) catField.style.display = val === 'category' ? '' : 'none';
		if (idsField) idsField.style.display = val === 'manual' ? '' : 'none';
	}

	// ── Module Manager ────────────────────────────────────────
	// Unified system for Homepage, Shop, PDP, Pages module lists.
	// Renders a compact sortable list + modal editor backed by a
	// single hidden JSON field per list.

	var TYPE_LABELS = {
		hero: 'Hero',
		product_slider: 'Product Slider', review_slider: 'Review Slider',
		listicle: 'Listicle',
		promo_offer: 'Promo offer (split)',
		reviews_listicle: 'Reviews listicle',
		listicle_faqs: 'Listicle FAQs',
		accordion: 'Accordion', trust_bar: 'Trust Bar', text_block: 'Text Block',
		gallery: 'Gallery', contact_form: 'Contact Form', shop_grid: 'Shop Grid',
		category_grid: 'Category Grid', 		split_features: 'Split Features',
		split_value: 'Value split (BOGO)',
		feature_highlights: 'Feature highlights',
		order_handling: 'Order handling',
		cta: 'CTA button', spacer: 'Spacer', logo_strip: 'Logo strip',
		video: 'Video / embed'
	};

	var TYPE_CATEGORY = {
		hero: 'branding', trust_bar: 'branding', logo_strip: 'branding',
		product_slider: 'commerce', review_slider: 'commerce',
		shop_grid: 'commerce', category_grid: 'commerce',
		accordion: 'content', listicle: 'content', reviews_listicle: 'content', listicle_faqs: 'content', text_block: 'content', gallery: 'content',
		promo_offer: 'commerce',
		split_features: 'content', split_value: 'commerce', feature_highlights: 'content',
		order_handling: 'content',
		cta: 'content', spacer: 'content',
		video: 'content',
		contact_form: 'engagement'
	};

	// Which contexts (page types) a given module may be inserted into.
	// Mirror of supports.contexts on the PHP side so the admin insert-menu
	// can filter without needing to fetch the registry.
	var TYPE_CONTEXTS = {
		hero: ['homepage','shop','pdp','pages'],
		trust_bar: ['homepage','shop','pdp','pages'],
		split_features: ['homepage','shop','pdp','pages'],
		split_value: ['homepage','shop','pdp','pages'],
		feature_highlights: ['homepage','shop','pdp','pages'],
		order_handling: ['homepage','shop','pdp','pages'],
		product_slider: ['homepage','shop','pdp','pages'],
		review_slider: ['homepage','shop','pdp','pages'],
		text_block: ['homepage','shop','pdp','pages'],
		listicle: ['homepage','shop','pdp','pages'],
		promo_offer: ['homepage','shop','pdp','pages'],
		reviews_listicle: ['homepage','shop','pdp','pages'],
		listicle_faqs: ['homepage','shop','pdp','pages'],
		accordion: ['homepage','shop','pdp','pages'],
		gallery: ['homepage','shop','pdp','pages'],
		cta: ['homepage','shop','pdp','pages'],
		spacer: ['homepage','shop','pdp','pages'],
		logo_strip: ['homepage','shop','pdp','pages'],
		video: ['homepage','shop','pdp','pages'],
		shop_grid: ['homepage', 'shop'],
		category_grid: ['homepage','pages'],
		contact_form: ['pages']
	};

	// Sensible per-type defaults for a newly-added module. Most types
	// just need an empty title; contact_form benefits from pre-populating
	// the recipient email + standard fields so the admin doesn't have
	// to type everything from scratch.
	function defaultConfigFor(type) {
		if (type === 'contact_form') {
			var site = (window.wchsAdmin && window.wchsAdmin.siteName) || '';
			var admin = (window.wchsAdmin && window.wchsAdmin.adminEmail) || '';
			return {
				title: 'Get in touch',
				recipient_email: admin,
				subject_prefix: site ? '[' + site + ']' : '',
				success_message: 'Thanks — we\'ll get back to you shortly.',
				fields: [
					{ name: 'name', label: 'Your Name', type: 'text', required: true },
					{ name: 'email', label: 'Email Address', type: 'email', required: true },
					{ name: 'subject', label: 'Subject', type: 'text', required: false },
					{ name: 'message', label: 'Message', type: 'textarea', required: true },
				],
			};
		}
		if (type === 'hero') {
			return {
				image_desktop: '', image_mobile: '',
				image_position_x: 50, image_position_y: 50, image_zoom: 100,
				variant: 'text-only',
				headline: 'New hero section',
				subheadline: '',
				show_cta: true, cta_text: 'Learn more', cta_link: '#',
				layout: 'left',
				headline_size: 'l', headline_weight: 'medium', headline_font: 'inter',
				subheadline_size: 'm', text_color_mode: 'theme',
			};
		}
		if (type === 'cta') {
			return {
				label: 'Shop now', href: '/shop',
				style: 'primary', size: 'md', align: 'center',
				open_new_tab: false,
			};
		}
		if (type === 'spacer') {
			return { height: 40 };
		}
		if (type === 'logo_strip') {
			return { title: '', grayscale: true, items: [] };
		}
		if (type === 'video') {
			return {
				title: '', source_url: '', poster_url: '',
				aspect_ratio: '16/9', autoplay: false, muted: true, loop: false, controls: true,
			};
		}
		if (type === 'split_value') {
			return {
				rating_line: 'Rated 4.98/5 · 24,987+ reviews',
				headline_prefix: 'A Leading Provider of Research Grade',
				headline_accent: 'Peptides.',
				accent_underline: true,
				bullets: [
					{ text: 'Fast U.S. Shipping' },
					{ text: '99% Tested Purity' },
					{ text: 'Made in USA' },
				],
				cta_label: 'Buy 1 Get 1 Free',
				cta_href: '/shop',
				trust_note: 'Research use only. All major credit/debit cards, PayPal, ACH, BTC, Zelle.',
				promo_badge_eyebrow: 'LIMITED TIME',
				promo_badge_title: 'Buy 1 Get 1 Free',
				image: '/wp-content/uploads/2026/05/e33abf7d-1bcf-42ea-b324-c777cec4006d.webp',
				image_alt: 'Research-grade peptides — product lineup',
				stats: [
					{ value: '99%', label: 'Purity' },
					{ value: '24.9K+', label: 'Reviews' },
					{ value: 'Triple-Tested', label: 'for Quality' },
				],
			};
		}
		if (type === 'feature_highlights') {
			return {
				badge_text: 'Verified & Trusted',
				headline_prefix: 'The Standard for ',
				headline_accent: 'Verified Peptides',
				subheadline: 'Independent testing. Full batch documentation. Reliable, tracked delivery.',
				items: [
					{ variant: 'pin', headline: 'USA Manufactured', description: 'Synthesized and packaged domestically. No overseas sourcing.' },
					{ variant: 'star', headline: '5-Star Reviewed', description: 'Rated 5 stars by verified customers.' },
					{ variant: 'lab', headline: 'Third-Party Lab Tested', description: 'Every batch independently verified before shipping.' },
					{ variant: 'award', headline: 'Triple-Tested for Quality', description: 'Purity, Content, and Endotoxin testing on every product.' },
				],
				cta_label: 'Buy 1 Get 1 Free',
				cta_href: '/shop',
			};
		}
		if (type === 'listicle') {
			return {
				section_eyebrow: '',
				headline: '5 Reasons Researchers Choose Verified Peptide Suppliers Over Gray-Market Listings',
				intro: '<p>Many labs still source peptides from unverified sellers because the price looks right and the listing looks legitimate.</p><p>That shortcut often means missing batch documentation, inconsistent purity claims, and no traceable COA before you commit budget to a run.</p>',
				items_headline: 'Here is why more research teams standardize on documented, batch-tested supply:',
				closing: '<p>So why have more research teams switched to Alyve Peptides? Because documented purity, published COAs, and predictable domestic fulfillment are not extras—they are the baseline.</p>',
				items: [
					{
						headline: 'Unverified purity claims can invalidate your data.',
						body: '<p>Your outcomes depend on what is actually in the vial. Without independent testing on every batch, you are trusting a label—not a lab result.</p>',
					},
					{
						headline: 'No COA before purchase means no audit trail.',
						body: '<p>Reputable suppliers publish Certificates of Analysis tied to batch numbers before you buy. Gray-market listings rarely offer the same transparency.</p>',
					},
					{
						headline: 'Inconsistent sourcing slows every experiment cycle.',
						body: '<p>Switching vendors mid-study introduces variables you cannot control. A single catalog with documented batches keeps your team focused on research.</p>',
					},
					{
						headline: 'Research-use standards matter for your reputation.',
						body: '<p>Materials labeled and handled for research use, with clear disclaimers and batch traceability, reduce ambiguity for PI review and institutional policy.</p>',
					},
					{
						headline: 'Verified supply is faster to trust than faster to ship.',
						body: '<p>Tracked domestic shipping matters—but only after purity and documentation are settled.</p>',
					},
				],
				cta_label: 'Shop research-grade peptides',
				cta_href: '/shop',
			};
		}
		if (type === 'listicle_faqs') {
			return {
				eyebrow: 'COMMON QUESTIONS',
				headline: 'What researchers ask before ordering',
				items: [
					{
						q: 'How much bacteriostatic water do I use to reconstitute?',
						a: '<p>Follow the reconstitution guidance on your product label and COA. A common starting point for research vials is adding the volume of bacteriostatic water (BAC) that yields your target concentration—many protocols use 1–2 mL per vial, but always defer to the compound-specific instructions supplied with the batch.</p>',
					},
					{
						q: 'Are COAs available before I purchase?',
						a: '<p>Yes. Every batch is tested by independent third-party laboratories. Certificates of Analysis confirming purity are published on product pages and available before you add items to cart.</p>',
					},
					{
						q: 'What is your return policy?',
						a: '<p>Unopened products in original packaging may be returned within 30 days of delivery. Opened or reconstituted materials cannot be accepted due to research-use handling requirements. Contact support with your order number to start a return.</p>',
					},
					{
						q: 'How quickly does the order ship?',
						a: '<p>Orders ship within 1–2 business days from our U.S. facility. You will receive tracking as soon as the carrier scans the package. Free shipping applies on qualifying order totals shown at checkout.</p>',
					},
					{
						q: 'What testing methodology do you use to verify purity?',
						a: '<p>Each batch undergoes third-party laboratory testing—including HPLC for purity and identity—with results documented on the COA. We publish methodology summaries and batch numbers so your team can align material qualification with your protocol.</p>',
					},
				],
			};
		}
		if (type === 'reviews_listicle') {
			return {
				headline: 'Amazing Reviews with a 4.9 Rating',
				items: [
					{
						quote: 'COAs matched the batch numbers on our vials. Documentation was clear and easy to file for our lab records.',
						name: 'Vincent R.',
						rating: 5,
					},
					{
						quote: 'Ordering was straightforward and fulfillment was faster than our previous supplier. Purity reports were posted before we checked out.',
						name: 'Justin F.',
						rating: 5,
					},
					{
						quote: 'Consistent quality across reorders — no surprises between batches. Support answered technical questions the same day.',
						name: 'Carlos B.',
						rating: 5,
					},
				],
			};
		}
		if (type === 'promo_offer') {
			return {
				intro_headline: '',
				intro_subheadline:
					'✨ We don\'t hand these out every day… consider this an exclusive Alyve hookup, just for you.',
				badge_text: 'LIMITED TIME OFFER ✨',
				image: '/wp-content/uploads/2026/05/e33abf7d-1bcf-42ea-b324-c777cec4006d.webp',
				image_alt: 'Alyve research-grade peptides — Buy one get one free',
				ribbon_text: 'PUBLISHED COAs + BATCH DOCS WITH EVERY ORDER',
				offer_primary: 'UP TO 40% OFF',
				offer_secondary: 'FOR A LIMITED TIME ONLY!',
				scarcity_text: 'High demand — popular batches sell out quickly.',
				cta_label: 'GET 40% OFF',
				cta_href: '/shop',
				show_countdown: true,
				countdown_end_at: '',
				status_label: 'Sell-out risk:',
				status_value: 'High',
				status_note: 'Faster shipping',
				footer_text: 'Try it today with a 60-Day Money-Back Guarantee!',
			};
		}
		if (type === 'order_handling') {
			return {
				badge_text: 'Our Process',
				headline: 'How Every Order Is Handled',
				subheadline: 'From verification to delivery, we ensure each step meets our highest standards.',
				steps: [
					{ variant: 'verified', headline: 'Verified Batches', description: 'Every batch undergoes rigorous quality control and verification before release.' },
					{ variant: 'lab', headline: '3rd Party Testing', description: 'Independent laboratory testing ensures purity and consistency you can trust.' },
					{ variant: 'shipping', headline: 'Ships Same Day', description: 'Discreetly packaged and dispatched within 24 hours from our U.S. facility.' },
					{ variant: 'support', headline: '24/7 Support', description: 'Round-the-clock customer service for any questions before or after your order.' },
				],
				metrics_title: 'Quality Metrics',
				metrics: [
					{ value: '99.8%', label: 'Batch Accuracy' },
					{ value: '100%', label: 'Verified Testing' },
					{ value: '24/7', label: 'Support Response' },
				],
			};
		}
		if (type === 'split_features') {
			return {
				layout: 'alternating',
				headline: '',
				subtitle: '',
				brand_name: '',
				competitor_name: 'Unverified Sellers',
				brand_logo: '',
				competitor_logo: '',
				title: '',
				items: [],
			};
		}
		if (type === 'text_block') {
			return {
				layout: 'auto',
				title: '',
				headline: '',
				content: '',
				brand_name: '',
				competitor_name: 'Unverified Sellers',
				brand_logo: '',
				competitor_logo: '',
				comparison_rows: [],
			};
		}
		if (type === 'category_grid') {
			return { title: '', columns: 4, gap: 12, items: [] };
		}
		return { title: '' };
	}

	// Global modal element (created once, shared by all managers)
	var modal = null;
	var modalBackdrop = null;
	var modalCallback = null; // fn(moduleData) called on save

	function getModal() {
		if (modal) return modal;
		modalBackdrop = document.createElement('div');
		modalBackdrop.className = 'wchs-modal-backdrop';
		modalBackdrop.innerHTML = '<div class="wchs-modal">'
			+ '<div class="wchs-modal__header"><h3 class="wchs-modal__title"></h3><button type="button" class="wchs-modal__close">&times;</button></div>'
			+ '<div class="wchs-modal__body"></div>'
			+ '<div class="wchs-modal__footer">'
			+ '<button type="button" class="wchs-btn wchs-btn--secondary wchs-modal__cancel">Cancel</button>'
			+ '<button type="button" class="wchs-btn wchs-btn--primary wchs-modal__save">Apply</button>'
			+ '</div></div>';
		document.body.appendChild(modalBackdrop);
		modal = modalBackdrop.querySelector('.wchs-modal');

		// Close handlers
		modalBackdrop.querySelector('.wchs-modal__close').addEventListener('click', closeModal);
		modalBackdrop.querySelector('.wchs-modal__cancel').addEventListener('click', closeModal);
		var mouseDownTarget = null;
		modalBackdrop.addEventListener('mousedown', function (e) { mouseDownTarget = e.target; });
		modalBackdrop.addEventListener('click', function (e) { if (e.target === modalBackdrop && mouseDownTarget === modalBackdrop) closeModal(); });
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && modalBackdrop.classList.contains('is-open')) closeModal(); });

		// Save handler
		modalBackdrop.querySelector('.wchs-modal__save').addEventListener('click', function () {
			if (modalCallback) modalCallback();
		});

		return modal;
	}

	function openModal(title) {
		var m = getModal();
		m.querySelector('.wchs-modal__title').textContent = title;
		modalBackdrop.classList.add('is-open');
		document.body.style.overflow = 'hidden';
	}

	function closeModal(force) {
		if (!modalBackdrop) return;
		if (!force && modalDirty) {
			var ok = window.confirm('Discard unsaved module changes?');
			if (!ok) return;
		}
		clearTimeout(modalStreamTimer);
		modalStreamTimer = null;
		destroyModalWysiwyg();
		modalBackdrop.classList.remove('is-open');
		document.body.style.overflow = '';
		modalCallback = null;
		modalDirty = false;
		// Clear body
		var body = modal.querySelector('.wchs-modal__body');
		body.innerHTML = '';
	}

	/* ── WYSIWYG (TinyMCE) lifecycle for modal textareas ── */
	var wysiwygCounter = 0;

	function initModalWysiwyg(container) {
		if (typeof tinymce === 'undefined') return;
		container.querySelectorAll('textarea[data-wysiwyg="1"]').forEach(function (ta) {
			var id = 'wchs-mce-' + (++wysiwygCounter);
			ta.id = id;
			tinymce.init({
				selector: '#' + id,
				menubar: false,
				statusbar: false,
				toolbar: 'bold italic underline | bullist numlist | link | removeformat',
				plugins: 'lists link',
				height: parseInt(ta.getAttribute('rows') || '4') > 5 ? 280 : 150,
				content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; font-size: 14px; line-height: 1.6; color: #1e293b; }',
				branding: false,
				promotion: false,
				setup: function (editor) {
					editor.on('change keyup blur', function () { editor.save(); });
				}
			});
		});
	}

	function destroyModalWysiwyg() {
		if (typeof tinymce === 'undefined') return;
		tinymce.get().forEach(function (editor) {
			if (editor.id && editor.id.indexOf('wchs-mce-') === 0) {
				editor.remove();
			}
		});
	}

	function syncWysiwygInContainer(container) {
		if (typeof tinymce === 'undefined') return;
		container.querySelectorAll('textarea[data-wysiwyg="1"]').forEach(function (ta) {
			if (ta.id && tinymce.get(ta.id)) {
				tinymce.get(ta.id).save();
			}
		});
	}

	function destroyWysiwygInElement(el) {
		if (typeof tinymce === 'undefined') return;
		el.querySelectorAll('textarea[data-wysiwyg="1"]').forEach(function (ta) {
			if (ta.id && tinymce.get(ta.id)) {
				tinymce.get(ta.id).remove();
			}
		});
	}

	function showTypePicker(onPick) {
		var m = getModal();
		var body = m.querySelector('.wchs-modal__body');
		var grid = document.createElement('div');
		grid.className = 'wchs-type-picker';
		Object.keys(TYPE_LABELS).forEach(function (type) {
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'wchs-type-picker__btn';
			btn.textContent = TYPE_LABELS[type];
			btn.addEventListener('click', function () { onPick(type); });
			grid.appendChild(btn);
		});
		body.innerHTML = '';
		body.appendChild(grid);
		m.querySelector('.wchs-modal__save').style.display = 'none';
		openModal('Add Module');
	}

	// ── Keyboard shortcut help overlay ────────────────────────
	// `?` opens a modal listing every shortcut the admin supports.
	// Uses the existing modal plumbing (getModal) so styling is consistent.
	var SHORTCUTS = [
		{ keys: 'Cmd / Ctrl + S',        action: 'Save' },
		{ keys: 'Cmd / Ctrl + Z',        action: 'Undo' },
		{ keys: 'Cmd / Ctrl + Shift + Z', action: 'Redo' },
		{ keys: 'Cmd / Ctrl + C',        action: 'Copy focused module' },
		{ keys: 'Cmd / Ctrl + V',        action: 'Paste module' },
		{ keys: '/',                      action: 'Open insert menu' },
		{ keys: '?',                      action: 'Show this help' },
		{ keys: 'Esc',                    action: 'Close modal / menu' },
		{ keys: 'Drag ⠿',                 action: 'Reorder modules' },
	];
	function showShortcutHelp() {
		var m = getModal();
		var body = m.querySelector('.wchs-modal__body');
		var wrap = document.createElement('div');
		wrap.className = 'wchs-shortcut-help';
		var rows = SHORTCUTS.map(function (s) {
			return '<div class="wchs-shortcut-help__row">'
				+ '<kbd class="wchs-shortcut-help__keys">' + escHtml(s.keys) + '</kbd>'
				+ '<span class="wchs-shortcut-help__action">' + escHtml(s.action) + '</span>'
				+ '</div>';
		}).join('');
		wrap.innerHTML = rows;
		body.innerHTML = '';
		body.appendChild(wrap);
		m.querySelector('.wchs-modal__save').style.display = 'none';
		openModal('Keyboard shortcuts');
	}

	// ── Hint-icon tooltip (body-portal) ───────────────────────
	// A single <div> appended to <body> serves every .wchs-hint-icon.
	// Escapes ancestor overflow:auto clipping that plagued the old
	// ::after tooltip, and clamps to the viewport so tooltips never
	// run off-screen regardless of icon position.
	var hintTipEl = null;
	var hintShowTimer = null;
	function ensureHintTip() {
		if (hintTipEl) return hintTipEl;
		hintTipEl = document.createElement('div');
		hintTipEl.className = 'wchs-hint-tip';
		hintTipEl.setAttribute('role', 'tooltip');
		document.body.appendChild(hintTipEl);
		return hintTipEl;
	}
	function showHintTip(icon) {
		var text = icon.getAttribute('data-tip') || '';
		if (!text) return;
		var t = ensureHintTip();
		t.textContent = text;
		// Measure first — is-measuring renders it at layout size without
		// showing to the eye, so we can read getBoundingClientRect and
		// then commit a clamped position before fading in.
		t.classList.remove('is-visible');
		t.classList.add('is-measuring');
		// Reset transform var before measuring so prior positions don't skew
		t.style.setProperty('--wchs-tip-x', '0px');
		t.style.setProperty('--wchs-tip-y', '0px');
		var iconRect = icon.getBoundingClientRect();
		var tipRect = t.getBoundingClientRect();
		// Preferred: below + centered on icon
		var x = iconRect.left + iconRect.width / 2 - tipRect.width / 2;
		var y = iconRect.bottom + 8;
		// Clamp horizontally to 8px viewport margins
		var maxX = window.innerWidth - tipRect.width - 8;
		if (maxX < 8) maxX = 8;
		if (x < 8) x = 8;
		if (x > maxX) x = maxX;
		// Flip above if no room below
		if (y + tipRect.height + 8 > window.innerHeight) {
			y = iconRect.top - tipRect.height - 8;
			if (y < 8) y = 8;
		}
		t.style.setProperty('--wchs-tip-x', x + 'px');
		t.style.setProperty('--wchs-tip-y', y + 'px');
		t.classList.remove('is-measuring');
		t.classList.add('is-visible');
	}
	function hideHintTip() {
		if (!hintTipEl) return;
		hintTipEl.classList.remove('is-visible');
		hintTipEl.classList.remove('is-measuring');
	}
	document.addEventListener('mouseover', function (e) {
		var icon = e.target && e.target.closest && e.target.closest('.wchs-hint-icon');
		if (!icon) return;
		clearTimeout(hintShowTimer);
		hintShowTimer = setTimeout(function () { showHintTip(icon); }, 50);
	});
	document.addEventListener('mouseout', function (e) {
		var from = e.target && e.target.closest && e.target.closest('.wchs-hint-icon');
		if (!from) return;
		// If moving to another descendant of the same icon, stay open
		var related = e.relatedTarget && e.relatedTarget.closest && e.relatedTarget.closest('.wchs-hint-icon');
		if (related === from) return;
		clearTimeout(hintShowTimer);
		hideHintTip();
	});
	document.addEventListener('focusin', function (e) {
		if (!e.target.classList || !e.target.classList.contains('wchs-hint-icon')) return;
		showHintTip(e.target);
	});
	document.addEventListener('focusout', function (e) {
		if (!e.target.classList || !e.target.classList.contains('wchs-hint-icon')) return;
		hideHintTip();
	});
	// Hide on scroll / resize — position becomes stale, user can re-hover.
	window.addEventListener('scroll', hideHintTip, true);
	window.addEventListener('resize', hideHintTip);
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape') hideHintTip();
	});

	// ── Toast ─────────────────────────────────────────────────
	var toastEl = null;
	var toastTimer = null;
	function toast(msg, kind) {
		if (!toastEl) {
			toastEl = document.createElement('div');
			toastEl.className = 'wchs-toast';
			document.body.appendChild(toastEl);
		}
		toastEl.textContent = msg;
		toastEl.dataset.kind = kind || 'info';
		toastEl.classList.add('is-visible');
		clearTimeout(toastTimer);
		toastTimer = setTimeout(function () { toastEl.classList.remove('is-visible'); }, 1600);
	}

	// ── Clipboard (module copy/paste) ─────────────────────────
	var CLIPBOARD_KEY = 'wchs_clipboard_module';
	function writeClipboard(mod) {
		try { localStorage.setItem(CLIPBOARD_KEY, JSON.stringify(mod)); return true; }
		catch (e) { return false; }
	}
	function readClipboard() {
		try {
			var raw = localStorage.getItem(CLIPBOARD_KEY);
			if (!raw) return null;
			var parsed = JSON.parse(raw);
			return (parsed && typeof parsed === 'object' && parsed.type) ? parsed : null;
		} catch (e) { return null; }
	}
	function clipboardAllowedIn(context) {
		var c = readClipboard();
		if (!c) return null;
		var allowed = TYPE_CONTEXTS[c.type];
		if (!allowed || !allowed.length) return c; // default = allowed everywhere
		return allowed.indexOf(context) >= 0 ? c : null;
	}

	// ── Presets ───────────────────────────────────────────────
	var PRESETS_KEY = 'wchs_presets';
	var PRESETS_MAX = 25;
	function readPresets() {
		try {
			var raw = localStorage.getItem(PRESETS_KEY);
			var arr = raw ? JSON.parse(raw) : [];
			return Array.isArray(arr) ? arr : [];
		} catch (e) { return []; }
	}
	function writePresets(list) {
		try {
			// LRU: cap at PRESETS_MAX, keeping the most-recently-created ones.
			if (list.length > PRESETS_MAX) list = list.slice(-PRESETS_MAX);
			localStorage.setItem(PRESETS_KEY, JSON.stringify(list));
			return true;
		} catch (e) { return false; }
	}
	function savePreset(mod) {
		// Minimal validation — must have a type we recognize.
		if (!mod || !TYPE_LABELS[mod.type]) return;
		var name = window.prompt('Name this preset:', TYPE_LABELS[mod.type]);
		if (!name) return;
		name = String(name).trim().slice(0, 60);
		if (!name) return;
		var list = readPresets();
		var id = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : ('p_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8));
		list.push({
			id: id,
			name: name,
			type: mod.type,
			module: JSON.parse(JSON.stringify(mod)),
			created: Date.now(),
		});
		if (writePresets(list)) toast('Preset saved');
		else toast('Could not save preset', 'error');
	}
	function deletePreset(id) {
		var list = readPresets().filter(function (p) { return p.id !== id; });
		writePresets(list);
	}

	// ── Insert menu (slash-style) ─────────────────────────────
	var CATEGORY_LABELS = {
		branding: 'Branding',
		content: 'Content',
		commerce: 'Commerce',
		engagement: 'Engagement',
		other: 'Other',
	};

	function buildInsertItems(context) {
		// Ordered groups: built-in categories first, then presets, then clipboard.
		var GROUP_ORDER = ['Content', 'Branding', 'Commerce', 'Engagement', 'Other', 'My presets', 'Clipboard'];
		var items = [];
		Object.keys(TYPE_LABELS).forEach(function (type) {
			var allowed = TYPE_CONTEXTS[type];
			if (allowed && allowed.indexOf(context) < 0) return;
			items.push({
				kind: 'type',
				id: 'type:' + type,
				type: type,
				name: TYPE_LABELS[type],
				group: CATEGORY_LABELS[TYPE_CATEGORY[type] || 'other'] || 'Other',
			});
		});
		readPresets().forEach(function (p) {
			var allowed = TYPE_CONTEXTS[p.type];
			if (allowed && allowed.indexOf(context) < 0) return;
			items.push({
				kind: 'preset',
				id: 'preset:' + p.id,
				type: p.type,
				name: p.name,
				presetId: p.id,
				module: p.module,
				group: 'My presets',
			});
		});
		var clip = clipboardAllowedIn(context);
		if (clip) {
			items.push({
				kind: 'paste',
				id: 'paste',
				type: clip.type,
				name: 'Paste ' + (TYPE_LABELS[clip.type] || clip.type),
				module: clip,
				group: 'Clipboard',
			});
		}
		// Sort so same-group items are contiguous in a stable, meaningful order.
		items.sort(function (a, b) {
			var ia = GROUP_ORDER.indexOf(a.group);
			var ib = GROUP_ORDER.indexOf(b.group);
			if (ia < 0) ia = 99;
			if (ib < 0) ib = 99;
			return ia - ib;
		});
		return items;
	}

	var insertMenuEl = null;
	var insertMenuOnClose = null;

	function ensureInsertMenu() {
		if (insertMenuEl) return insertMenuEl;
		insertMenuEl = document.createElement('div');
		insertMenuEl.className = 'wchs-insert-menu';
		insertMenuEl.innerHTML = '<input type="text" class="wchs-insert-menu__filter" placeholder="Type to filter…" />'
			+ '<div class="wchs-insert-menu__list"></div>';
		document.body.appendChild(insertMenuEl);
		// Close on outside click
		document.addEventListener('mousedown', function (e) {
			if (!insertMenuEl || !insertMenuEl.classList.contains('is-open')) return;
			if (insertMenuEl.contains(e.target)) return;
			closeInsertMenu();
		});
		return insertMenuEl;
	}

	function openInsertMenu(containerEl, context, anchor, onPick) {
		var el = ensureInsertMenu();
		var filter = el.querySelector('.wchs-insert-menu__filter');
		var listEl = el.querySelector('.wchs-insert-menu__list');
		var items = buildInsertItems(context);
		var query = '';
		var activeIdx = 0;

		function render() {
			var matched = items.filter(function (it) {
				if (!query) return true;
				return it.name.toLowerCase().indexOf(query) >= 0 || (TYPE_LABELS[it.type] || '').toLowerCase().indexOf(query) >= 0;
			});
			if (activeIdx >= matched.length) activeIdx = Math.max(0, matched.length - 1);
			listEl.innerHTML = '';
			var currentGroup = null;
			matched.forEach(function (it, i) {
				if (it.group !== currentGroup) {
					currentGroup = it.group;
					var head = document.createElement('div');
					head.className = 'wchs-insert-menu__group';
					head.textContent = currentGroup;
					listEl.appendChild(head);
				}
				var row = document.createElement('div');
				row.className = 'wchs-insert-menu__row' + (i === activeIdx ? ' is-active' : '');
				row.dataset.index = i;
				row.dataset.kind = it.kind;
				var hint = '';
				if (it.kind === 'paste') hint = '⌘V';
				else if (it.kind === 'preset') hint = TYPE_LABELS[it.type] || it.type;
				row.innerHTML = '<span class="wchs-insert-menu__name">' + escHtml(it.name) + '</span>'
					+ (it.kind === 'preset'
						? '<span class="wchs-insert-menu__hint">' + escHtml(hint) + '</span>'
						  + '<button type="button" class="wchs-insert-menu__del" data-id="' + escHtml(it.presetId) + '" title="Delete preset">&times;</button>'
						: (hint ? '<span class="wchs-insert-menu__hint">' + escHtml(hint) + '</span>' : ''));
				listEl.appendChild(row);
			});
			listEl._matched = matched;
		}

		function pickActive() {
			var matched = listEl._matched || [];
			var picked = matched[activeIdx];
			if (!picked) return;
			closeInsertMenu();
			onPick(picked);
		}

		// Position near anchor
		var rect = anchor.getBoundingClientRect();
		var top = Math.min(window.innerHeight - 360, rect.bottom + 4);
		var left = Math.min(window.innerWidth - 340, rect.left);
		el.style.top = top + 'px';
		el.style.left = left + 'px';
		el.classList.add('is-open');
		filter.value = '';
		query = '';
		activeIdx = 0;
		render();
		setTimeout(function () { filter.focus(); }, 0);

		filter.oninput = function () {
			query = filter.value.trim().toLowerCase();
			activeIdx = 0;
			render();
		};
		filter.onkeydown = function (e) {
			var matched = listEl._matched || [];
			if (e.key === 'ArrowDown') { e.preventDefault(); activeIdx = Math.min(matched.length - 1, activeIdx + 1); render(); }
			else if (e.key === 'ArrowUp') { e.preventDefault(); activeIdx = Math.max(0, activeIdx - 1); render(); }
			else if (e.key === 'Enter') { e.preventDefault(); pickActive(); }
			else if (e.key === 'Escape') { e.preventDefault(); closeInsertMenu(); }
		};
		listEl.onclick = function (e) {
			var del = e.target.closest('.wchs-insert-menu__del');
			if (del) {
				e.stopPropagation();
				deletePreset(del.dataset.id);
				items = buildInsertItems(context);
				render();
				toast('Preset deleted');
				return;
			}
			var row = e.target.closest('.wchs-insert-menu__row');
			if (!row) return;
			activeIdx = parseInt(row.dataset.index, 10);
			pickActive();
		};

		insertMenuOnClose = function () { /* no-op hook */ };
	}

	function closeInsertMenu() {
		if (!insertMenuEl) return;
		insertMenuEl.classList.remove('is-open');
		if (insertMenuOnClose) { insertMenuOnClose(); insertMenuOnClose = null; }
	}

	// Apply an insert selection to the module list.
	function insertFromSelection(selection, context, modules, sync) {
		if (!selection) return;
		if (selection.kind === 'type') {
			var type = selection.type;
			var newMod = { type: type, visibility: 'all', spacing_v: 'normal', spacing_h: 'normal', center_header: false, config: defaultConfigFor(type) };
			showModuleEditor(type, newMod, function (result) {
				modules.push(result);
				sync();
			}, { mode: 'add' });
			return;
		}
		if (selection.kind === 'preset' || selection.kind === 'paste') {
			// Apply preset/clipboard module directly — skip the editor so the
			// saved config lands as-is. Editor is still reachable via the edit
			// icon if the user wants to tweak.
			var clone = JSON.parse(JSON.stringify(selection.module));
			clone.type = selection.type; // defensive
			modules.push(clone);
			sync();
			toast(selection.kind === 'paste' ? 'Module pasted' : 'Preset applied');
		}
	}

	// Tracks whether the user made any change in the open modal since open.
	// Lets us warn on accidental close and suppresses the warning after a
	// successful save.
	var modalDirty = false;
	// Pending live-stream debounce timer. Cancelled on modal close so a late
	// fire doesn't read a cleared modal body and overwrite the saved data
	// with empties. Set/read by showModuleEditor + closeModal.
	var modalStreamTimer = null;

	function showModuleEditor(type, data, onSave, options) {
		var mode = (options && options.mode) || 'edit'; // 'edit' streams live; 'add' doesn't push until save
		var m = getModal();
		var body = m.querySelector('.wchs-modal__body');
		// Clone the template for this type from the template bank
		var tpl = document.getElementById('wchs-mod-tpl-' + type);
		if (!tpl) { alert('Template not found for type: ' + type); return; }
		body.innerHTML = '';
		var clone = tpl.cloneNode(true);
		clone.style.display = '';
		clone.removeAttribute('id');
		body.appendChild(clone);

		// Populate fields from data
		populateModuleFields(body, type, data);

		// Init WYSIWYG on any [data-wysiwyg="1"] textareas
		initModalWysiwyg(body);

		// Wire up conditional fields (product_slider source toggle)
		updateSourceFields(body);
		var sel = body.querySelector('[data-role="source"]');
		if (sel) sel.addEventListener('change', function () { updateSourceFields(body); });

		// Show save button
		m.querySelector('.wchs-modal__save').style.display = '';
		openModal('Edit ' + TYPE_LABELS[type]);

		modalDirty = false;

		// Live preview streams field changes to the iframe. Only fires in
		// 'edit' mode (existing module); 'add' mode defers writes until the
		// user clicks Save so we don't insert a phantom module during typing.
		function streamModule() {
			if (mode !== 'edit' || !window.wchsPushPreview) return;
			clearTimeout(modalStreamTimer);
			modalStreamTimer = setTimeout(function () {
				// Bail if modal has been closed between scheduling + firing —
				// reading an empty body would overwrite the saved row with empties.
				if (!body.isConnected || body.childElementCount === 0) return;
				var mod = readModuleFields(body, type, data);
				if (typeof onSave === 'function') {
					onSave(mod, true /* noClose */);
				}
				window.wchsPushPreview();
			}, 120);
		}
		function markDirty() {
			modalDirty = true;
			streamModule();
		}
		body.addEventListener('input', markDirty);
		body.addEventListener('change', markDirty);

		modalCallback = function () {
			var result = readModuleFields(body, type, data);
			onSave(result);
			modalDirty = false;
			closeModal();
		};
	}

	function populateModuleFields(container, type, data) {
		// Universal fields
		setVal(container, '[data-field="title"]', data.config && data.config.title || '');
		setVal(container, '[data-field="visibility"]', data.visibility || 'all');
		// Backward compat: edge_to_edge → spacing_h
		var sh = data.spacing_h || (data.edge_to_edge ? 'compact' : 'normal');
		setVal(container, '[data-field="spacing_v"]', data.spacing_v || 'normal');
		setVal(container, '[data-field="spacing_h"]', sh);
		setCheck(container, '[data-field="center_header"]', data.center_header || false);
		// Scheduled publishing — convert ISO-8601 to datetime-local format
		// (YYYY-MM-DDTHH:MM, local TZ). Storing in UTC, displaying in local.
		setVal(container, '[data-field="start_at"]', isoToLocal(data.start_at));
		setVal(container, '[data-field="end_at"]',   isoToLocal(data.end_at));

		// Overrides — restore module.overrides.accent_color into the hidden
		// input + mark the matching swatch active. Phase-2 loop close.
		var overrideAccent = (data.overrides && data.overrides.accent_color) || '';
		setVal(container, '[data-field="overrides_accent_color"]', overrideAccent);
		var swatches = container.querySelectorAll('.wchs-override-swatch');
		swatches.forEach(function (s) {
			s.classList.toggle('active', (s.dataset.overrideValue || '') === overrideAccent);
		});

		var cfg = data.config || {};

		switch (type) {
			case 'product_slider':
				setVal(container, '[data-field="source"]', cfg.source || 'all');
				setVal(container, '[data-field="category"]', cfg.category || '');
				setVal(container, '[data-field="product_ids"]', (cfg.product_ids || []).join(','));
				break;
			case 'review_slider':
				setCheck(container, '[data-field="photos_only"]', cfg.photos_only || false);
				setVal(container, '[data-field="product_ids"]', (cfg.product_ids || []).join(','));
				break;
			case 'trust_bar':
				setCheck(container, '[data-field="icon_accent"]', cfg.icon_accent || false);
				populateRepeaterItems(container, '.wchs-accordion-items', cfg.items || [], function (item, el) {
					// Set icon picker
					var iconVal = item.icon || 'shipping';
					var picker = el.querySelector('.wchs-icon-picker');
					if (picker) {
						var hidden = picker.querySelector('.wchs-icon-picker__value');
						if (hidden) hidden.value = iconVal;
						picker.querySelectorAll('.wchs-icon-picker__btn').forEach(function (b) {
							b.classList.toggle('is-selected', b.dataset.icon === iconVal);
						});
					}
					var inputs = el.querySelectorAll('input[type="text"]');
					if (inputs[0]) inputs[0].value = item.headline || '';
					if (inputs[1]) inputs[1].value = item.description || '';
				});
				break;
			case 'accordion':
				populateRepeaterItems(container, '.wchs-accordion-items', cfg.items || [], function (item, el) {
					var inputs = el.querySelectorAll('input');
					var textareas = el.querySelectorAll('textarea');
					if (inputs[0]) inputs[0].value = item.q || '';
					if (textareas[0]) textareas[0].value = item.a || '';
				});
				break;
			case 'listicle':
				setVal(container, '[data-field="lc_section_eyebrow"]', cfg.section_eyebrow || '');
				setVal(container, '[data-field="lc_headline"]', cfg.headline || '');
				setVal(container, '[data-field="lc_hero_image"]', cfg.hero_image || '');
				setVal(container, '[data-field="lc_hero_image_alt"]', cfg.hero_image_alt || '');
				setVal(container, '[data-field="lc_intro"]', cfg.intro || '');
				setVal(container, '[data-field="lc_items_headline"]', cfg.items_headline || '');
				setVal(container, '[data-field="lc_closing"]', cfg.closing || '');
				setVal(container, '[data-field="lc_cta_label"]', cfg.cta_label || '');
				setVal(container, '[data-field="lc_cta_href"]', cfg.cta_href || '');
				syncListicleHeroMedia(container);
				populateRepeaterItems(container, '.wchs-listicle-items', listicleItemsForAdmin(cfg), function (item, el) {
					setVal(el, '[data-field="lc_item_number"]', item.number || '');
					setVal(el, '[data-field="lc_item_label"]', item.label || '');
					setVal(el, '[data-field="lc_item_headline"]', item.headline || '');
					setVal(el, '[data-field="lc_item_body"]', item.body || '');
					setVal(el, '[data-field="lc_item_callout"]', item.callout || '');
					setVal(el, '[data-field="lc_item_image"]', item.image || '');
					setVal(el, '[data-field="lc_item_image_alt"]', item.image_alt || '');
					syncListicleItemMedia(el);
				});
				break;
			case 'listicle_faqs':
				setVal(container, '[data-field="lf_eyebrow"]', cfg.eyebrow || '');
				setVal(
					container,
					'[data-field="lf_headline"]',
					cfg.headline ||
						[cfg.headline_prefix, cfg.headline_accent].filter(Boolean).join('') ||
						''
				);
				populateRepeaterItems(container, '.wchs-listicle-faqs-items', cfg.items || [], function (item, el) {
					var inputs = el.querySelectorAll('input[type="text"]');
					var textareas = el.querySelectorAll('textarea');
					if (inputs[0]) inputs[0].value = item.q || '';
					if (textareas[0]) textareas[0].value = item.a || '';
				});
				break;
			case 'reviews_listicle':
				setVal(container, '[data-field="rl_headline"]', cfg.headline || '');
				populateRepeaterItems(container, '.wchs-reviews-listicle-items', cfg.items || [], function (item, el) {
					var textareas = el.querySelectorAll('textarea');
					var inputs = el.querySelectorAll('input');
					if (textareas[0]) textareas[0].value = item.quote || '';
					if (inputs[0]) inputs[0].value = item.name || '';
					if (inputs[1]) inputs[1].value = item.rating != null ? String(item.rating) : '5';
				});
				break;
			case 'promo_offer':
				setVal(container, '[data-field="po_intro_subheadline"]', cfg.intro_subheadline || cfg.intro_headline || '');
				setVal(container, '[data-field="po_badge_text"]', cfg.badge_text || '');
				setVal(container, '[data-field="po_image"]', cfg.image || '');
				setVal(container, '[data-field="po_image_alt"]', cfg.image_alt || '');
				setVal(container, '[data-field="po_ribbon_text"]', cfg.ribbon_text || '');
				setVal(container, '[data-field="po_offer_primary"]', cfg.offer_primary || '');
				setVal(container, '[data-field="po_offer_secondary"]', cfg.offer_secondary || '');
				setVal(container, '[data-field="po_scarcity_text"]', cfg.scarcity_text || '');
				setVal(container, '[data-field="po_cta_label"]', cfg.cta_label || '');
				setVal(container, '[data-field="po_cta_href"]', cfg.cta_href || '');
				setCheck(container, '[data-field="po_show_countdown"]', cfg.show_countdown !== false);
				setVal(container, '[data-field="po_countdown_end_at"]', isoToLocal(cfg.countdown_end_at || ''));
				setVal(container, '[data-field="po_status_label"]', cfg.status_label || '');
				setVal(container, '[data-field="po_status_value"]', cfg.status_value || '');
				setVal(container, '[data-field="po_status_note"]', cfg.status_note || '');
				setVal(container, '[data-field="po_footer_text"]', cfg.footer_text || '');
				(function () {
					var input = container.querySelector('[data-field="po_image"]');
					if (!input) return;
					var field = input.closest('.wchs-field');
					var preview = field && field.querySelector('.wchs-media-preview');
					var removeBtn = field && field.querySelector('.wchs-media-remove');
					if (input.value && preview) { preview.src = input.value; preview.style.display = ''; }
					else if (preview) { preview.src = ''; preview.style.display = 'none'; }
					if (removeBtn) removeBtn.style.display = input.value ? '' : 'none';
				})();
				break;
			case 'text_block':
				setVal(container, '[data-field="tb_layout"]', cfg.layout || 'auto');
				setVal(container, '[data-field="tb_headline"]', cfg.headline || '');
				setVal(container, '[data-field="content"]', cfg.content || '');
				setVal(container, '[data-field="tb_brand_name"]', cfg.brand_name || '');
				setVal(container, '[data-field="tb_competitor_name"]', cfg.competitor_name || '');
				setVal(container, '[data-field="tb_brand_logo"]', cfg.brand_logo || '');
				setVal(container, '[data-field="tb_competitor_logo"]', cfg.competitor_logo || '');
				populateTbCompareRows(container, cfg.comparison_rows || []);
				(function () {
					['tb_brand_logo', 'tb_competitor_logo'].forEach(function (fid) {
						var input = container.querySelector('[data-field="' + fid + '"]');
						if (!input) return;
						var field = input.closest('.wchs-field');
						var preview = field && field.querySelector('.wchs-media-preview');
						var removeBtn = field && field.querySelector('.wchs-media-remove');
						if (input.value && preview) { preview.src = input.value; preview.style.display = ''; }
						else if (preview) { preview.src = ''; preview.style.display = 'none'; }
						if (removeBtn) removeBtn.style.display = input.value ? '' : 'none';
					});
				})();
				break;
			case 'gallery':
				setVal(container, '[data-field="columns"]', cfg.columns || 3);
				setVal(container, '[data-field="gap"]', cfg.gap || 8);
				setVal(container, '[data-field="aspect_ratio"]', cfg.aspect_ratio || '1/1');
				populateGalleryItems(container, cfg.items || []);
				break;
			case 'shop_grid':
				setVal(container, '[data-field="category"]', cfg.category || '');
				break;
			case 'contact_form':
				setVal(container, '[data-field="recipient_email"]', cfg.recipient_email || '');
				setVal(container, '[data-field="subject_prefix"]', cfg.subject_prefix || '');
				setVal(container, '[data-field="success_message"]', cfg.success_message || '');
				populateContactFields(container, cfg.fields || []);
				break;
			case 'category_grid':
				setVal(container, '[data-field="columns"]', cfg.columns || 4);
				setVal(container, '[data-field="gap"]', cfg.gap || 12);
				populateCatGridItems(container, cfg.items || []);
				break;
			case 'split_features':
				setVal(container, '[data-field="sf_layout"]', cfg.layout || 'alternating');
				setVal(container, '[data-field="sf_headline"]', cfg.headline || '');
				setVal(container, '[data-field="sf_subtitle"]', cfg.subtitle || '');
				setVal(container, '[data-field="sf_brand_name"]', cfg.brand_name || '');
				setVal(container, '[data-field="sf_competitor_name"]', cfg.competitor_name || '');
				setVal(container, '[data-field="sf_brand_logo"]', cfg.brand_logo || '');
				setVal(container, '[data-field="sf_competitor_logo"]', cfg.competitor_logo || '');
				populateSplitItems(container, cfg.items || []);
				(function () {
					['sf_brand_logo', 'sf_competitor_logo'].forEach(function (fid) {
						var input = container.querySelector('[data-field="' + fid + '"]');
						if (!input) return;
						var field = input.closest('.wchs-field');
						var preview = field && field.querySelector('.wchs-media-preview');
						var removeBtn = field && field.querySelector('.wchs-media-remove');
						if (input.value && preview) { preview.src = input.value; preview.style.display = ''; }
						else if (preview) { preview.src = ''; preview.style.display = 'none'; }
						if (removeBtn) removeBtn.style.display = input.value ? '' : 'none';
					});
				})();
				break;
			case 'split_value':
				setVal(container, '[data-field="sv_rating_line"]', cfg.rating_line || '');
				setVal(container, '[data-field="sv_headline_prefix"]', cfg.headline_prefix || '');
				setVal(container, '[data-field="sv_headline_accent"]', cfg.headline_accent || '');
				setCheck(container, '[data-field="sv_accent_underline"]', cfg.accent_underline !== false);
				populateSvBullets(container, cfg.bullets || []);
				setVal(container, '[data-field="sv_cta_label"]', cfg.cta_label || '');
				setVal(container, '[data-field="sv_cta_href"]', cfg.cta_href || '');
				setVal(container, '[data-field="sv_trust_note"]', cfg.trust_note || '');
				setVal(container, '[data-field="sv_promo_eyebrow"]', cfg.promo_badge_eyebrow || '');
				setVal(container, '[data-field="sv_promo_title"]', cfg.promo_badge_title || '');
				setVal(container, '[data-field="sv_image"]', cfg.image || '');
				setVal(container, '[data-field="sv_image_alt"]', cfg.image_alt || '');
				populateSvStats(container, cfg.stats || []);
				(function () {
					var input = container.querySelector('[data-field="sv_image"]');
					if (!input) return;
					var field = input.closest('.wchs-field');
					var preview = field && field.querySelector('.wchs-media-preview');
					var removeBtn = field && field.querySelector('.wchs-media-remove');
					if (input.value && preview) { preview.src = input.value; preview.style.display = ''; }
					else if (preview) { preview.src = ''; preview.style.display = 'none'; }
					if (removeBtn) removeBtn.style.display = input.value ? '' : 'none';
				})();
				break;
			case 'feature_highlights':
				setVal(container, '[data-field="fh_badge_text"]', cfg.badge_text || '');
				setVal(container, '[data-field="fh_headline_prefix"]', cfg.headline_prefix || '');
				setVal(container, '[data-field="fh_headline_accent"]', cfg.headline_accent || '');
				setVal(container, '[data-field="fh_subheadline"]', cfg.subheadline || '');
				setVal(container, '[data-field="fh_cta_label"]', cfg.cta_label || '');
				setVal(container, '[data-field="fh_cta_href"]', cfg.cta_href || '');
				populateRepeaterItems(container, '.wchs-fh-items', cfg.items || [], function (item, el) {
					var sel = el.querySelector('[data-field="fh_variant"]');
					if (sel) sel.value = item.variant || 'pin';
					var inputs = el.querySelectorAll('input[type="text"]');
					if (inputs[0]) inputs[0].value = item.headline || '';
					if (inputs[1]) inputs[1].value = item.description || '';
				});
				break;
			case 'order_handling':
				setVal(container, '[data-field="oh_badge_text"]', cfg.badge_text || '');
				setVal(container, '[data-field="oh_headline"]', cfg.headline || '');
				setVal(container, '[data-field="oh_subheadline"]', cfg.subheadline || '');
				setVal(container, '[data-field="oh_metrics_title"]', cfg.metrics_title || '');
				populateRepeaterItems(container, '.wchs-oh-steps', cfg.steps || [], function (item, el) {
					var sel = el.querySelector('[data-field="oh_step_variant"]');
					if (sel) sel.value = item.variant || 'verified';
					var inputs = el.querySelectorAll('input[type="text"]');
					if (inputs[0]) inputs[0].value = item.headline || '';
					if (inputs[1]) inputs[1].value = item.description || '';
				});
				populateOhMetrics(container, cfg.metrics || []);
				break;
			case 'cta':
				setVal(container, '[data-field="cta_label"]', cfg.label || '');
				setVal(container, '[data-field="cta_href"]', cfg.href || '');
				setVal(container, '[data-field="cta_style"]', cfg.style || 'primary');
				setVal(container, '[data-field="cta_size"]', cfg.size || 'md');
				setVal(container, '[data-field="cta_align"]', cfg.align || 'center');
				setCheck(container, '[data-field="cta_open_new_tab"]', !!cfg.open_new_tab);
				break;
			case 'spacer':
				var spacerH = (typeof cfg.height === 'number') ? cfg.height : 40;
				setVal(container, '[data-field="spacer_height"]', spacerH);
				var spacerLbl = container.querySelector('.wchs-spacer-mod-h-val');
				if (spacerLbl) spacerLbl.textContent = String(spacerH);
				break;
			case 'logo_strip':
				setCheck(container, '[data-field="logo_grayscale"]', cfg.grayscale !== false);
				populateLogoStripItems(container, cfg.items || []);
				break;
			case 'video':
				setVal(container, '[data-field="source_url"]', cfg.source_url || '');
				setVal(container, '[data-field="poster_url"]', cfg.poster_url || '');
				setVal(container, '[data-field="aspect_ratio"]', cfg.aspect_ratio || '16/9');
				setCheck(container, '[data-field="autoplay"]', !!cfg.autoplay);
				setCheck(container, '[data-field="muted"]',    cfg.muted !== false);
				setCheck(container, '[data-field="loop"]',     !!cfg.loop);
				setCheck(container, '[data-field="controls"]', cfg.controls !== false);
				// Preview the poster if set
				var posterInput = container.querySelector('[data-field="poster_url"]');
				if (posterInput) {
					var field = posterInput.closest('.wchs-field');
					var preview = field && field.querySelector('.wchs-media-preview');
					var rm = field && field.querySelector('.wchs-media-remove');
					if (posterInput.value && preview) { preview.src = posterInput.value; preview.style.display = ''; }
					if (rm) rm.style.display = posterInput.value ? '' : 'none';
				}
				break;
			case 'hero':
				setVal(container, '[data-field="hero_headline"]', cfg.headline || '');
				setVal(container, '[data-field="hero_subheadline"]', cfg.subheadline || '');
				setVal(container, '[data-field="hero_layout"]', cfg.layout || 'left');
				setVal(container, '[data-field="hero_text_color_mode"]', cfg.text_color_mode || 'theme');
				setVal(container, '[data-field="hero_image_desktop"]', cfg.image_desktop || '');
				setVal(container, '[data-field="hero_image_mobile"]', cfg.image_mobile || '');
				setVal(container, '[data-field="hero_image_position_x"]', cfg.image_position_x ?? 50);
				setVal(container, '[data-field="hero_image_position_y"]', cfg.image_position_y ?? 50);
				setVal(container, '[data-field="hero_image_zoom"]', cfg.image_zoom ?? 100);
				setVal(container, '[data-field="hero_variant"]', cfg.variant || 'text-only');
				setCheck(container, '[data-field="hero_show_cta"]', cfg.show_cta !== false);
				setVal(container, '[data-field="hero_cta_text"]', cfg.cta_text || '');
				setVal(container, '[data-field="hero_cta_link"]', cfg.cta_link || '#');
				setVal(container, '[data-field="hero_research_badge"]', cfg.research_badge || '');
				setVal(container, '[data-field="hero_cta_secondary_text"]', cfg.cta_secondary_text || '');
				setVal(container, '[data-field="hero_cta_secondary_link"]', cfg.cta_secondary_link || '');
				(function () {
					var rs = cfg.research_stats;
					var txt = '';
					if (Array.isArray(rs)) {
						try { txt = JSON.stringify(rs, null, 2); } catch (e) { txt = '[]'; }
					} else if (typeof rs === 'string') {
						txt = rs;
					}
					setVal(container, '[data-field="hero_research_stats_json"]', txt || '[]');
				})();
				setVal(container, '[data-field="hero_headline_size"]', cfg.headline_size || 'l');
				setVal(container, '[data-field="hero_headline_weight"]', cfg.headline_weight || 'medium');
				setVal(container, '[data-field="hero_headline_font"]', cfg.headline_font || 'inter');
				setVal(container, '[data-field="hero_subheadline_size"]', cfg.subheadline_size || 'm');
				// Refresh slider labels + media preview images
				['hero_image_position_x', 'hero_image_position_y', 'hero_image_zoom'].forEach(function (f) {
					var r = container.querySelector('[data-field="' + f + '"]');
					if (r) {
						var lbl = r.parentElement.querySelector('.wchs-hero-mod-pos-x-val, .wchs-hero-mod-pos-y-val, .wchs-hero-mod-zoom-val');
						if (lbl) lbl.textContent = r.value;
					}
				});
				['hero_image_desktop', 'hero_image_mobile'].forEach(function (f) {
					var input = container.querySelector('[data-field="' + f + '"]');
					if (!input) return;
					var field = input.closest('.wchs-field');
					var preview = field && field.querySelector('.wchs-media-preview');
					var removeBtn = field && field.querySelector('.wchs-media-remove');
					if (input.value && preview) { preview.src = input.value; preview.style.display = ''; }
					else if (preview) { preview.src = ''; preview.style.display = 'none'; }
					if (removeBtn) removeBtn.style.display = input.value ? '' : 'none';
				});
				break;
		}
	}

	function readModuleFields(container, type, existing) {
		// Sync all WYSIWYG editors back to their textareas before reading
		syncWysiwygInContainer(container);

		var mod = {
			type: type,
			visibility: getVal(container, '[data-field="visibility"]') || 'all',
			spacing_v: getVal(container, '[data-field="spacing_v"]') || 'normal',
			spacing_h: getVal(container, '[data-field="spacing_h"]') || 'normal',
			center_header: getCheck(container, '[data-field="center_header"]'),
			config: { title: getVal(container, '[data-field="title"]') || '' }
		};

		// Preserve the stable id if one exists — SchemaSanitizer on PHP side
		// will generate one on first save if missing, but we want to keep
		// round-tripped ids stable across edits.
		if (existing && existing.id) mod.id = existing.id;

		// Scheduled publishing — empty string means "no schedule"; omit the
		// key entirely in that case to keep payload lean.
		var startLocal = getVal(container, '[data-field="start_at"]');
		var endLocal   = getVal(container, '[data-field="end_at"]');
		if (startLocal) { var iso = localToIso(startLocal); if (iso) mod.start_at = iso; }
		if (endLocal)   { var iso2 = localToIso(endLocal);   if (iso2) mod.end_at = iso2; }

		// Overrides — only attach the key if a non-empty hex value is set.
		// Leaving overrides out entirely (rather than persisting null) keeps
		// the REST payload clean and lets defaults flow through.
		var overrideAccent = getVal(container, '[data-field="overrides_accent_color"]') || '';
		if (overrideAccent && /^#[0-9a-fA-F]{6}$/.test(overrideAccent)) {
			mod.overrides = { accent_color: overrideAccent };
		}

		var cfg = mod.config;

		switch (type) {
			case 'product_slider':
				cfg.source = getVal(container, '[data-field="source"]') || 'all';
				cfg.category = cfg.source === 'category' ? getVal(container, '[data-field="category"]') : null;
				cfg.product_ids = (getVal(container, '[data-field="product_ids"]') || '').split(',').map(Number).filter(Boolean);
				break;
			case 'review_slider':
				cfg.photos_only = getCheck(container, '[data-field="photos_only"]');
				cfg.product_ids = (getVal(container, '[data-field="product_ids"]') || '')
					.split(',').map(Number).filter(Boolean);
				break;
			case 'trust_bar':
				cfg.icon_accent = getCheck(container, '[data-field="icon_accent"]');
				cfg.items = readTrustItems(container);
				break;
			case 'accordion':
				cfg.items = readAccordionItems(container);
				break;
			case 'listicle':
				cfg.section_eyebrow = getVal(container, '[data-field="lc_section_eyebrow"]') || '';
				cfg.headline = getVal(container, '[data-field="lc_headline"]') || '';
				cfg.hero_image = getVal(container, '[data-field="lc_hero_image"]') || '';
				cfg.hero_image_alt = getVal(container, '[data-field="lc_hero_image_alt"]') || '';
				cfg.intro = getVal(container, '[data-field="lc_intro"]') || '';
				cfg.items_headline = getVal(container, '[data-field="lc_items_headline"]') || '';
				cfg.closing = getVal(container, '[data-field="lc_closing"]') || '';
				cfg.cta_label = getVal(container, '[data-field="lc_cta_label"]') || '';
				cfg.cta_href = getVal(container, '[data-field="lc_cta_href"]') || '';
				cfg.items = readListicleItems(container);
				delete cfg.title;
				break;
			case 'reviews_listicle':
				cfg.headline = getVal(container, '[data-field="rl_headline"]') || '';
				cfg.items = readReviewsListicleItems(container);
				delete cfg.title;
				break;
			case 'listicle_faqs':
				cfg.eyebrow = getVal(container, '[data-field="lf_eyebrow"]') || '';
				cfg.headline = getVal(container, '[data-field="lf_headline"]') || '';
				cfg.items = readListicleFaqsItems(container);
				delete cfg.title;
				break;
			case 'promo_offer':
				cfg.intro_headline = '';
				cfg.intro_subheadline = getVal(container, '[data-field="po_intro_subheadline"]') || '';
				cfg.badge_text = getVal(container, '[data-field="po_badge_text"]') || '';
				cfg.image = getVal(container, '[data-field="po_image"]') || '';
				cfg.image_alt = getVal(container, '[data-field="po_image_alt"]') || '';
				cfg.ribbon_text = getVal(container, '[data-field="po_ribbon_text"]') || '';
				cfg.offer_primary = getVal(container, '[data-field="po_offer_primary"]') || '';
				cfg.offer_secondary = getVal(container, '[data-field="po_offer_secondary"]') || '';
				cfg.scarcity_text = getVal(container, '[data-field="po_scarcity_text"]') || '';
				cfg.cta_label = getVal(container, '[data-field="po_cta_label"]') || '';
				cfg.cta_href = getVal(container, '[data-field="po_cta_href"]') || '';
				cfg.show_countdown = getCheck(container, '[data-field="po_show_countdown"]');
				(function () {
					var localEnd = getVal(container, '[data-field="po_countdown_end_at"]') || '';
					if (localEnd) {
						var isoEnd = localToIso(localEnd);
						if (isoEnd) cfg.countdown_end_at = isoEnd;
					} else {
						cfg.countdown_end_at = '';
					}
				})();
				cfg.status_label = getVal(container, '[data-field="po_status_label"]') || '';
				cfg.status_value = getVal(container, '[data-field="po_status_value"]') || '';
				cfg.status_note = getVal(container, '[data-field="po_status_note"]') || '';
				cfg.footer_text = getVal(container, '[data-field="po_footer_text"]') || '';
				delete cfg.title;
				break;
			case 'text_block':
				cfg.layout = getVal(container, '[data-field="tb_layout"]') || 'auto';
				cfg.headline = getVal(container, '[data-field="tb_headline"]') || '';
				cfg.content = getVal(container, '[data-field="content"]') || '';
				cfg.brand_name = getVal(container, '[data-field="tb_brand_name"]') || '';
				cfg.competitor_name = getVal(container, '[data-field="tb_competitor_name"]') || '';
				cfg.brand_logo = getVal(container, '[data-field="tb_brand_logo"]') || '';
				cfg.competitor_logo = getVal(container, '[data-field="tb_competitor_logo"]') || '';
				cfg.comparison_rows = readTbCompareRows(container);
				break;
			case 'gallery':
				cfg.columns = parseInt(getVal(container, '[data-field="columns"]')) || 3;
				cfg.gap = parseInt(getVal(container, '[data-field="gap"]')) || 8;
				cfg.aspect_ratio = getVal(container, '[data-field="aspect_ratio"]') || '1/1';
				cfg.items = readGalleryItems(container);
				break;
			case 'shop_grid':
				cfg.category = getVal(container, '[data-field="category"]') || null;
				break;
			case 'contact_form':
				cfg.recipient_email = getVal(container, '[data-field="recipient_email"]') || '';
				cfg.subject_prefix = getVal(container, '[data-field="subject_prefix"]') || '';
				cfg.success_message = getVal(container, '[data-field="success_message"]') || '';
				cfg.fields = readContactFields(container);
				break;
			case 'category_grid':
				cfg.columns = parseInt(getVal(container, '[data-field="columns"]')) || 4;
				cfg.gap = parseInt(getVal(container, '[data-field="gap"]')) || 12;
				cfg.items = readCatGridItems(container);
				break;
			case 'split_features':
				cfg.layout = getVal(container, '[data-field="sf_layout"]') || 'alternating';
				cfg.headline = getVal(container, '[data-field="sf_headline"]') || '';
				cfg.subtitle = getVal(container, '[data-field="sf_subtitle"]') || '';
				cfg.brand_name = getVal(container, '[data-field="sf_brand_name"]') || '';
				cfg.competitor_name = getVal(container, '[data-field="sf_competitor_name"]') || '';
				cfg.brand_logo = getVal(container, '[data-field="sf_brand_logo"]') || '';
				cfg.competitor_logo = getVal(container, '[data-field="sf_competitor_logo"]') || '';
				cfg.items = readSplitItems(container);
				break;
			case 'split_value':
				cfg.rating_line = getVal(container, '[data-field="sv_rating_line"]') || '';
				cfg.headline_prefix = getVal(container, '[data-field="sv_headline_prefix"]') || '';
				cfg.headline_accent = getVal(container, '[data-field="sv_headline_accent"]') || '';
				cfg.accent_underline = getCheck(container, '[data-field="sv_accent_underline"]');
				cfg.bullets = readSvBullets(container);
				cfg.cta_label = getVal(container, '[data-field="sv_cta_label"]') || '';
				cfg.cta_href = getVal(container, '[data-field="sv_cta_href"]') || '';
				cfg.trust_note = getVal(container, '[data-field="sv_trust_note"]') || '';
				cfg.promo_badge_eyebrow = getVal(container, '[data-field="sv_promo_eyebrow"]') || '';
				cfg.promo_badge_title = getVal(container, '[data-field="sv_promo_title"]') || '';
				cfg.image = getVal(container, '[data-field="sv_image"]') || '';
				cfg.image_alt = getVal(container, '[data-field="sv_image_alt"]') || '';
				cfg.stats = readSvStats(container);
				delete cfg.title;
				break;
			case 'feature_highlights':
				cfg.badge_text = getVal(container, '[data-field="fh_badge_text"]') || '';
				cfg.headline_prefix = getVal(container, '[data-field="fh_headline_prefix"]') || '';
				cfg.headline_accent = getVal(container, '[data-field="fh_headline_accent"]') || '';
				cfg.subheadline = getVal(container, '[data-field="fh_subheadline"]') || '';
				cfg.cta_label = getVal(container, '[data-field="fh_cta_label"]') || '';
				cfg.cta_href = getVal(container, '[data-field="fh_cta_href"]') || '';
				cfg.items = readFhItems(container);
				delete cfg.title;
				break;
			case 'order_handling':
				cfg.badge_text = getVal(container, '[data-field="oh_badge_text"]') || '';
				cfg.headline = getVal(container, '[data-field="oh_headline"]') || '';
				cfg.subheadline = getVal(container, '[data-field="oh_subheadline"]') || '';
				cfg.metrics_title = getVal(container, '[data-field="oh_metrics_title"]') || '';
				cfg.steps = readOhSteps(container);
				cfg.metrics = readOhMetrics(container);
				delete cfg.title;
				break;
			case 'cta':
				cfg.label = getVal(container, '[data-field="cta_label"]') || '';
				cfg.href = getVal(container, '[data-field="cta_href"]') || '';
				cfg.style = getVal(container, '[data-field="cta_style"]') || 'primary';
				cfg.size = getVal(container, '[data-field="cta_size"]') || 'md';
				cfg.align = getVal(container, '[data-field="cta_align"]') || 'center';
				cfg.open_new_tab = getCheck(container, '[data-field="cta_open_new_tab"]');
				// CTA has no `title` — the common placeholder field isn't shown.
				delete cfg.title;
				break;
			case 'spacer':
				var rawH = parseInt(getVal(container, '[data-field="spacer_height"]'), 10);
				cfg.height = Number.isFinite(rawH) ? Math.max(8, Math.min(160, rawH)) : 40;
				delete cfg.title;
				break;
			case 'logo_strip':
				cfg.grayscale = getCheck(container, '[data-field="logo_grayscale"]');
				cfg.items = readLogoStripItems(container);
				break;
			case 'video':
				cfg.source_url   = getVal(container, '[data-field="source_url"]') || '';
				cfg.poster_url   = getVal(container, '[data-field="poster_url"]') || '';
				cfg.aspect_ratio = getVal(container, '[data-field="aspect_ratio"]') || '16/9';
				cfg.autoplay     = getCheck(container, '[data-field="autoplay"]');
				cfg.muted        = getCheck(container, '[data-field="muted"]');
				cfg.loop         = getCheck(container, '[data-field="loop"]');
				cfg.controls     = getCheck(container, '[data-field="controls"]');
				break;
			case 'hero':
				cfg.headline = getVal(container, '[data-field="hero_headline"]') || '';
				cfg.subheadline = getVal(container, '[data-field="hero_subheadline"]') || '';
				cfg.layout = getVal(container, '[data-field="hero_layout"]') || 'left';
				cfg.text_color_mode = getVal(container, '[data-field="hero_text_color_mode"]') || 'theme';
				cfg.image_desktop = getVal(container, '[data-field="hero_image_desktop"]') || '';
				cfg.image_mobile = getVal(container, '[data-field="hero_image_mobile"]') || '';
				cfg.image_position_x = parseInt(getVal(container, '[data-field="hero_image_position_x"]'), 10);
				cfg.image_position_y = parseInt(getVal(container, '[data-field="hero_image_position_y"]'), 10);
				cfg.image_zoom = parseInt(getVal(container, '[data-field="hero_image_zoom"]'), 10);
				if (isNaN(cfg.image_position_x)) cfg.image_position_x = 50;
				if (isNaN(cfg.image_position_y)) cfg.image_position_y = 50;
				if (isNaN(cfg.image_zoom)) cfg.image_zoom = 100;
				cfg.variant = getVal(container, '[data-field="hero_variant"]') || 'text-only';
				cfg.show_cta = getCheck(container, '[data-field="hero_show_cta"]');
				cfg.cta_text = getVal(container, '[data-field="hero_cta_text"]') || '';
				cfg.cta_link = getVal(container, '[data-field="hero_cta_link"]') || '#';
				cfg.research_badge = getVal(container, '[data-field="hero_research_badge"]') || '';
				cfg.cta_secondary_text = getVal(container, '[data-field="hero_cta_secondary_text"]') || '';
				cfg.cta_secondary_link = getVal(container, '[data-field="hero_cta_secondary_link"]') || '';
				(function () {
					var raw = getVal(container, '[data-field="hero_research_stats_json"]');
					try {
						var parsed = JSON.parse(raw || '[]');
						cfg.research_stats = Array.isArray(parsed) ? parsed : [];
					} catch (e) {
						cfg.research_stats = [];
					}
				})();
				cfg.headline_size = getVal(container, '[data-field="hero_headline_size"]') || 'l';
				cfg.headline_weight = getVal(container, '[data-field="hero_headline_weight"]') || 'medium';
				cfg.headline_font = getVal(container, '[data-field="hero_headline_font"]') || 'inter';
				cfg.subheadline_size = getVal(container, '[data-field="hero_subheadline_size"]') || 'm';
				// Strip the placeholder `title` field — hero doesn't use it.
				delete cfg.title;
				break;
		}

		return mod;
	}

	// DOM helpers
	function setVal(ctx, sel, val) { var el = ctx.querySelector(sel); if (el) el.value = val; }
	function getVal(ctx, sel) { var el = ctx.querySelector(sel); return el ? el.value : ''; }
	function setCheck(ctx, sel, val) { var el = ctx.querySelector(sel); if (el) el.checked = !!val; }
	function getCheck(ctx, sel) { var el = ctx.querySelector(sel); return el ? el.checked : false; }

	// Repeater readers
	function readTrustItems(ctx) {
		var items = [];
		ctx.querySelectorAll('.wchs-accordion-items .wchs-accordion-item').forEach(function (el) {
			if (el.closest('.wchs-fh-items')) return;
			var iconHidden = el.querySelector('.wchs-icon-picker__value');
			var inputs = el.querySelectorAll('input[type="text"]');
			items.push({ icon: iconHidden ? iconHidden.value : '', headline: inputs[0] ? inputs[0].value : '', description: inputs[1] ? inputs[1].value : '' });
		});
		return items;
	}

	function readFhItems(ctx) {
		var items = [];
		ctx.querySelectorAll('.wchs-fh-items .wchs-accordion-item').forEach(function (el) {
			var sel = el.querySelector('[data-field="fh_variant"]');
			var variant = sel ? sel.value : 'pin';
			var inputs = el.querySelectorAll('input[type="text"]');
			items.push({
				variant: variant,
				headline: inputs[0] ? inputs[0].value : '',
				description: inputs[1] ? inputs[1].value : '',
			});
		});
		return items;
	}

	function readOhSteps(ctx) {
		var items = [];
		ctx.querySelectorAll('.wchs-oh-steps .wchs-accordion-item').forEach(function (el) {
			var sel = el.querySelector('[data-field="oh_step_variant"]');
			var variant = sel ? sel.value : 'verified';
			var inputs = el.querySelectorAll('input[type="text"]');
			items.push({
				variant: variant,
				headline: inputs[0] ? inputs[0].value : '',
				description: inputs[1] ? inputs[1].value : '',
			});
		});
		return items;
	}

	function readOhMetrics(ctx) {
		var items = [];
		ctx.querySelectorAll('.wchs-oh-metrics .wchs-accordion-item').forEach(function (el) {
			var inputs = el.querySelectorAll('input[type="text"]');
			items.push({
				value: inputs[0] ? inputs[0].value : '',
				label: inputs[1] ? inputs[1].value : '',
			});
		});
		return items;
	}

	function readAccordionItems(ctx) {
		var items = [];
		ctx.querySelectorAll('.wchs-accordion-items .wchs-accordion-item').forEach(function (el) {
			if (el.closest('.wchs-listicle-items')) return;
			if (el.closest('.wchs-reviews-listicle-items')) return;
			if (el.closest('.wchs-listicle-faqs-items')) return;
			var inputs = el.querySelectorAll('input');
			var textareas = el.querySelectorAll('textarea');
			items.push({ q: inputs[0] ? inputs[0].value : '', a: textareas[0] ? textareas[0].value : '' });
		});
		return items;
	}

	function readListicleFaqsItems(ctx) {
		var items = [];
		ctx.querySelectorAll('.wchs-listicle-faqs-items .wchs-accordion-item').forEach(function (el) {
			var inputs = el.querySelectorAll('input[type="text"]');
			var textareas = el.querySelectorAll('textarea');
			var q = inputs[0] ? inputs[0].value.trim() : '';
			var a = textareas[0] ? textareas[0].value : '';
			if (!q || !a.trim()) return;
			items.push({ q: q, a: a });
		});
		return items;
	}

	function readReviewsListicleItems(ctx) {
		var items = [];
		ctx.querySelectorAll('.wchs-reviews-listicle-items .wchs-accordion-item').forEach(function (el) {
			var textareas = el.querySelectorAll('textarea');
			var inputs = el.querySelectorAll('input');
			var quote = textareas[0] ? textareas[0].value.trim() : '';
			var name = inputs[0] ? inputs[0].value.trim() : '';
			if (!quote || !name) return;
			var rating = parseInt(inputs[1] ? inputs[1].value : '5', 10);
			if (isNaN(rating)) rating = 5;
			rating = Math.min(5, Math.max(1, rating));
			items.push({ quote: quote, name: name, rating: rating });
		});
		return items;
	}

	function syncListicleItemMedia(el) {
		var input = el.querySelector('[data-field="lc_item_image"]');
		if (!input) return;
		var preview = el.querySelector('.wchs-media-preview');
		var removeBtn = el.querySelector('.wchs-media-remove');
		if (input.value && preview) {
			preview.src = input.value;
			preview.style.display = '';
		} else if (preview) {
			preview.src = '';
			preview.style.display = 'none';
		}
		if (removeBtn) removeBtn.style.display = input.value ? '' : 'none';
	}

	function listicleItemsForAdmin(cfg) {
		var saved = cfg.items || [];
		var defaults = defaultConfigFor('listicle').items || [];
		if (!defaults.length) return saved;
		if (!saved.length) return defaults.slice();
		var out = [];
		for (var i = 0; i < defaults.length; i++) {
			out.push(Object.assign({}, defaults[i], saved[i] || {}));
		}
		if (saved.length > defaults.length) {
			for (var j = defaults.length; j < saved.length; j++) {
				out.push(saved[j]);
			}
		}
		return out;
	}

	function syncListicleHeroMedia(ctx) {
		var input = ctx.querySelector('[data-field="lc_hero_image"]');
		if (!input) return;
		var field = input.closest('.wchs-field');
		var preview = field && field.querySelector('.wchs-media-preview');
		var removeBtn = field && field.querySelector('.wchs-media-remove');
		if (input.value && preview) {
			preview.src = input.value;
			preview.style.display = '';
		} else if (preview) {
			preview.src = '';
			preview.style.display = 'none';
		}
		if (removeBtn) removeBtn.style.display = input.value ? '' : 'none';
	}

	function readListicleItems(ctx) {
		var items = [];
		ctx.querySelectorAll('.wchs-listicle-items .wchs-accordion-item').forEach(function (el) {
			var headline = getVal(el, '[data-field="lc_item_headline"]').trim();
			if (!headline) return;
			items.push({
				number: getVal(el, '[data-field="lc_item_number"]'),
				label: getVal(el, '[data-field="lc_item_label"]'),
				headline: headline,
				body: getVal(el, '[data-field="lc_item_body"]'),
				callout: getVal(el, '[data-field="lc_item_callout"]'),
				image: getVal(el, '[data-field="lc_item_image"]'),
				image_alt: getVal(el, '[data-field="lc_item_image_alt"]'),
			});
		});
		return items;
	}

	function readGalleryItems(ctx) {
		var items = [];
		ctx.querySelectorAll('.wchs-gallery-items .wchs-gallery-item').forEach(function (el) {
			var src = el.querySelector('.wchs-gallery-src');
			var inputs = el.querySelectorAll('input[type="text"]');
			items.push({ src: src ? src.value : '', title: inputs[0] ? inputs[0].value : '', description: inputs[1] ? inputs[1].value : '' });
		});
		return items;
	}

	function readLogoStripItems(ctx) {
		var items = [];
		ctx.querySelectorAll('.wchs-logo-strip-items .wchs-logo-strip-item').forEach(function (el) {
			var src = el.querySelector('.wchs-logo-src');
			var inputs = el.querySelectorAll('input[type="text"]');
			items.push({
				src: src ? src.value : '',
				alt: inputs[0] ? inputs[0].value : '',
				link_url: inputs[1] ? inputs[1].value : ''
			});
		});
		return items.filter(function (i) { return i.src; });
	}

	function populateLogoStripItems(ctx, items) {
		var container = ctx.querySelector('.wchs-logo-strip-items');
		if (!container || !items.length) return;
		var tpl = container.querySelector('.wchs-logo-strip-item');
		if (!tpl) return;
		var tplHtml = tpl.outerHTML;
		container.innerHTML = '';
		items.forEach(function (item) {
			var div = document.createElement('div');
			div.innerHTML = tplHtml;
			var el = div.firstElementChild;
			var src = el.querySelector('.wchs-logo-src');
			if (src) src.value = item.src || '';
			var thumb = el.querySelector('.wchs-logo-thumb');
			if (thumb && item.src) { thumb.src = item.src; thumb.style.display = ''; }
			var inputs = el.querySelectorAll('input[type="text"]');
			if (inputs[0]) inputs[0].value = item.alt || '';
			if (inputs[1]) inputs[1].value = item.link_url || '';
			container.appendChild(el);
		});
	}

	function readContactFields(ctx) {
		var fields = [];
		ctx.querySelectorAll('.wchs-accordion-items .wchs-accordion-item').forEach(function (el) {
			var inputs = el.querySelectorAll('input[type="text"]');
			var selects = el.querySelectorAll('select');
			var checks = el.querySelectorAll('input[type="checkbox"]');
			fields.push({
				name: inputs[0] ? inputs[0].value : '',
				label: inputs[1] ? inputs[1].value : '',
				type: selects[0] ? selects[0].value : 'text',
				required: checks[0] ? checks[0].checked : false
			});
		});
		return fields;
	}

	function readCatGridItems(ctx) {
		var items = [];
		ctx.querySelectorAll('.wchs-accordion-items .wchs-accordion-item').forEach(function (el) {
			var selects = el.querySelectorAll('select');
			var src = el.querySelector('.wchs-gallery-src');
			items.push({ category_id: selects[0] ? parseInt(selects[0].value) || 0 : 0, image: src ? src.value : '' });
		});
		return items;
	}

	function readSplitItems(ctx) {
		var items = [];
		ctx.querySelectorAll('.wchs-accordion-items .wchs-accordion-item').forEach(function (el) {
			var inputs = el.querySelectorAll('input[type="text"]');
			var textareas = el.querySelectorAll('textarea');
			var src = el.querySelector('.wchs-gallery-src');
			items.push({
				eyebrow: inputs[0] ? inputs[0].value : '',
				heading: inputs[1] ? inputs[1].value : '',
				description: textareas[0] ? textareas[0].value : '',
				image: src ? src.value : ''
			});
		});
		return items;
	}

	function readSvBullets(ctx) {
		var items = [];
		ctx.querySelectorAll('.wchs-sv-bullets .wchs-accordion-item').forEach(function (el) {
			var inp = el.querySelector('input[type="text"]');
			var t = inp ? inp.value.trim() : '';
			if (t) items.push({ text: t });
		});
		return items;
	}

	function readSvStats(ctx) {
		var items = [];
		ctx.querySelectorAll('.wchs-sv-stats .wchs-accordion-item').forEach(function (el) {
			var inputs = el.querySelectorAll('input[type="text"]');
			var v = inputs[0] ? inputs[0].value.trim() : '';
			var lab = inputs[1] ? inputs[1].value.trim() : '';
			if (v || lab) items.push({ value: v, label: lab });
		});
		return items;
	}

	// Repeater populators (create DOM elements from data)
	function populateRepeaterItems(ctx, containerSel, items, fillFn) {
		var container = ctx.querySelector(containerSel);
		if (!container || !items.length) return;
		// Use the first existing item as a template, clone for each data item
		var templateItem = container.querySelector('.wchs-accordion-item');
		if (!templateItem) return;
		var tplHtml = templateItem.outerHTML;
		container.innerHTML = '';
		items.forEach(function (item) {
			var div = document.createElement('div');
			div.innerHTML = tplHtml;
			var el = div.firstElementChild;
			fillFn(item, el);
			container.appendChild(el);
		});
	}

	function populateGalleryItems(ctx, items) {
		var container = ctx.querySelector('.wchs-gallery-items');
		if (!container || !items.length) return;
		var tpl = container.querySelector('.wchs-gallery-item');
		if (!tpl) return;
		var tplHtml = tpl.outerHTML;
		container.innerHTML = '';
		items.forEach(function (item) {
			var div = document.createElement('div');
			div.innerHTML = tplHtml;
			var el = div.firstElementChild;
			var src = el.querySelector('.wchs-gallery-src');
			if (src) src.value = item.src || '';
			var thumb = el.querySelector('.wchs-gallery-thumb');
			if (thumb && item.src) { thumb.src = item.src; thumb.style.display = ''; }
			var inputs = el.querySelectorAll('input[type="text"]');
			if (inputs[0]) inputs[0].value = item.title || '';
			if (inputs[1]) inputs[1].value = item.description || '';
			container.appendChild(el);
		});
	}

	function populateContactFields(ctx, fields) {
		var container = ctx.querySelector('.wchs-accordion-items');
		if (!container || !fields.length) return;
		var tpl = container.querySelector('.wchs-accordion-item');
		if (!tpl) return;
		var tplHtml = tpl.outerHTML;
		container.innerHTML = '';
		fields.forEach(function (f) {
			var div = document.createElement('div');
			div.innerHTML = tplHtml;
			var el = div.firstElementChild;
			var inputs = el.querySelectorAll('input[type="text"]');
			if (inputs[0]) inputs[0].value = f.name || '';
			if (inputs[1]) inputs[1].value = f.label || '';
			var selects = el.querySelectorAll('select');
			if (selects[0]) selects[0].value = f.type || 'text';
			var checks = el.querySelectorAll('input[type="checkbox"]');
			if (checks[0]) checks[0].checked = !!f.required;
			container.appendChild(el);
		});
	}

	function populateCatGridItems(ctx, items) {
		var container = ctx.querySelector('.wchs-accordion-items');
		if (!container || !items.length) return;
		var tpl = container.querySelector('.wchs-accordion-item');
		if (!tpl) return;
		var tplHtml = tpl.outerHTML;
		container.innerHTML = '';
		items.forEach(function (item) {
			var div = document.createElement('div');
			div.innerHTML = tplHtml;
			var el = div.firstElementChild;
			var selects = el.querySelectorAll('select');
			if (selects[0]) selects[0].value = item.category_id || '';
			var src = el.querySelector('.wchs-gallery-src');
			if (src) src.value = item.image || '';
			container.appendChild(el);
		});
	}

	function populateTbCompareRows(ctx, rows) {
		var container = ctx.querySelector('.wchs-tb-compare-rows');
		if (!container) return;
		var tpl = container.querySelector('.wchs-accordion-item');
		if (!tpl) return;
		var tplHtml = tpl.outerHTML;
		container.innerHTML = '';
		(rows.length ? rows : [ { heading: '' } ]).forEach(function (item) {
			var div = document.createElement('div');
			div.innerHTML = tplHtml;
			var el = div.firstElementChild;
			var inp = el.querySelector('input[type="text"]');
			if (inp) inp.value = item.heading || '';
			container.appendChild(el);
		});
	}

	function readTbCompareRows(ctx) {
		var container = ctx.querySelector('.wchs-tb-compare-rows');
		if (!container) return [];
		var out = [];
		container.querySelectorAll('.wchs-accordion-item').forEach(function (el) {
			var inp = el.querySelector('input[type="text"]');
			var h = inp ? inp.value.trim() : '';
			if (h) out.push({ heading: h });
		});
		return out;
	}

	function populateSplitItems(ctx, items) {
		var container = ctx.querySelector('.wchs-accordion-items');
		if (!container || !items.length) return;
		var tpl = container.querySelector('.wchs-accordion-item');
		if (!tpl) return;
		var tplHtml = tpl.outerHTML;
		container.innerHTML = '';
		items.forEach(function (item) {
			var div = document.createElement('div');
			div.innerHTML = tplHtml;
			var el = div.firstElementChild;
			var inputs = el.querySelectorAll('input[type="text"]');
			if (inputs[0]) inputs[0].value = item.eyebrow || '';
			if (inputs[1]) inputs[1].value = item.heading || '';
			var textareas = el.querySelectorAll('textarea');
			if (textareas[0]) textareas[0].value = item.description || '';
			var src = el.querySelector('.wchs-gallery-src');
			if (src) src.value = item.image || '';
			var thumb = el.querySelector('.wchs-gallery-thumb');
			if (thumb && item.image) { thumb.src = item.image; thumb.style.display = ''; }
			container.appendChild(el);
		});
	}

	function populateSvBullets(ctx, items) {
		var container = ctx.querySelector('.wchs-sv-bullets');
		if (!container) return;
		var tpl = container.querySelector('.wchs-accordion-item');
		if (!tpl) return;
		var tplHtml = tpl.outerHTML;
		container.innerHTML = '';
		(items.length ? items : [{ text: '' }]).forEach(function (item) {
			var div = document.createElement('div');
			div.innerHTML = tplHtml;
			var el = div.firstElementChild;
			var inp = el.querySelector('input[type="text"]');
			if (inp) inp.value = item.text || '';
			container.appendChild(el);
		});
	}

	function populateSvStats(ctx, items) {
		var container = ctx.querySelector('.wchs-sv-stats');
		if (!container) return;
		var tpl = container.querySelector('.wchs-accordion-item');
		if (!tpl) return;
		var tplHtml = tpl.outerHTML;
		container.innerHTML = '';
		(items.length ? items : [{ value: '', label: '' }]).forEach(function (item) {
			var div = document.createElement('div');
			div.innerHTML = tplHtml;
			var el = div.firstElementChild;
			var inputs = el.querySelectorAll('input[type="text"]');
			if (inputs[0]) inputs[0].value = item.value || '';
			if (inputs[1]) inputs[1].value = item.label || '';
			container.appendChild(el);
		});
	}

	function populateOhMetrics(ctx, items) {
		var container = ctx.querySelector('.wchs-oh-metrics');
		if (!container) return;
		var tpl = container.querySelector('.wchs-accordion-item');
		if (!tpl) return;
		var tplHtml = tpl.outerHTML;
		container.innerHTML = '';
		(items.length ? items : [{ value: '', label: '' }]).forEach(function (item) {
			var div = document.createElement('div');
			div.innerHTML = tplHtml;
			var el = div.firstElementChild;
			var inputs = el.querySelectorAll('input[type="text"]');
			if (inputs[0]) inputs[0].value = item.value || '';
			if (inputs[1]) inputs[1].value = item.label || '';
			container.appendChild(el);
		});
	}

	// ── Module list rendering + drag-to-reorder ───────────────

	function initModuleList(containerEl) {
		var hiddenInput = containerEl.querySelector('input[type="hidden"]');
		var listEl = containerEl.querySelector('.wchs-modlist__items');
		var modules = [];

		try { modules = JSON.parse(hiddenInput.value || '[]'); } catch (e) { modules = []; }

		function sync() {
			hiddenInput.value = JSON.stringify(modules);
			renderList();
			// Programmatic `.value =` doesn't fire input events — dispatch
			// manually so scheduleSync + dirty tracker + undo capture pick
			// up module mutations (add/dup/remove/reorder/edit-apply).
			hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
		}

		function renderList() {
			listEl.innerHTML = '';
			modules.forEach(function (mod, i) {
				var row = document.createElement('div');
				row.className = 'wchs-modlist__row';
				row.draggable = true;
				row.dataset.index = i;
				row.tabIndex = 0;
				row.innerHTML = '<span class="wchs-modlist__drag">⠿</span>'
					+ '<span class="wchs-modlist__type">' + (TYPE_LABELS[mod.type] || mod.type) + '</span>'
					+ '<span class="wchs-modlist__title">' + escHtml(mod.config && mod.config.title || '') + '</span>'
					+ '<span class="wchs-modlist__vis">' + (mod.visibility || 'all') + '</span>'
					+ '<button type="button" class="wchs-modlist__edit wchs-icon-btn" data-tooltip="Edit"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11.5 1.5l3 3L5 14H2v-3L11.5 1.5z"/></svg></button>'
					+ '<button type="button" class="wchs-modlist__dup wchs-icon-btn" data-tooltip="Duplicate"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="5" width="9" height="9" rx="1"/><path d="M10 5V3a1 1 0 00-1-1H3a1 1 0 00-1 1v6a1 1 0 001 1h2"/></svg></button>'
					+ '<button type="button" class="wchs-modlist__preset wchs-icon-btn" data-tooltip="Save as preset"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2h8l-.5 12-3.5-2-3.5 2L4 2z"/></svg></button>'
					+ '<button type="button" class="wchs-modlist__remove wchs-icon-btn wchs-icon-btn--danger" data-tooltip="Remove"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 4.5h11M5.5 4.5V3a1 1 0 011-1h3a1 1 0 011 1v1.5M6.5 7v4.5M9.5 7v4.5M3.5 4.5l.5 9a1 1 0 001 1h6a1 1 0 001-1l.5-9"/></svg></button>';
				listEl.appendChild(row);
			});
		}

		// Focus tracking — which row is currently focused, used by keyboard
		// copy/paste. Updated on click + focus.
		containerEl._wchsFocusedIdx = null;
		listEl.addEventListener('focusin', function (e) {
			var row = e.target.closest('.wchs-modlist__row');
			containerEl._wchsFocusedIdx = row ? parseInt(row.dataset.index) : null;
		});
		listEl.addEventListener('mousedown', function (e) {
			var row = e.target.closest('.wchs-modlist__row');
			if (row) containerEl._wchsFocusedIdx = parseInt(row.dataset.index);
		});

		// Refresh hook for undo/redo — re-parses the hidden input + re-renders.
		// Underscore-prefix signals internal, not a public API.
		containerEl._wchsRefresh = function () {
			try { modules = JSON.parse(hiddenInput.value || '[]'); } catch (e) { modules = []; }
			renderList();
		};

		// Click handlers
		listEl.addEventListener('click', function (e) {
			var row = e.target.closest('.wchs-modlist__row');
			if (!row) return;
			var idx = parseInt(row.dataset.index);

			if (e.target.closest('.wchs-modlist__edit')) {
				showModuleEditor(modules[idx].type, modules[idx], function (updated) {
					modules[idx] = updated;
					sync();
				});
			}
			if (e.target.closest('.wchs-modlist__dup')) {
				// Deep clone via JSON — modules are pure data (verified against
				// SPA's SiteConfig schema, no functions/Dates/refs).
				var clone = JSON.parse(JSON.stringify(modules[idx]));
				modules.splice(idx + 1, 0, clone);
				sync();
			}
			if (e.target.closest('.wchs-modlist__preset')) {
				savePreset(modules[idx]);
			}
			if (e.target.closest('.wchs-modlist__remove')) {
				modules.splice(idx, 1);
				sync();
			}
		});

		// Double-click row to edit
		listEl.addEventListener('dblclick', function (e) {
			var row = e.target.closest('.wchs-modlist__row');
			if (!row || e.target.closest('.wchs-modlist__edit') || e.target.closest('.wchs-modlist__remove')) return;
			var idx = parseInt(row.dataset.index);
			showModuleEditor(modules[idx].type, modules[idx], function (updated) {
				modules[idx] = updated;
				sync();
			});
		});

		// Add button — opens the slash-style insert menu scoped to this
		// list's context so incompatible types (e.g. shop_grid on PDP) are
		// filtered out.
		var addBtn = containerEl.querySelector('.wchs-modlist__add-btn');
		var ctx = containerEl.dataset.context || 'homepage';
		if (addBtn) {
			addBtn.addEventListener('click', function (e) {
				openInsertMenu(containerEl, ctx, addBtn, function (selection) {
					insertFromSelection(selection, ctx, modules, sync);
				});
			});
		}
		// `/` keypress in the module list also opens the insert menu.
		containerEl.addEventListener('keydown', function (e) {
			if (e.key !== '/') return;
			var target = e.target;
			// Skip typing inputs/textareas/contenteditable
			if (target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable)) return;
			e.preventDefault();
			openInsertMenu(containerEl, ctx, addBtn || containerEl, function (selection) {
				insertFromSelection(selection, ctx, modules, sync);
			});
		});

		// Drag to reorder
		var dragIdx = null;
		listEl.addEventListener('dragstart', function (e) {
			var row = e.target.closest('.wchs-modlist__row');
			if (!row) return;
			dragIdx = parseInt(row.dataset.index);
			row.classList.add('is-dragging');
			e.dataTransfer.effectAllowed = 'move';
		});
		listEl.addEventListener('dragover', function (e) {
			e.preventDefault();
			var row = e.target.closest('.wchs-modlist__row');
			listEl.querySelectorAll('.is-drag-over').forEach(function (r) { r.classList.remove('is-drag-over'); });
			if (row) row.classList.add('is-drag-over');
		});
		listEl.addEventListener('drop', function (e) {
			e.preventDefault();
			var row = e.target.closest('.wchs-modlist__row');
			if (!row || dragIdx === null) return;
			var dropIdx = parseInt(row.dataset.index);
			if (dragIdx !== dropIdx) {
				var item = modules.splice(dragIdx, 1)[0];
				modules.splice(dropIdx, 0, item);
				sync();
			}
			dragIdx = null;
		});
		listEl.addEventListener('dragend', function () {
			listEl.querySelectorAll('.is-dragging, .is-drag-over').forEach(function (r) {
				r.classList.remove('is-dragging', 'is-drag-over');
			});
			dragIdx = null;
		});

		renderList();
	}

	function escHtml(s) {
		var d = document.createElement('div');
		d.textContent = s;
		return d.innerHTML;
	}

	// Scheduled-publishing datetime helpers.
	// PHP stores ISO-8601 UTC; the <input type="datetime-local"> expects
	// local-time "YYYY-MM-DDTHH:MM". Round-trip preserves user intent.
	function isoToLocal(iso) {
		if (!iso) return '';
		var d = new Date(iso);
		if (isNaN(d.getTime())) return '';
		var pad = function (n) { return String(n).padStart(2, '0'); };
		return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate())
			+ 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
	}
	function localToIso(localStr) {
		if (!localStr) return '';
		var d = new Date(localStr);
		if (isNaN(d.getTime())) return '';
		return d.toISOString();
	}

	// Init all module lists on the page
	document.querySelectorAll('.wchs-modlist').forEach(function (el) {
		initModuleList(el);
	});

	// ── Module copy/paste keyboard shortcuts ─────────────────
	// Cmd/Ctrl+C copies the focused row's module to localStorage; Cmd/Ctrl+V
	// pastes it into whichever .wchs-modlist currently contains focus.
	// Guarded so we don't hijack clipboard when typing in text inputs or
	// WYSIWYG editors. Closes over writeClipboard/readClipboard defined above.
	function isCopyPasteTextTarget() {
		var ae = document.activeElement;
		if (!ae) return false;
		var tag = ae.tagName;
		if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return true;
		if (ae.isContentEditable) return true;
		if (tag === 'IFRAME') return true;
		return ae.closest && !!ae.closest('.mce-container, .mce-edit-area, .mce-tinymce');
	}
	// `?` (= Shift+/) anywhere opens the shortcut help. Skip when typing
	// in an input/textarea/contenteditable — otherwise it eats every ? the
	// user types in a field.
	window.addEventListener('keydown', function (e) {
		if (e.key !== '?') return;
		if (isCopyPasteTextTarget()) return;
		e.preventDefault();
		showShortcutHelp();
	});

	window.addEventListener('keydown', function (e) {
		var isCmd = e.ctrlKey || e.metaKey;
		if (!isCmd) return;
		var key = (e.key || '').toLowerCase();
		if (key !== 'c' && key !== 'v') return;
		if (isCopyPasteTextTarget()) return;
		if (key === 'c') {
			var ae = document.activeElement;
			var row = ae && ae.closest ? ae.closest('.wchs-modlist__row') : null;
			if (!row) return;
			var container = row.closest('.wchs-modlist');
			var hidden = container && container.querySelector('input[type="hidden"]');
			if (!hidden) return;
			try {
				var arr = JSON.parse(hidden.value || '[]');
				var idx = parseInt(row.dataset.index, 10);
				var mod = arr[idx];
				if (!mod) return;
				e.preventDefault();
				if (writeClipboard(mod)) toast('Module copied');
				else toast('Could not copy', 'error');
			} catch (err) { /* ignore */ }
		} else if (key === 'v') {
			var ae2 = document.activeElement;
			var list = ae2 && ae2.closest ? ae2.closest('.wchs-modlist') : null;
			if (!list) return;
			var ctx = list.dataset.context || 'homepage';
			var clip = clipboardAllowedIn(ctx);
			if (!clip) {
				if (readClipboard()) {
					e.preventDefault();
					toast("This module can't be used here", 'error');
				}
				return;
			}
			e.preventDefault();
			var hidden2 = list.querySelector('input[type="hidden"]');
			if (!hidden2) return;
			try {
				var arr2 = JSON.parse(hidden2.value || '[]');
				arr2.push(JSON.parse(JSON.stringify(clip)));
				hidden2.value = JSON.stringify(arr2);
				if (typeof list._wchsRefresh === 'function') list._wchsRefresh();
				hidden2.dispatchEvent(new Event('input', { bubbles: true }));
				toast('Module pasted');
			} catch (err) { toast('Paste failed', 'error'); }
		}
	}, true);

	// ── Accordion item CRUD ──────────────────────────────────
	document.addEventListener('click', function (e) {
		var addItemBtn = e.target.closest('.wchs-add-accordion-item');
		if (addItemBtn) {
			var container = addItemBtn.previousElementSibling;
			if (!container) return;
			var moduleEl = addItemBtn.closest('.wchs-module');
			var namePrefix = moduleEl ? moduleEl.dataset.namePrefix : ('modules[' + addItemBtn.dataset.idx + ']');
			var itemIdx = container.querySelectorAll('.wchs-accordion-item').length;
			var div = document.createElement('div');
			div.className = 'wchs-accordion-item';
			div.innerHTML = '<input type="text" name="' + namePrefix + '[items][' + itemIdx + '][q]" placeholder="Question" />'
				+ '<textarea name="' + namePrefix + '[items][' + itemIdx + '][a]" placeholder="Answer" rows="2"></textarea>'
				+ '<button type="button" class="wchs-accordion-item__remove" title="Remove">✕</button>';
			container.appendChild(div);
		}
		// Trust bar presets
		var presetBtn = e.target.closest('.wchs-trust-preset');
		if (presetBtn) {
			var presetModuleEl = presetBtn.closest('.wchs-module');
			var presetNamePrefix = presetModuleEl ? presetModuleEl.dataset.namePrefix : ('modules[' + presetBtn.dataset.idx + ']');
			var presets = {
				general: [
					{ icon: 'shipping', headline: 'Free Shipping Over $50', description: 'Fast, tracked delivery on all domestic orders.' },
					{ icon: 'refresh', headline: 'Easy Returns', description: '30-day hassle-free returns on all orders.' },
					{ icon: 'lock', headline: 'Secure Checkout', description: 'Your payment information is always protected.' },
				],
				supplements: [
					{ icon: 'shipping', headline: 'Fast Express Delivery', description: 'Tracked delivery for time-sensitive orders.' },
					{ icon: 'check', headline: 'Quality Checked', description: 'Every order is reviewed against your quality standards.' },
					{ icon: 'shield', headline: 'Satisfaction Guarantee', description: 'Free replacement if your order arrives damaged.' },
				],
				fashion: [
					{ icon: 'shipping', headline: 'Free Shipping & Returns', description: 'On all orders, no minimum purchase required.' },
					{ icon: 'heart', headline: 'Curated Quality', description: 'Hand-selected pieces from trusted designers.' },
					{ icon: 'refresh', headline: 'Easy Exchanges', description: 'Wrong size? Exchange for free within 30 days.' },
				],
				digital: [
					{ icon: 'zap', headline: 'Instant Delivery', description: 'Download immediately after purchase.' },
					{ icon: 'lock', headline: 'Secure Payment', description: 'Encrypted transactions, your data stays safe.' },
					{ icon: 'phone', headline: '24/7 Support', description: 'Get help anytime via email or live chat.' },
				],
				food: [
					{ icon: 'shipping', headline: 'Protected Delivery', description: 'Packed to match the product handling needs.' },
					{ icon: 'leaf', headline: 'All-Natural Ingredients', description: 'No artificial preservatives or additives.' },
					{ icon: 'award', headline: 'Freshness Guarantee', description: 'Not satisfied? Full refund, no questions asked.' },
				],
			};
			var items = presets[presetBtn.dataset.preset] || presets.general;
			var container = presetBtn.closest('.wchs-field').querySelector('.wchs-accordion-items');
			if (!container) return;
			var icons = ['shipping','lab','shield','star','heart','lock','clock','refresh','check','leaf','gift','award','globe','wallet','users','zap','percent','phone','package','thumbsup'];
			var optsHtml = icons.map(function(i) { return '<option value="'+i+'">'+i.charAt(0).toUpperCase()+i.slice(1)+'</option>'; }).join('');
			items.forEach(function(item, j) {
				var div = document.createElement('div');
				div.className = 'wchs-accordion-item';
				div.innerHTML = '<select name="' + presetNamePrefix + '[items][' + j + '][icon]" style="width:auto;min-width:100px">' + optsHtml + '</select>'
					+ '<input type="text" name="' + presetNamePrefix + '[items][' + j + '][headline]" value="' + item.headline + '" placeholder="Headline" />'
					+ '<input type="text" name="' + presetNamePrefix + '[items][' + j + '][description]" value="' + item.description + '" placeholder="Description" />'
					+ '<button type="button" class="wchs-accordion-item__remove" title="Remove">✕</button>';
				div.querySelector('select').value = item.icon;
				container.appendChild(div);
			});
			// Hide preset buttons after use
			presetBtn.closest('.wchs-gateway-presets').style.display = 'none';
		}

		var addTrustBtn = e.target.closest('.wchs-add-trust-item');
		if (addTrustBtn) {
			var container = addTrustBtn.previousElementSibling;
			if (!container) return;
			var trustModuleEl = addTrustBtn.closest('.wchs-module');
			var trustNamePrefix = trustModuleEl ? trustModuleEl.dataset.namePrefix : ('modules[' + addTrustBtn.dataset.idx + ']');
			var itemIdx = container.querySelectorAll('.wchs-accordion-item').length;
			var icons = ['shipping','lab','shield','star','heart','lock','clock','refresh','check','leaf','gift','award','globe','wallet','users','zap','percent','phone','package','thumbsup'];
			var opts = icons.map(function(i) { return '<option value="'+i+'">'+i.charAt(0).toUpperCase()+i.slice(1)+'</option>'; }).join('');
			var div = document.createElement('div');
			div.className = 'wchs-accordion-item';
			div.innerHTML = '<select name="' + trustNamePrefix + '[items][' + itemIdx + '][icon]" style="width:auto;min-width:100px">' + opts + '</select>'
				+ '<input type="text" name="' + trustNamePrefix + '[items][' + itemIdx + '][headline]" placeholder="Headline" />'
				+ '<input type="text" name="' + trustNamePrefix + '[items][' + itemIdx + '][description]" placeholder="Description" />'
				+ '<button type="button" class="wchs-accordion-item__remove" title="Remove">✕</button>';
			container.appendChild(div);
		}
		// Modal-context add buttons (accordion + split_features inside module editor modal)
		var addListicleFaqsModal = e.target.closest('.wchs-add-listicle-faqs-item-modal');
		if (addListicleFaqsModal) {
			var lfWrap = addListicleFaqsModal.closest('.wchs-field').querySelector('.wchs-listicle-faqs-items');
			if (!lfWrap) return;
			var lfTpl = lfWrap.querySelector('.wchs-accordion-item');
			if (!lfTpl) return;
			var lfClone = lfTpl.cloneNode(true);
			lfClone.querySelectorAll('input, textarea').forEach(function (el) { el.value = ''; });
			lfWrap.appendChild(lfClone);
			return;
		}

		var addReviewsListicleModal = e.target.closest('.wchs-add-reviews-listicle-item-modal');
		if (addReviewsListicleModal) {
			var rlWrap = addReviewsListicleModal.closest('.wchs-field').querySelector('.wchs-reviews-listicle-items');
			if (!rlWrap) return;
			var rlTpl = rlWrap.querySelector('.wchs-accordion-item');
			if (!rlTpl) return;
			var rlClone = rlTpl.cloneNode(true);
			rlClone.querySelectorAll('input, textarea').forEach(function (el) { el.value = ''; });
			var rlRating = rlClone.querySelector('input[type="number"]');
			if (rlRating) rlRating.value = '5';
			rlWrap.appendChild(rlClone);
			return;
		}

		var addListicleModal = e.target.closest('.wchs-add-listicle-item-modal');
		if (addListicleModal) {
			var lcWrap = addListicleModal.closest('.wchs-field').querySelector('.wchs-listicle-items');
			if (!lcWrap) return;
			var lcTpl = lcWrap.querySelector('.wchs-accordion-item');
			if (!lcTpl) return;
			var lcClone = lcTpl.cloneNode(true);
			lcClone.querySelectorAll('input, textarea').forEach(function (el) { el.value = ''; });
			syncListicleItemMedia(lcClone);
			lcWrap.appendChild(lcClone);
			return;
		}

		var addAccordionModal = e.target.closest('.wchs-add-accordion-item-modal');
		if (addAccordionModal) {
			var container = addAccordionModal.previousElementSibling;
			if (!container) return;
			var div = document.createElement('div');
			div.className = 'wchs-accordion-item';
			div.innerHTML = '<input type="text" placeholder="Question" />'
				+ '<textarea placeholder="Answer" rows="3" data-wysiwyg="1"></textarea>'
				+ '<button type="button" class="wchs-accordion-item__remove" title="Remove">✕</button>';
			container.appendChild(div);
			initModalWysiwyg(div);
		}
		var addFhModal = e.target.closest('.wchs-add-fh-item-modal');
		if (addFhModal) {
			var fhWrap = addFhModal.previousElementSibling;
			if (!fhWrap || !fhWrap.classList.contains('wchs-fh-items')) return;
			var fhTpl = fhWrap.querySelector('.wchs-accordion-item');
			if (!fhTpl) return;
			var fhDiv = document.createElement('div');
			fhDiv.innerHTML = fhTpl.outerHTML;
			var fhEl = fhDiv.firstElementChild;
			var fhSel = fhEl.querySelector('[data-field="fh_variant"]');
			if (fhSel) fhSel.value = 'pin';
			fhEl.querySelectorAll('input[type="text"]').forEach(function (inp) { inp.value = ''; });
			fhWrap.appendChild(fhEl);
		}
		var addOhStepModal = e.target.closest('.wchs-add-oh-step-modal');
		if (addOhStepModal) {
			var ohWrap = addOhStepModal.previousElementSibling;
			if (!ohWrap || !ohWrap.classList.contains('wchs-oh-steps')) return;
			var ohTpl = ohWrap.querySelector('.wchs-accordion-item');
			if (!ohTpl) return;
			var ohDiv = document.createElement('div');
			ohDiv.innerHTML = ohTpl.outerHTML;
			var ohEl = ohDiv.firstElementChild;
			var ohSel = ohEl.querySelector('[data-field="oh_step_variant"]');
			if (ohSel) ohSel.value = 'verified';
			ohEl.querySelectorAll('input[type="text"]').forEach(function (inp) { inp.value = ''; });
			ohWrap.appendChild(ohEl);
		}
		var addOhMetricModal = e.target.closest('.wchs-add-oh-metric-modal');
		if (addOhMetricModal) {
			var ohMetWrap = addOhMetricModal.previousElementSibling;
			if (!ohMetWrap || !ohMetWrap.classList.contains('wchs-oh-metrics')) return;
			var ohMetTpl = ohMetWrap.querySelector('.wchs-accordion-item');
			if (!ohMetTpl) return;
			var ohMetDiv = document.createElement('div');
			ohMetDiv.innerHTML = ohMetTpl.outerHTML;
			var ohMetEl = ohMetDiv.firstElementChild;
			ohMetEl.querySelectorAll('input[type="text"]').forEach(function (inp) { inp.value = ''; });
			ohMetWrap.appendChild(ohMetEl);
		}
		var addTbCompareModal = e.target.closest('.wchs-add-tb-compare-row-modal');
		if (addTbCompareModal) {
			var tbWrap = addTbCompareModal.previousElementSibling;
			if (!tbWrap || !tbWrap.classList.contains('wchs-tb-compare-rows')) return;
			var tbTpl = tbWrap.querySelector('.wchs-accordion-item');
			if (!tbTpl) return;
			var tbDiv = document.createElement('div');
			tbDiv.innerHTML = tbTpl.outerHTML;
			var tbEl = tbDiv.firstElementChild;
			var tbInp = tbEl.querySelector('input[type="text"]');
			if (tbInp) tbInp.value = '';
			tbWrap.appendChild(tbEl);
		}
		var addSplitModal = e.target.closest('.wchs-add-splitfeature-item-modal');
		if (addSplitModal) {
			var container = addSplitModal.previousElementSibling;
			if (!container) return;
			var div = document.createElement('div');
			div.className = 'wchs-accordion-item';
			div.style.cssText = 'display:flex;gap:8px;align-items:flex-start;padding:8px;border:1px solid #ddd;background:#fafafa';
			div.innerHTML = '<div style="flex-shrink:0;width:80px">'
				+ '<img src="" style="width:80px;height:80px;object-fit:cover;display:none;border:1px solid #ddd" class="wchs-gallery-thumb" />'
				+ '<input type="hidden" value="" class="wchs-gallery-src" />'
				+ '<button type="button" class="wchs-btn wchs-btn--secondary wchs-gallery-pick" style="font-size:10px;padding:2px 6px;margin-top:4px;width:100%">Choose</button>'
				+ '</div>'
				+ '<div style="flex:1;display:flex;flex-direction:column;gap:4px">'
				+ '<input type="text" placeholder="Eyebrow (e.g. VERIFICATION)" />'
				+ '<input type="text" placeholder="Heading" />'
				+ '<textarea placeholder="Description" rows="3" data-wysiwyg="1"></textarea>'
				+ '</div>'
				+ '<button type="button" class="wchs-accordion-item__remove" title="Remove" style="flex-shrink:0">✕</button>';
			container.appendChild(div);
			initModalWysiwyg(div);
		}
		var addSvBulletModal = e.target.closest('.wchs-add-sv-bullet-modal');
		if (addSvBulletModal) {
			var bCont = addSvBulletModal.previousElementSibling;
			if (!bCont || !bCont.classList.contains('wchs-sv-bullets')) return;
			var div = document.createElement('div');
			div.className = 'wchs-accordion-item';
			div.style.cssText = 'display:flex;gap:8px;align-items:center;padding:6px 8px;border:1px solid #ddd;background:#fafafa';
			div.innerHTML = '<input type="text" style="flex:1" placeholder="Bullet text" />'
				+ '<button type="button" class="wchs-accordion-item__remove" title="Remove">✕</button>';
			bCont.appendChild(div);
		}
		var addSvStatModal = e.target.closest('.wchs-add-sv-stat-modal');
		if (addSvStatModal) {
			var sCont = addSvStatModal.previousElementSibling;
			if (!sCont || !sCont.classList.contains('wchs-sv-stats')) return;
			var div = document.createElement('div');
			div.className = 'wchs-accordion-item';
			div.style.cssText = 'display:grid;grid-template-columns:1fr 1fr auto;gap:8px;align-items:center;padding:6px 8px;border:1px solid #ddd;background:#fafafa';
			div.innerHTML = '<input type="text" placeholder="Value" />'
				+ '<input type="text" placeholder="Label" />'
				+ '<button type="button" class="wchs-accordion-item__remove" title="Remove">✕</button>';
			sCont.appendChild(div);
		}

		var removeItemBtn = e.target.closest('.wchs-accordion-item__remove');
		if (removeItemBtn) {
			var item = removeItemBtn.closest('.wchs-accordion-item, .wchs-gallery-item, .wchs-logo-strip-item');
			if (!item) return;
			destroyWysiwygInElement(item);
			item.remove();
		}
	});

	// ── Product search picker ─────────────────────────────────
	var searchTimers = {};
	document.addEventListener('input', function (e) {
		if (!e.target.classList.contains('wchs-product-search')) return;
		var picker = e.target.closest('.wchs-product-picker');
		if (!picker) return;
		var q = e.target.value.trim();
		var results = picker.querySelector('.wchs-product-results');
		var timerKey = picker.dataset.idx || picker.dataset.field || '0';
		clearTimeout(searchTimers[timerKey]);
		if (q.length < 2) { results.innerHTML = ''; return; }
		searchTimers[timerKey] = setTimeout(function () {
			fetch(wchsAdmin.ajaxUrl + '?action=wchs_product_search&_nonce=' + wchsAdmin.productSearchNonce + '&q=' + encodeURIComponent(q), { credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (d) {
					if (!d.success) { results.innerHTML = ''; return; }
					var hidden = picker.querySelector('.wchs-product-ids-hidden');
					var existing = (hidden.value || '').split(',').filter(Boolean).map(Number);
					results.innerHTML = d.data.map(function (p) {
						var already = existing.indexOf(p.id) >= 0;
						var safeName = escHtml(p.name);
						var safePrice = escHtml(p.price);
						var safeImage = p.image ? escHtml(p.image) : '';
						var typeBadge = p.type === 'variable' ? '<span style="font-size:10px;background:#f0f0f0;padding:1px 5px;border-radius:2px;color:#666;margin-left:6px">Variable</span>' : '';
						return '<div class="wchs-product-result' + (already ? ' is-added' : '') + '" data-id="' + p.id + '" data-name="' + safeName.replace(/"/g, '&quot;') + '" data-price="' + safePrice.replace(/"/g, '&quot;') + '" data-type="' + (p.type || 'simple') + '">'
							+ (safeImage ? '<img src="' + safeImage + '" width="32" height="32" style="object-fit:cover;border-radius:3px" />' : '')
							+ '<span>' + safeName + typeBadge + '</span>'
							+ '<span class="wchs-product-result__price">' + safePrice + '</span>'
							+ (already ? '<span class="wchs-product-result__added">Added</span>' : '')
							+ '</div>';
					}).join('');
				});
		}, 250);
	});

	document.addEventListener('click', function (e) {
		var result = e.target.closest('.wchs-product-result');
		if (result && !result.classList.contains('is-added')) {
			var picker = result.closest('.wchs-product-picker');
			var isSingle = picker.classList.contains('wchs-product-picker--single');
			var hidden = picker.querySelector('.wchs-product-ids-hidden');
			var tags = picker.querySelector('.wchs-product-tags');
			var id = result.dataset.id;
			var name = result.dataset.name;
			var price = result.dataset.price || '';

			if (isSingle) {
				hidden.value = id;
				tags.innerHTML = '';
				var li = document.createElement('li');
				li.className = 'wchs-product-tag';
				li.dataset.id = id;
				li.innerHTML = name + (price ? ' — ' + price : '') + ' <button type="button" class="wchs-product-tag__remove">×</button>';
				tags.appendChild(li);

				// If variable product selected and this picker has a variations container, fetch variations
				var varContainer = picker.querySelector('.wchs-bump-variations');
				var varHidden = picker.querySelector('.wchs-bump-variation-id');
				if (varContainer && result.dataset.type === 'variable') {
					varContainer.innerHTML = '<p style="color:#999;font-size:12px">Loading variations…</p>';
					varContainer.style.display = '';
					if (varHidden) varHidden.value = '0';
					fetch(wchsAdmin.ajaxUrl + '?action=wchs_product_variations&_nonce=' + wchsAdmin.productSearchNonce + '&product_id=' + id, { credentials: 'same-origin' })
						.then(function (r) { return r.json(); })
						.then(function (d) {
							if (!d.success) { varContainer.innerHTML = ''; varContainer.style.display = 'none'; return; }
							var attributes = d.data.attributes;
							var variations = d.data.variations;
							var selectedAttrs = {};

							function render() {
								var html = '<div style="padding:12px;border:1px solid #ddd;border-radius:4px;background:#fff">';

								attributes.forEach(function (attr, attrIdx) {
									// Determine which values are available given current selections
									var availableValues = {};
									attr.values.forEach(function (v) { availableValues[v] = false; });

									variations.forEach(function (vr) {
										// Check if this variation matches all OTHER selected attributes
										var matchesOthers = true;
										attributes.forEach(function (otherAttr, otherIdx) {
											if (otherIdx === attrIdx) return;
											var key = 'attribute_' + otherAttr.slug;
											var sel = selectedAttrs[otherAttr.slug];
											if (sel && vr.attributes[key] && vr.attributes[key] !== '' && vr.attributes[key] !== sel) {
												matchesOthers = false;
											}
										});
										if (matchesOthers) {
											var thisKey = 'attribute_' + attr.slug;
											var thisVal = vr.attributes[thisKey];
											if (thisVal && availableValues.hasOwnProperty(thisVal)) {
												availableValues[thisVal] = true;
											}
										}
									});

									html += '<div style="margin-bottom:10px"><label style="font-size:11px;text-transform:uppercase;letter-spacing:0.06em;color:#999;display:block;margin-bottom:6px">' + escHtml(attr.name) + '</label>';
									html += '<div style="display:flex;gap:4px;flex-wrap:wrap">';
									attr.values.forEach(function (v) {
										var isSelected = selectedAttrs[attr.slug] === v;
										var isAvailable = availableValues[v];
										var style = 'padding:6px 14px;border:1px solid ' + (isSelected ? '#1a1a1a' : isAvailable ? '#ccc' : '#eee')
											+ ';border-radius:3px;background:' + (isSelected ? '#1a1a1a' : '#fff')
											+ ';color:' + (isSelected ? '#fff' : isAvailable ? '#333' : '#bbb')
											+ ';font-size:13px;cursor:' + (isAvailable ? 'pointer' : 'not-allowed')
											+ ';opacity:' + (isAvailable ? '1' : '0.5')
											+ ';text-decoration:' + (isAvailable ? 'none' : 'line-through');
										html += '<button type="button" class="wchs-bump-attr-btn" data-attr="' + escHtml(attr.slug) + '" data-value="' + escHtml(v) + '"'
											+ (isAvailable ? '' : ' disabled')
											+ ' style="' + style + '">' + escHtml(v) + '</button>';
									});
									html += '</div></div>';
								});

								// Show matched variation price + stock
								var matched = findMatch();
								if (matched) {
									html += '<div style="padding:8px 0;font-size:13px;color:#333">'
										+ '<strong>' + matched.price + '</strong>'
										+ (matched.in_stock ? ' <span style="color:#4ade80">In stock</span>' : ' <span style="color:#dc3545">Out of stock</span>')
										+ '</div>';
								} else if (Object.keys(selectedAttrs).length === attributes.length) {
									html += '<div style="padding:8px 0;font-size:12px;color:#dc3545">This combination is not available.</div>';
								}

								html += '</div>';
								varContainer.innerHTML = html;

								// Wire click handlers
								varContainer.querySelectorAll('.wchs-bump-attr-btn').forEach(function (btn) {
									if (btn.disabled) return;
									btn.addEventListener('click', function () {
										var attrSlug = btn.dataset.attr;
										var val = btn.dataset.value;
										if (selectedAttrs[attrSlug] === val) {
											delete selectedAttrs[attrSlug]; // deselect
										} else {
											selectedAttrs[attrSlug] = val;
										}
										render();
									});
								});
							}

							function findMatch() {
								if (Object.keys(selectedAttrs).length !== attributes.length) return null;
								for (var i = 0; i < variations.length; i++) {
									var vr = variations[i];
									var match = true;
									for (var slug in selectedAttrs) {
										var key = 'attribute_' + slug;
										if (vr.attributes[key] && vr.attributes[key] !== '' && vr.attributes[key] !== selectedAttrs[slug]) {
											match = false;
											break;
										}
									}
									if (match) return vr;
								}
								return null;
							}

							// Update hidden field + tag whenever selection changes
							var origRender = render;
							render = function () {
								origRender();
								var matched = findMatch();
								if (matched && matched.in_stock) {
									if (varHidden) varHidden.value = matched.id;
									var tagEl = tags.querySelector('.wchs-product-tag');
									if (tagEl) {
										var attrStr = attributes.map(function (a) { return selectedAttrs[a.slug] || ''; }).filter(Boolean).join(', ');
										tagEl.innerHTML = name + (attrStr ? ' - ' + attrStr : '') + ' — ' + matched.price + ' <button type="button" class="wchs-product-tag__remove">×</button>';
									}
								} else {
									if (varHidden) varHidden.value = '0';
								}
							};

							render();
						});
				} else if (varContainer) {
					varContainer.innerHTML = '';
					varContainer.style.display = 'none';
					if (varHidden) varHidden.value = '0';
				}
			} else {
				var existing = (hidden.value || '').split(',').filter(Boolean);
				existing.push(id);
				hidden.value = existing.join(',');
				var li = document.createElement('li');
				li.className = 'wchs-product-tag';
				li.dataset.id = id;
				li.innerHTML = name + ' <button type="button" class="wchs-product-tag__remove">×</button>';
				tags.appendChild(li);
				result.classList.add('is-added');
				result.querySelector('.wchs-product-result__price').insertAdjacentHTML('afterend', '<span class="wchs-product-result__added">Added</span>');
			}
			picker.querySelector('.wchs-product-search').value = '';
			picker.querySelector('.wchs-product-results').innerHTML = '';
		}
		var removeBtn = e.target.closest('.wchs-product-tag__remove');
		if (removeBtn) {
			var tag = removeBtn.closest('.wchs-product-tag');
			var picker = tag.closest('.wchs-product-picker');
			var isSingle = picker.classList.contains('wchs-product-picker--single');
			var hidden = picker.querySelector('.wchs-product-ids-hidden');
			var id = tag.dataset.id;
			if (isSingle) {
				hidden.value = '0';
			} else {
				var existing = (hidden.value || '').split(',').filter(Boolean);
				hidden.value = existing.filter(function (x) { return x !== id; }).join(',');
			}
			tag.remove();
		}
	});

	// ── Pages tab — add/remove pages + per-page modules ──
	var pagesList = document.getElementById('wchs-pages-list');
	var addPageBtn = document.getElementById('wchs-add-page');
	if (addPageBtn && pagesList) {
		addPageBtn.addEventListener('click', function () {
			var tpl = document.getElementById('wchs-page-template');
			if (!tpl) return;
			var idx = pagesList.querySelectorAll('.wchs-page-card').length;
			var html = tpl.innerHTML.replace(/__PIDX__/g, idx);
			var wrapper = document.createElement('div');
			wrapper.innerHTML = html;
			var card = wrapper.firstElementChild;
			pagesList.appendChild(card);
		});
	}

	document.addEventListener('click', function (e) {
		var removePageBtn = e.target.closest('.wchs-remove-page');
		if (removePageBtn) {
			removePageBtn.closest('.wchs-page-card').remove();
			renumberPages();
		}
	});

	function renumberPages() {
		if (!pagesList) return;
		pagesList.querySelectorAll('.wchs-page-card').forEach(function (card, pi) {
			card.querySelectorAll('input[name^="pages["]').forEach(function (input) {
				input.name = input.name.replace(/pages\[\d+\]/, 'pages[' + pi + ']');
			});
			// Renumber module list hidden field names
			card.querySelectorAll('.wchs-modlist input[type="hidden"]').forEach(function (input) {
				input.name = input.name.replace(/pages\[\d+\]/, 'pages[' + pi + ']');
			});
		});
	}

	// ── Offline gateway presets + CRUD ────────────────────────
	var gatewayContainer = document.getElementById('wchs-gateways');

	function renumberGateways() {
		if (!gatewayContainer) return;
		gatewayContainer.querySelectorAll('.wchs-gateway-card').forEach(function (card, i) {
			card.querySelectorAll('[name^="gateways["]').forEach(function (input) {
				input.name = input.name.replace(/gateways\[\d+\]/, 'gateways[' + i + ']');
			});
		});
	}

	function addGatewayCard(preset) {
		if (!gatewayContainer) return;
		var tpl = document.getElementById('wchs-gateway-template');
		if (!tpl) return;
		var idx = gatewayContainer.querySelectorAll('.wchs-gateway-card').length;
		var presetData = (typeof wchsPresets !== 'undefined' && preset !== 'custom') ? wchsPresets[preset] : null;
		var id = preset === 'custom' ? 'custom_' + Date.now() : preset;
		var html = tpl.innerHTML.replace(/__IDX__/g, idx).replace(/__ID__/g, id);
		var wrapper = document.createElement('div');
		wrapper.innerHTML = html;
		var card = wrapper.firstElementChild;
		gatewayContainer.appendChild(card);
		if (presetData) {
			var fields = card.querySelectorAll('input, textarea');
			fields.forEach(function (f) {
				var name = f.name.replace(/gateways\[\d+\]\[/, '').replace(/\]/, '');
				if (name === 'title' && presetData.title) f.value = presetData.title;
				if (name === 'description' && presetData.description) f.value = presetData.description;
				if (name === 'instructions' && presetData.instructions) f.value = presetData.instructions;
				if (name === 'link_template' && presetData.link_template) f.value = presetData.link_template;
				if (name === 'show_qr' && f.type === 'checkbox') f.checked = !!presetData.show_qr;
				if (name === 'enabled' && f.type === 'checkbox') f.checked = presetData.enabled !== false;
			});
		}
	}

	document.addEventListener('click', function (e) {
		var presetBtn = e.target.closest('.wchs-preset-btn');
		if (presetBtn) {
			addGatewayCard(presetBtn.dataset.preset);
		}
	});

	// Extend module remove to also renumber gateways
	var origRemoveHandler = null;
	document.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-action="remove"]');
		if (btn && btn.closest('.wchs-gateway-card')) {
			setTimeout(renumberGateways, 0);
		}
	});

	// ── Contact form field CRUD ───────────────────────────────
	document.addEventListener('click', function (e) {
		var addCfBtn = e.target.closest('.wchs-add-cf-field');
		if (addCfBtn) {
			var namePrefix = addCfBtn.dataset.namePrefix;
			var container = addCfBtn.closest('.wchs-field').querySelector('.wchs-accordion-items');
			if (!container) return;
			var idx = container.querySelectorAll('.wchs-accordion-item').length;
			var div = document.createElement('div');
			div.className = 'wchs-accordion-item';
			div.style.cssText = 'display:flex;gap:6px;align-items:center;flex-wrap:wrap';
			div.innerHTML = '<input type="text" name="' + namePrefix + '[fields][' + idx + '][name]" placeholder="field_name" style="width:100px" />'
				+ '<input type="text" name="' + namePrefix + '[fields][' + idx + '][label]" placeholder="Label" style="flex:1" />'
				+ '<select name="' + namePrefix + '[fields][' + idx + '][type]" style="width:auto"><option value="text">Text</option><option value="email">Email</option><option value="textarea">Textarea</option><option value="select">Select</option></select>'
				+ '<label style="font-size:12px;text-transform:none;letter-spacing:0;color:#333;display:flex;align-items:center;gap:4px"><input type="checkbox" name="' + namePrefix + '[fields][' + idx + '][required]" value="1" /> Req</label>'
				+ '<button type="button" class="wchs-accordion-item__remove" title="Remove">✕</button>';
			container.appendChild(div);
		}

		var presetBtn = e.target.closest('.wchs-cf-preset');
		if (presetBtn) {
			var namePrefix = presetBtn.dataset.namePrefix;
			var container = presetBtn.closest('.wchs-field').querySelector('.wchs-accordion-items');
			if (!container) return;
			container.innerHTML = '';
			var preset = [
				{ name: 'name', label: 'Your Name', type: 'text', required: true },
				{ name: 'email', label: 'Email Address', type: 'email', required: true },
				{ name: 'subject', label: 'Subject', type: 'text', required: false },
				{ name: 'message', label: 'Message', type: 'textarea', required: true },
			];
			preset.forEach(function (f, idx) {
				var div = document.createElement('div');
				div.className = 'wchs-accordion-item';
				div.style.cssText = 'display:flex;gap:6px;align-items:center;flex-wrap:wrap';
				div.innerHTML = '<input type="text" name="' + namePrefix + '[fields][' + idx + '][name]" value="' + f.name + '" placeholder="field_name" style="width:100px" />'
					+ '<input type="text" name="' + namePrefix + '[fields][' + idx + '][label]" value="' + f.label + '" placeholder="Label" style="flex:1" />'
					+ '<select name="' + namePrefix + '[fields][' + idx + '][type]" style="width:auto"><option value="text"' + (f.type === 'text' ? ' selected' : '') + '>Text</option><option value="email"' + (f.type === 'email' ? ' selected' : '') + '>Email</option><option value="textarea"' + (f.type === 'textarea' ? ' selected' : '') + '>Textarea</option><option value="select">Select</option></select>'
					+ '<label style="font-size:12px;text-transform:none;letter-spacing:0;color:#333;display:flex;align-items:center;gap:4px"><input type="checkbox" name="' + namePrefix + '[fields][' + idx + '][required]" value="1"' + (f.required ? ' checked' : '') + ' /> Req</label>'
					+ '<button type="button" class="wchs-accordion-item__remove" title="Remove">✕</button>';
				container.appendChild(div);
			});
		}

		// ── Modal variants: same behaviors but for rows inside the
		// module editor modal, which uses unnamed inputs (positional
		// read via readContactFields — no name attributes needed). ──
		var addCfModal = e.target.closest('.wchs-add-cf-field-modal');
		if (addCfModal) {
			var container = addCfModal.closest('.wchs-field').querySelector('.wchs-accordion-items');
			if (!container) return;
			var div = document.createElement('div');
			div.className = 'wchs-accordion-item';
			div.style.cssText = 'display:flex;gap:6px;align-items:center;flex-wrap:wrap';
			div.innerHTML = '<input type="text" placeholder="field_name" style="width:100px" />'
				+ '<input type="text" placeholder="Label" style="flex:1" />'
				+ '<select style="width:auto"><option value="text">Text</option><option value="email">Email</option><option value="textarea">Textarea</option><option value="select">Select</option></select>'
				+ '<label style="font-size:12px;text-transform:none;letter-spacing:0;color:#333;display:flex;align-items:center;gap:4px"><input type="checkbox" value="1" /> Req</label>'
				+ '<button type="button" class="wchs-accordion-item__remove" title="Remove">✕</button>';
			container.appendChild(div);
		}

		var addLogoModal = e.target.closest('.wchs-add-logo-item-modal');
		if (addLogoModal) {
			var logoContainer = addLogoModal.closest('.wchs-field').querySelector('.wchs-logo-strip-items');
			if (!logoContainer) return;
			if (logoContainer.querySelectorAll('.wchs-logo-strip-item').length >= 5) return;
			var logoDiv = document.createElement('div');
			logoDiv.className = 'wchs-logo-strip-item';
			logoDiv.style.cssText = 'display:flex;gap:8px;align-items:flex-start;margin-bottom:8px;padding:8px;border:1px solid #ddd;background:#fafafa';
			logoDiv.innerHTML = '<div style="flex-shrink:0;width:80px">'
				+ '<img src="" style="width:80px;height:48px;object-fit:contain;display:none;border:1px solid #ddd;background:#fff" class="wchs-logo-thumb" />'
				+ '<input type="hidden" value="" class="wchs-logo-src" />'
				+ '<button type="button" class="wchs-btn wchs-btn--secondary wchs-logo-pick" style="font-size:10px;padding:2px 6px;margin-top:4px;width:100%">Choose</button>'
				+ '</div>'
				+ '<div style="flex:1;display:flex;flex-direction:column;gap:4px">'
				+ '<input type="text" placeholder="Alt text" />'
				+ '<input type="text" placeholder="Link (optional)" />'
				+ '</div>'
				+ '<button type="button" class="wchs-accordion-item__remove" title="Remove" style="flex-shrink:0">&#10005;</button>';
			logoContainer.appendChild(logoDiv);
		}

		var logoPickBtn = e.target.closest('.wchs-logo-pick');
		if (logoPickBtn && typeof wp !== 'undefined' && wp.media) {
			var logoItem = logoPickBtn.closest('.wchs-logo-strip-item');
			var logoSrc = logoItem.querySelector('.wchs-logo-src');
			var logoThumb = logoItem.querySelector('.wchs-logo-thumb');
			var logoFrame = wp.media({ title: 'Select logo', multiple: false, library: { type: 'image' } });
			logoFrame.on('select', function () {
				var a = logoFrame.state().get('selection').first().toJSON();
				logoSrc.value = a.url;
				logoThumb.src = a.url;
				logoThumb.style.display = 'block';
			});
			logoFrame.open();
		}

		var addGalleryModal = e.target.closest('.wchs-add-gallery-item-modal');
		if (addGalleryModal) {
			var galContainer = addGalleryModal.closest('.wchs-field').querySelector('.wchs-gallery-items');
			if (!galContainer) return;
			var galDiv = document.createElement('div');
			galDiv.className = 'wchs-gallery-item';
			galDiv.style.cssText = 'display:flex;gap:8px;align-items:flex-start;margin-bottom:8px;padding:8px;border:1px solid #ddd;background:#fafafa';
			galDiv.innerHTML = '<div style="flex-shrink:0;width:80px">'
				+ '<img src="" style="width:80px;height:80px;object-fit:cover;display:none;border:1px solid #ddd" class="wchs-gallery-thumb" />'
				+ '<input type="hidden" value="" class="wchs-gallery-src" />'
				+ '<button type="button" class="wchs-btn wchs-btn--secondary wchs-gallery-pick" style="font-size:10px;padding:2px 6px;margin-top:4px;width:100%">Choose</button>'
				+ '</div>'
				+ '<div style="flex:1;display:flex;flex-direction:column;gap:4px">'
				+ '<input type="text" placeholder="Title (optional)" />'
				+ '<input type="text" placeholder="Description (optional)" />'
				+ '</div>'
				+ '<button type="button" class="wchs-accordion-item__remove" title="Remove" style="flex-shrink:0">&#10005;</button>';
			galContainer.appendChild(galDiv);
		}

		var presetModal = e.target.closest('.wchs-cf-preset-modal');
		if (presetModal) {
			var container = presetModal.closest('.wchs-field').querySelector('.wchs-accordion-items');
			if (!container) return;
			container.innerHTML = '';
			var preset = [
				{ name: 'name', label: 'Your Name', type: 'text', required: true },
				{ name: 'email', label: 'Email Address', type: 'email', required: true },
				{ name: 'subject', label: 'Subject', type: 'text', required: false },
				{ name: 'message', label: 'Message', type: 'textarea', required: true },
			];
			preset.forEach(function (f) {
				var div = document.createElement('div');
				div.className = 'wchs-accordion-item';
				div.style.cssText = 'display:flex;gap:6px;align-items:center;flex-wrap:wrap';
				div.innerHTML = '<input type="text" value="' + f.name + '" placeholder="field_name" style="width:100px" />'
					+ '<input type="text" value="' + f.label + '" placeholder="Label" style="flex:1" />'
					+ '<select style="width:auto"><option value="text"' + (f.type === 'text' ? ' selected' : '') + '>Text</option><option value="email"' + (f.type === 'email' ? ' selected' : '') + '>Email</option><option value="textarea"' + (f.type === 'textarea' ? ' selected' : '') + '>Textarea</option><option value="select">Select</option></select>'
				+ '<label style="font-size:12px;text-transform:none;letter-spacing:0;color:#333;display:flex;align-items:center;gap:4px"><input type="checkbox" value="1"' + (f.required ? ' checked' : '') + ' /> Req</label>'
					+ '<button type="button" class="wchs-accordion-item__remove" title="Remove">✕</button>';
				container.appendChild(div);
			});
		}
	});

	// ── Category grid + split feature item CRUD ──────────────
	document.addEventListener('click', function (e) {
		var addCatBtn = e.target.closest('.wchs-add-catgrid-item');
		if (addCatBtn) {
			var namePrefix = addCatBtn.dataset.namePrefix;
			var container = addCatBtn.previousElementSibling;
			if (!container) return;
			var idx = container.querySelectorAll('.wchs-accordion-item').length;
			// Fetch categories for the select — grab from an existing select in the same module
			var existingSelect = container.querySelector('select');
			var catOptions = existingSelect ? existingSelect.innerHTML : '<option value="">— Select category —</option>';
			var div = document.createElement('div');
			div.className = 'wchs-accordion-item';
			div.style.cssText = 'display:flex;gap:8px;align-items:center';
			div.innerHTML = '<select name="' + namePrefix + '[items][' + idx + '][category_id]" style="flex:1">' + catOptions + '</select>'
				+ '<input type="hidden" name="' + namePrefix + '[items][' + idx + '][image]" value="" class="wchs-gallery-src" />'
				+ '<button type="button" class="wchs-btn wchs-btn--secondary wchs-gallery-pick" style="font-size:10px;padding:2px 8px;flex-shrink:0">Add image</button>'
				+ '<button type="button" class="wchs-accordion-item__remove" title="Remove">✕</button>';
			container.appendChild(div);
		}

		var addSfBtn = e.target.closest('.wchs-add-splitfeature-item');
		if (addSfBtn) {
			var namePrefix = addSfBtn.dataset.namePrefix;
			var container = addSfBtn.previousElementSibling;
			if (!container) return;
			var idx = container.querySelectorAll('.wchs-accordion-item').length;
			var div = document.createElement('div');
			div.className = 'wchs-accordion-item';
			div.style.cssText = 'display:flex;gap:8px;align-items:flex-start;padding:8px;border:1px solid #ddd;background:#fafafa';
			div.innerHTML = '<div style="flex-shrink:0;width:80px">'
				+ '<img src="" style="width:80px;height:80px;object-fit:cover;display:none;border:1px solid #ddd" class="wchs-gallery-thumb" />'
				+ '<input type="hidden" name="' + namePrefix + '[items][' + idx + '][image]" value="" class="wchs-gallery-src" />'
				+ '<button type="button" class="wchs-btn wchs-btn--secondary wchs-gallery-pick" style="font-size:10px;padding:2px 6px;margin-top:4px;width:100%">Choose</button>'
				+ '</div>'
				+ '<div style="flex:1;display:flex;flex-direction:column;gap:4px">'
				+ '<input type="text" name="' + namePrefix + '[items][' + idx + '][eyebrow]" placeholder="Eyebrow (e.g. VERIFICATION)" />'
				+ '<input type="text" name="' + namePrefix + '[items][' + idx + '][heading]" placeholder="Heading" />'
				+ '<textarea name="' + namePrefix + '[items][' + idx + '][description]" placeholder="Description" rows="2"></textarea>'
				+ '</div>'
				+ '<button type="button" class="wchs-accordion-item__remove" title="Remove" style="flex-shrink:0">✕</button>';
			container.appendChild(div);
		}
	});

	// ── Gallery item CRUD + media picker ─────────────────────
	document.addEventListener('click', function (e) {
		var addGalleryBtn = e.target.closest('.wchs-add-gallery-item');
		if (addGalleryBtn) {
			var namePrefix = addGalleryBtn.dataset.namePrefix;
			var container = addGalleryBtn.previousElementSibling;
			if (!container || !container.classList.contains('wchs-gallery-items')) {
				container = addGalleryBtn.closest('.wchs-field').querySelector('.wchs-gallery-items');
			}
			if (!container) return;
			var idx = container.querySelectorAll('.wchs-gallery-item').length;
			var div = document.createElement('div');
			div.className = 'wchs-gallery-item';
			div.style.cssText = 'display:flex;gap:8px;align-items:flex-start;margin-bottom:8px;padding:8px;border:1px solid #ddd;background:#fafafa';
			div.innerHTML = '<div style="flex-shrink:0;width:80px">'
				+ '<img src="" style="width:80px;height:80px;object-fit:cover;display:none;border:1px solid #ddd" class="wchs-gallery-thumb" />'
				+ '<input type="hidden" name="' + namePrefix + '[items][' + idx + '][src]" value="" class="wchs-gallery-src" />'
				+ '<button type="button" class="wchs-btn wchs-btn--secondary wchs-gallery-pick" style="font-size:10px;padding:2px 6px;margin-top:4px;width:100%">Choose</button>'
				+ '</div>'
				+ '<div style="flex:1;display:flex;flex-direction:column;gap:4px">'
				+ '<input type="text" name="' + namePrefix + '[items][' + idx + '][title]" placeholder="Title (optional)" />'
				+ '<input type="text" name="' + namePrefix + '[items][' + idx + '][description]" placeholder="Description (optional)" />'
				+ '</div>'
				+ '<button type="button" class="wchs-accordion-item__remove" title="Remove" style="flex-shrink:0">✕</button>';
			container.appendChild(div);
		}

		var galleryPickBtn = e.target.closest('.wchs-gallery-pick');
		if (galleryPickBtn) {
			var item = galleryPickBtn.closest('.wchs-gallery-item');
			var srcInput = item.querySelector('.wchs-gallery-src');
			var thumb = item.querySelector('.wchs-gallery-thumb');
			var frame = wp.media({ title: 'Select Image', multiple: false, library: { type: 'image' } });
			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				srcInput.value = attachment.url;
				thumb.src = attachment.url;
				thumb.style.display = 'block';
			});
			frame.open();
		}
	});

	// ── Media library picker ──────────────────────────────────
	document.addEventListener('click', function (e) {
		var selectBtn = e.target.closest('.wchs-media-select');
		if (selectBtn) {
			var field = selectBtn.closest('.wchs-field');
			var urlInput = field.querySelector('.wchs-media-url');
			var removeBtn = field.querySelector('.wchs-media-remove');
			var preview = field.querySelector('.wchs-media-preview');
			var frame = wp.media({ title: 'Select Image', multiple: false, library: { type: 'image' } });
			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				urlInput.value = attachment.url;
				if (removeBtn) removeBtn.style.display = '';
				if (preview) { preview.src = attachment.url; preview.style.display = ''; }
			});
			frame.open();
		}
		var removeBtn = e.target.closest('.wchs-media-remove');
		if (removeBtn) {
			var field = removeBtn.closest('.wchs-field');
			var urlInput = field.querySelector('.wchs-media-url');
			var preview = field.querySelector('.wchs-media-preview');
			urlInput.value = '';
			removeBtn.style.display = 'none';
			if (preview) { preview.src = ''; preview.style.display = 'none'; }
		}
	});

	// ── Hero trust items ──────────────────────────────────────
	var heroTrustBtn = document.getElementById('wchs-add-hero-trust');
	if (heroTrustBtn) {
		heroTrustBtn.addEventListener('click', function () {
			var container = document.getElementById('wchs-hero-trust-items');
			if (!container) return;
			var idx = container.querySelectorAll('.wchs-accordion-item').length;
			var icons = ['check','shield','star','shipping','lock','lab','heart','leaf','zap','award'];
			var opts = icons.map(function(i) { return '<option value="'+i+'">'+i.charAt(0).toUpperCase()+i.slice(1)+'</option>'; }).join('');
			var div = document.createElement('div');
			div.className = 'wchs-accordion-item';
			div.innerHTML = '<select name="hero_trust_items[' + idx + '][icon]" style="width:auto;min-width:80px">' + opts + '</select>'
				+ '<input type="text" name="hero_trust_items[' + idx + '][text]" placeholder="e.g. Third-party tested" />'
				+ '<button type="button" class="wchs-accordion-item__remove" title="Remove">✕</button>';
			container.appendChild(div);
		});
	}

	// ── Review provider field toggling ────────────────────────
	var rpSelect = document.getElementById('wchs-review-provider');
	if (rpSelect) {
		rpSelect.addEventListener('change', function () {
			var val = rpSelect.value;
			document.querySelectorAll('.wchs-rp-field').forEach(function (f) {
				f.style.display = f.dataset.provider === val ? '' : 'none';
			});
		});
	}

	// ── Header link CRUD ─────────────────────────────────────
	var headerLinksContainer = document.getElementById('wchs-header-links');
	var addHeaderLinkBtn = document.getElementById('wchs-add-header-link');
	var iconPickerTpl = document.getElementById('wchs-icon-picker-tpl');
	if (addHeaderLinkBtn && headerLinksContainer) {
		addHeaderLinkBtn.addEventListener('click', function () {
			var idx = headerLinksContainer.querySelectorAll('.wchs-header-link').length;
			var div = document.createElement('div');
			div.className = 'wchs-header-link';
			div.style.cssText = 'display:flex;gap:6px;margin-bottom:8px;align-items:flex-start;flex-wrap:wrap';
			// Clone the icon picker template
			var pickerHtml = iconPickerTpl ? iconPickerTpl.innerHTML : '';
			// Set the hidden input name
			pickerHtml = pickerHtml.replace('class="wchs-icon-picker__value"', 'class="wchs-icon-picker__value" name="header_links[' + idx + '][icon]"');
			div.innerHTML = '<input type="text" name="header_links[' + idx + '][label]" placeholder="Label" style="width:100px" />'
				+ '<input type="text" name="header_links[' + idx + '][url]" placeholder="/path" style="width:100px" />'
				+ '<select name="header_links[' + idx + '][display]" style="width:auto"><option value="text">Text</option><option value="icon">Icon</option><option value="both">Both</option></select>'
				+ pickerHtml
				+ '<label style="font-size:12px;text-transform:none;letter-spacing:0;color:#333;display:flex;align-items:center;gap:4px"><input type="checkbox" name="header_links[' + idx + '][accent]" value="1" /> Accent</label>'
				+ '<label style="font-size:12px;text-transform:none;letter-spacing:0;color:#333;display:flex;align-items:center;gap:4px" title="Pin this link inline on mobile (otherwise it goes into the hamburger drawer)"><input type="checkbox" name="header_links[' + idx + '][mobile_pin]" value="1" /> Pin on mobile</label>'
				+ '<button type="button" class="wchs-accordion-item__remove" title="Remove">✕</button>';
			headerLinksContainer.appendChild(div);
		});
	}

	// ── Footer column/link CRUD ──────────────────────────────
	var footerColsContainer = document.getElementById('wchs-footer-columns');
	var addFooterColBtn = document.getElementById('wchs-add-footer-col');
	if (addFooterColBtn && footerColsContainer) {
		addFooterColBtn.addEventListener('click', function () {
			var ci = footerColsContainer.querySelectorAll('.wchs-footer-col').length;
			if (ci >= 5) return;
			var div = document.createElement('div');
			div.className = 'wchs-footer-col';
			div.style.cssText = 'border:1px solid #ddd;padding:12px 16px;margin-bottom:12px;background:#fff';
			div.innerHTML = '<div style="display:flex;gap:8px;align-items:center;margin-bottom:10px">'
				+ '<input type="text" name="footer_columns[' + ci + '][title]" placeholder="Column title" style="flex:1" />'
				+ '<button type="button" class="wchs-btn wchs-btn--secondary wchs-remove-footer-col" style="color:#a00">Remove</button>'
				+ '</div>'
				+ '<div class="wchs-footer-links"></div>'
				+ '<button type="button" class="wchs-btn wchs-btn--secondary wchs-add-footer-link" data-col-idx="' + ci + '" style="font-size:11px;padding:4px 10px">+ Add Link</button>';
			footerColsContainer.appendChild(div);
		});
	}

	document.addEventListener('click', function (e) {
		var removeColBtn = e.target.closest('.wchs-remove-footer-col');
		if (removeColBtn) {
			removeColBtn.closest('.wchs-footer-col').remove();
			renumberFooterCols();
		}
		var addLinkBtn = e.target.closest('.wchs-add-footer-link');
		if (addLinkBtn) {
			var col = addLinkBtn.closest('.wchs-footer-col');
			var linksContainer = col.querySelector('.wchs-footer-links');
			var colIdx = addLinkBtn.dataset.colIdx;
			var li = linksContainer.querySelectorAll('.wchs-footer-link').length;
			var div = document.createElement('div');
			div.className = 'wchs-footer-link';
			div.style.cssText = 'display:flex;gap:6px;margin-bottom:6px';
			div.innerHTML = '<input type="text" name="footer_columns[' + colIdx + '][links][' + li + '][label]" placeholder="Label" style="flex:1" />'
				+ '<input type="text" name="footer_columns[' + colIdx + '][links][' + li + '][url]" placeholder="/slug or https://..." style="flex:1" />'
				+ '<button type="button" class="wchs-accordion-item__remove" title="Remove link">✕</button>';
			linksContainer.appendChild(div);
		}
	});

	function renumberFooterCols() {
		if (!footerColsContainer) return;
		footerColsContainer.querySelectorAll('.wchs-footer-col').forEach(function (col, ci) {
			col.querySelector('[name^="footer_columns["]').name = 'footer_columns[' + ci + '][title]';
			col.querySelector('.wchs-add-footer-link').dataset.colIdx = ci;
			col.querySelectorAll('.wchs-footer-link').forEach(function (link, li) {
				var inputs = link.querySelectorAll('input');
				if (inputs[0]) inputs[0].name = 'footer_columns[' + ci + '][links][' + li + '][label]';
				if (inputs[1]) inputs[1].name = 'footer_columns[' + ci + '][links][' + li + '][url]';
			});
		});
	}

	// ── Text block preview ───────────────────────────────────
	document.addEventListener('click', function (e) {
		var previewBtn = e.target.closest('.wchs-preview-text');
		if (!previewBtn) return;
		var editorId = previewBtn.dataset.editorId;
		var content = '';
		// Try TinyMCE first, fall back to textarea
		if (typeof tinymce !== 'undefined' && tinymce.get(editorId)) {
			content = tinymce.get(editorId).getContent();
		} else {
			var ta = document.getElementById(editorId);
			if (ta) content = ta.value;
		}
		if (!content.trim()) { alert('No content to preview.'); return; }

		var overlay = document.createElement('div');
		overlay.style.cssText = 'position:fixed;inset:0;z-index:100000;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.6)';
		var panel = document.createElement('div');
		panel.style.cssText = 'background:#fff;max-width:820px;width:calc(100% - 48px);max-height:80vh;overflow-y:auto;padding:40px 32px;position:relative;font-family:Inter,-apple-system,sans-serif;color:#1a1a1a;line-height:1.6';
		panel.innerHTML = '<button style="position:absolute;top:12px;right:12px;background:none;border:1px solid #ddd;width:32px;height:32px;cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center" onclick="this.closest(\'div[style*=fixed]\').remove()">✕</button>'
			+ '<div class="wchs-text-preview">' + content + '</div>';

		// Apply preview styles inline
		var style = document.createElement('style');
		style.textContent = '.wchs-text-preview h1{font-size:42px;font-weight:600;letter-spacing:-0.03em;line-height:1.1;margin:0 0 16px}'
			+ '.wchs-text-preview h2{font-size:30px;font-weight:600;letter-spacing:-0.02em;line-height:1.2;margin:0 0 14px}'
			+ '.wchs-text-preview h3{font-size:22px;font-weight:600;letter-spacing:-0.01em;line-height:1.3;margin:0 0 12px}'
			+ '.wchs-text-preview h4{font-size:16px;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;margin:0 0 10px}'
			+ '.wchs-text-preview p{font-size:16px;line-height:1.6;margin:0 0 14px}'
			+ '.wchs-text-preview ul,.wchs-text-preview ol{padding-left:24px;margin:0 0 14px;font-size:16px;line-height:1.6}'
			+ '.wchs-text-preview blockquote{border-left:3px solid #ccc;padding:0 0 0 20px;margin:14px 0;font-style:italic;color:#666}'
			+ '.wchs-text-preview a{color:#2563eb;text-decoration:underline}';
		panel.appendChild(style);
		overlay.appendChild(panel);
		overlay.addEventListener('click', function (ev) { if (ev.target === overlay) overlay.remove(); });
		document.body.appendChild(overlay);
	});

})();

/* ═══════════════════════════════════════════════════════════
   Page Index Selector — scroll to page card + update preview
   ═══════════════════════════════════════════════════════════ */
(function () {
	'use strict';
	var selector = document.getElementById('wchs-page-selector');
	if (!selector) return;

	selector.addEventListener('change', function () {
		var slug = selector.value;
		if (!slug) return;

		// Scroll the panel body to the matching page card
		var option = selector.selectedOptions[0];
		var index = option ? option.dataset.index : null;
		if (index !== null) {
			var cards = document.querySelectorAll('.wchs-page-card');
			var target = cards[parseInt(index)];
			if (target) {
				target.scrollIntoView({ behavior: 'smooth', block: 'start' });
			}
		}

		// Current canvas uses artboard chips, not the old singleton iframe.
		// Ensure the selected page is visible in the canvas.
		var chipSelector = '.wchs-chip[data-slug="' + (window.CSS && CSS.escape ? CSS.escape(slug) : slug.replace(/"/g, '\\"')) + '"]';
		var chip = document.querySelector(chipSelector);
		if (chip && !chip.classList.contains('is-active')) {
			chip.click();
		}

		// Reset selector to placeholder
		selector.selectedIndex = 0;
	});
})();

/* ═══════════════════════════════════════════════════════════
   Canvas Editor — artboard manager, device switching, zoom
   ═══════════════════════════════════════════════════════════ */
(function () {
	'use strict';

	var admin = document.querySelector('.wchs-admin--has-canvas');
	if (!admin) return;

	// Lock outer page scroll and fit the editor to the remaining viewport.
	// The hardcoded `100vh - 76px` in CSS was 11px short and didn't account
	// for WP's footer, so the body scrolled on top of the editor's own
	// scroll regions. Measure the actual top offset and use that instead.
	var editor = admin.querySelector('.wchs-editor:not(.wchs-editor--no-canvas)');
	if (editor) {
		document.body.style.overflow = 'hidden';
		document.documentElement.style.overflow = 'hidden';
		var sizeEditor = function () {
			var top = editor.getBoundingClientRect().top + window.scrollY;
			editor.style.height = 'calc(100vh - ' + top + 'px)';
		};
		sizeEditor();
		window.addEventListener('resize', sizeEditor);
	}

	var divider  = admin.querySelector('.wchs-editor__divider');
	var panel    = admin.querySelector('.wchs-editor__panel');
	var canvas   = admin.querySelector('.wchs-editor__canvas');
	var surface  = document.getElementById('wchs-canvas-surface');
	var zoomLabel = admin.querySelector('.wchs-zoom-label');

	if (!divider || !panel || !canvas || !surface) return;

	var spaOrigin  = canvas.dataset.spaOrigin || 'http://localhost:5175';
	var artboards  = [];
	try { artboards = JSON.parse(canvas.dataset.artboards || '[]'); } catch(e) {}
	var activeScripts = [];
	try { activeScripts = JSON.parse(canvas.dataset.activeScripts || '[]'); } catch(e) {}
	// Only SPA-surface scripts matter for iframe previews — all artboards load SPA routes
	activeScripts = activeScripts.filter(function (s) {
		return !s.surfaces || s.surfaces.indexOf('spa') !== -1;
	});

	function escAttrLocal(s) {
		return String(s == null ? '' : s)
			.replace(/&/g, '&amp;').replace(/"/g, '&quot;')
			.replace(/</g, '&lt;').replace(/>/g, '&gt;');
	}

	function renderTrackerChips(scripts) {
		if (!scripts || !scripts.length) return '';
		var visible = scripts.slice(0, 3);
		var hidden = scripts.slice(3);
		var html = '<div class="wchs-artboard__meta">';
		visible.forEach(function (s) {
			var cat = (s.category || 'other').replace(/[^a-z]/g, '');
			html += '<span class="wchs-chip-tracker wchs-chip-tracker--' + cat +
					'" title="' + escAttrLocal(s.name) + '" data-script-id="' + escAttrLocal(s.id) + '">' +
					'<span class="wchs-chip-tracker__dot"></span>' +
					'<span class="wchs-chip-tracker__mark">' + escAttrLocal(s.mark || '?') + '</span>' +
					'</span>';
		});
		if (hidden.length) {
			var names = hidden.map(function (s) { return s.name; }).join(', ');
			html += '<span class="wchs-chip-tracker wchs-chip-tracker--overflow" title="' +
					escAttrLocal(names) + '">+' + hidden.length + '</span>';
		}
		html += '</div>';
		return html;
	}

	// ─── Device dimensions ───
	var DEVICES = {
		desktop: { w: 1440, scale: 0.25 },
		tablet:  { w: 768,  scale: 0.35 },
		mobile:  { w: 393,  scale: 0.5 },
	};
	var currentDevice = 'desktop';
	var currentZoom = 1;

	// Restore panel width
	var storedWidth = localStorage.getItem('wchs_panel_width');
	if (storedWidth) {
		var w = parseInt(storedWidth);
		if (w >= 280 && w <= 600) panel.style.width = w + 'px';
	}

	// ─── Artboard factory ───
	// Preserve scroll position on BOTH the window and the panel-body around
	// iframe size changes. The actual scroll container for most of the admin
	// is `.wchs-editor__panel-body` (overflow-y:auto), so preserving only
	// window.scrollY misses the real jolt source when an iframe resize
	// cascades into a panel layout shift.
	var panelBodyForResize = document.querySelector('.wchs-editor__panel-body');
	function updateArtboardSize(art, contentHeight) {
		var d = DEVICES[currentDevice];
		var prevY = window.scrollY;
		var prevX = window.scrollX;
		var prevPanelTop = panelBodyForResize ? panelBodyForResize.scrollTop : 0;
		art.iframe.style.width = d.w + 'px';
		art.iframe.style.height = contentHeight + 'px';
		art.frame.style.width = Math.round(d.w * d.scale) + 'px';
		art.frame.style.height = Math.round(contentHeight * d.scale) + 'px';
		art.iframe.style.transform = 'scale(' + d.scale + ')';
		// Sync check — covers synchronous reflow cases.
		if (window.scrollY !== prevY || window.scrollX !== prevX) {
			window.scrollTo({ top: prevY, left: prevX, behavior: 'instant' });
		}
		if (panelBodyForResize && panelBodyForResize.scrollTop !== prevPanelTop) {
			panelBodyForResize.scrollTop = prevPanelTop;
		}
		// Async check — browser may defer the iframe-size reflow to the next
		// frame, by which point the sync check above already passed. Extend
		// the scroll-jolt revert window so the onPanelScroll/onWindowScroll
		// listeners pick up any scroll that fires during the reflow.
		if (typeof window.__wchsExtendJoltRevert === 'function') {
			window.__wchsExtendJoltRevert(300);
		}
	}

	// slug+group → settings tab URL
	function artboardTargetUrl(slug, group) {
		var baseParams = new URLSearchParams(window.location.search);
		baseParams.delete('tab');
		baseParams.delete('focus');
		var base = window.location.pathname + '?' + baseParams.toString();
		if (group !== 'content') {
			var tab = ({ home: 'homepage', shop: 'shop', pdp: 'pdp' })[slug] || 'homepage';
			return base + '&tab=' + tab;
		}
		return base + '&tab=pages&focus=' + encodeURIComponent(slug);
	}

	function createArtboard(slug, title, path, eager, group) {
		var wrapper = document.createElement('div');
		wrapper.className = 'wchs-artboard';
		wrapper.style.opacity = '0';
		wrapper.style.transform = 'scale(0.95)';
		wrapper.style.transition = 'opacity 0.2s ease, transform 0.2s ease';

		var label = document.createElement('div');
		label.className = 'wchs-artboard__label';
		label.textContent = title || slug || 'Home';
		wrapper.appendChild(label);

		var metaHtml = renderTrackerChips(activeScripts);
		if (metaHtml) {
			var metaWrap = document.createElement('div');
			metaWrap.innerHTML = metaHtml;
			wrapper.appendChild(metaWrap.firstChild);
		}

		var frame = document.createElement('div');
		frame.className = 'wchs-artboard__frame';
		wrapper.appendChild(frame);

		// Track drag-vs-click so we don't navigate on pan release
		var mdX = 0, mdY = 0, mdMoved = false;
		wrapper.addEventListener('mousedown', function (e) {
			if (e.button !== 0) return;
			mdX = e.clientX; mdY = e.clientY; mdMoved = false;
		});
		wrapper.addEventListener('mousemove', function (e) {
			if (Math.abs(e.clientX - mdX) > 4 || Math.abs(e.clientY - mdY) > 4) mdMoved = true;
		});
		wrapper.addEventListener('click', function (e) {
			if (mdMoved) return;
			if (spaceHeld || panDrag) return;
			window.location.href = artboardTargetUrl(slug, group);
		});

		var iframe = document.createElement('iframe');
		iframe.className = 'wchs-artboard__iframe';
		var themeQ = (typeof window.__wchsPreviewTheme === 'function')
			? '&theme=' + encodeURIComponent(window.__wchsPreviewTheme())
			: '';
		iframe.src = spaOrigin + path + '?preview=1' + themeQ;
		iframe.title = 'Preview: ' + (title || slug);
		iframe.loading = eager ? 'eager' : 'lazy';
		iframe.setAttribute('scrolling', 'no');
		// Sandbox isolates SPA errors (WebGL shader crashes, Three.js context
		// loss) from bubbling into the admin page. Keep the three allowances
		// we need: same-origin for cookies + postMessage, scripts for the SPA
		// runtime, forms for in-preview submissions.
		iframe.setAttribute('sandbox', 'allow-same-origin allow-scripts allow-forms');
		frame.appendChild(iframe);

		surface.appendChild(wrapper);

		// Animate in
		requestAnimationFrame(function () {
			wrapper.style.opacity = '1';
			wrapper.style.transform = 'scale(1)';
		});

		var artObj = { wrapper: wrapper, frame: frame, iframe: iframe, slug: slug, sizedFromObserver: false };

		// Height is primarily driven by the SPA's ResizeObserver (see
		// +layout.svelte) posting __wchs_preview_size. This load handler
		// only pushes config + registers a fallback: if no observer
		// message lands within 3s (SPA build without the observer, or
		// cross-origin iframe), measure scrollHeight once as a safety net.
		iframe.addEventListener('load', function () {
			readyIframes.add(iframe);
			pushPreviewToIframe(iframe);
			setTimeout(function () {
				if (artObj.sizedFromObserver) return;
				try {
					var doc = iframe.contentDocument || iframe.contentWindow.document;
					var height = Math.max(doc.body.scrollHeight, doc.documentElement.scrollHeight, 200);
					updateArtboardSize(artObj, height);
				} catch (e) {
					updateArtboardSize(artObj, 800);
				}
			}, 3000);
		});

		return artObj;
	}

	// ─── Create initial artboards ───
	var artboardEls = [];
	var readyIframes = new Set();
	artboards.forEach(function (art, i) {
		var slug = art.slug || '';
		var path = art.path || (slug ? '/' + slug : '/');
		var obj = createArtboard(slug, art.title || slug, path, i === 0, art.group);
		artboardEls.push(obj);
	});

	// ─── Chip bar: page toggle logic ───
	var chipBar = document.getElementById('wchs-chip-bar');
	var chipCount = document.getElementById('wchs-chip-count');

	function updateChipCount() {
		if (!chipCount) return;
		var active = chipBar ? chipBar.querySelectorAll('.wchs-chip.is-active:not(.wchs-chip--preset)').length : 0;
		var total = chipBar ? chipBar.querySelectorAll('.wchs-chip:not(.wchs-chip--preset)').length : 0;
		chipCount.textContent = active + '/' + total;
	}

	function updatePresetStates() {
		if (!chipBar) return;
		var pageChips = chipBar.querySelectorAll('.wchs-chip:not(.wchs-chip--preset)');
		var activeSlugs = new Set();
		pageChips.forEach(function (c) {
			if (c.classList.contains('is-active')) activeSlugs.add(c.dataset.slug);
		});

		var allActive = true;
		var allStorefront = true;
		var allContent = true;
		pageChips.forEach(function (c) {
			var isActive = c.classList.contains('is-active');
			if (!isActive) allActive = false;
			if (c.dataset.group === 'storefront' && !isActive) allStorefront = false;
			if (c.dataset.group === 'content' && !isActive) allContent = false;
		});

		// Also check that at least one chip of each group exists for the preset to be fully active
		var hasStorefront = chipBar.querySelector('.wchs-chip[data-group="storefront"]');
		var hasContent = chipBar.querySelector('.wchs-chip[data-group="content"]');

		chipBar.querySelectorAll('.wchs-chip--preset').forEach(function (btn) {
			var preset = btn.dataset.preset;
			var active = false;
			if (preset === 'all') active = allActive;
			else if (preset === 'storefront') active = hasStorefront && allStorefront;
			else if (preset === 'content') active = hasContent && allContent;
			btn.classList.toggle('is-active', active);
		});
	}

	function rebuildArtboards() {
		if (!chipBar) return;
		var activeChips = chipBar.querySelectorAll('.wchs-chip.is-active:not(.wchs-chip--preset)');
		var activeSlugs = new Set();
		activeChips.forEach(function (c) { activeSlugs.add(c.dataset.slug); });

		// Remove artboards that are no longer active
		artboardEls = artboardEls.filter(function (art) {
			if (!activeSlugs.has(art.slug)) {
				readyIframes.delete(art.iframe);
				art.wrapper.style.opacity = '0';
				art.wrapper.style.transform = 'scale(0.95)';
				setTimeout(function () { art.wrapper.remove(); }, 200);
				return false;
			}
			return true;
		});

		// Add artboards for newly active pages
		activeChips.forEach(function (chip) {
			var slug = chip.dataset.slug;
			var path = chip.dataset.path;
			var group = chip.dataset.group;
			if (artboardEls.some(function (a) { return a.slug === slug; })) return;
			var art = createArtboard(slug, chip.textContent.trim(), path, false, group);
			artboardEls.push(art);
		});

		updateChipCount();
		updatePresetStates();
		if (typeof layoutArtboards === 'function') layoutArtboards();
	}

	if (chipBar) {
		// Individual page chip clicks
		chipBar.querySelectorAll('.wchs-chip:not(.wchs-chip--preset)').forEach(function (chip) {
			chip.addEventListener('click', function () {
				chip.classList.toggle('is-active');
				rebuildArtboards();
			});
		});

		// Preset chip clicks
		chipBar.querySelectorAll('.wchs-chip--preset').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var preset = btn.dataset.preset;
				var pageChips = chipBar.querySelectorAll('.wchs-chip:not(.wchs-chip--preset)');

				if (preset === 'all') {
					// Toggle: if all are active, deactivate all; otherwise activate all
					var allActive = true;
					pageChips.forEach(function (c) { if (!c.classList.contains('is-active')) allActive = false; });
					pageChips.forEach(function (c) { c.classList.toggle('is-active', !allActive); });
				} else if (preset === 'storefront') {
					pageChips.forEach(function (c) {
						if (c.dataset.group === 'storefront') c.classList.toggle('is-active', true);
						else c.classList.remove('is-active');
					});
				} else if (preset === 'content') {
					pageChips.forEach(function (c) {
						if (c.dataset.group === 'content') c.classList.toggle('is-active', true);
						else c.classList.remove('is-active');
					});
				}

				rebuildArtboards();
			});
		});

		// Initial count
		updateChipCount();
		updatePresetStates();
	}

	// ─── Resizable divider ───
	var isDragging = false;
	divider.addEventListener('mousedown', function (e) {
		e.preventDefault();
		isDragging = true;
		divider.classList.add('is-dragging');
		surface.style.pointerEvents = 'none';
		document.addEventListener('mousemove', onDividerMove);
		document.addEventListener('mouseup', onDividerUp);
	});

	function onDividerMove(e) {
		if (!isDragging) return;
		var adminRect = admin.getBoundingClientRect();
		var newWidth = e.clientX - adminRect.left;
		newWidth = Math.max(280, Math.min(600, newWidth));
		panel.style.width = newWidth + 'px';
	}

	function onDividerUp() {
		isDragging = false;
		divider.classList.remove('is-dragging');
		surface.style.pointerEvents = '';
		document.removeEventListener('mousemove', onDividerMove);
		document.removeEventListener('mouseup', onDividerUp);
		localStorage.setItem('wchs_panel_width', panel.offsetWidth);
	}

	// ─── Device switching ───
	var deviceBtns = admin.querySelectorAll('.wchs-device-btn');
	deviceBtns.forEach(function (btn) {
		btn.addEventListener('click', function () {
			var device = btn.dataset.device;
			if (!device || !DEVICES[device]) return;
			currentDevice = device;
			deviceBtns.forEach(function (b) { b.classList.remove('is-active'); });
			btn.classList.add('is-active');
			applyDevice();
		});
	});

	// ─── Theme preview toggle ───
	// Flip each artboard iframe between light/dark via postMessage (instant,
	// no reload) AND URL param (so subsequent navigations keep the theme).
	// SPA's pre-paint script reads ?theme=light|dark; its layout listens
	// for {__wchs_preview_theme} to flip without reload.
	var currentPreviewTheme = 'light';
	var themeBtns = admin.querySelectorAll('.wchs-theme-btn');
	themeBtns.forEach(function (btn) {
		btn.addEventListener('click', function () {
			var theme = btn.dataset.theme;
			if (theme !== 'light' && theme !== 'dark') return;
			currentPreviewTheme = theme;
			themeBtns.forEach(function (b) { b.classList.toggle('is-active', b === btn); });
			artboardEls.forEach(function (art) {
				// Instant flip via postMessage — no reload.
				try {
					art.iframe.contentWindow.postMessage(
						{ __wchs_preview_theme: theme }, spaOrigin
					);
				} catch (e) {}
				// Update URL so a later reload (device swap, section swap)
				// persists the theme.
				try {
					var url = new URL(art.iframe.src);
					url.searchParams.set('theme', theme);
					// Only update src if theme param actually changed to avoid
					// a needless reload.
					var asStr = url.toString();
					if (art.iframe.src !== asStr) {
						art.iframe.dataset.themeParam = theme;
					}
				} catch (e) {}
			});
		});
	});
	// Expose so createArtboard() adds ?theme=<currentPreviewTheme> on first load
	window.__wchsPreviewTheme = function () { return currentPreviewTheme; };

	function applyDevice() {
		// Rescale immediately using the current iframe height, then let
		// the SPA's ResizeObserver post a corrected height if the inner
		// layout shifts at the new viewport width (responsive breakpoints).
		var d = DEVICES[currentDevice];
		artboardEls.forEach(function (art) {
			var curH = parseInt(art.iframe.style.height, 10) || 800;
			art.iframe.style.width = d.w + 'px';
			art.iframe.style.transform = 'scale(' + d.scale + ')';
			art.frame.style.width = Math.round(d.w * d.scale) + 'px';
			art.frame.style.height = Math.round(curH * d.scale) + 'px';
		});
		if (typeof layoutArtboards === 'function') layoutArtboards();
	}

	// ─── Artboard height driver: ResizeObserver → postMessage ───
	// SPA posts { __wchs_preview_size: true, height } on every layout
	// change. We route to the matching artboard and debounce rapid fires.
	var pendingResizes = new Map();
	function handlePreviewSize(iframe, height) {
		var art = null;
		for (var i = 0; i < artboardEls.length; i++) {
			if (artboardEls[i].iframe === iframe) { art = artboardEls[i]; break; }
		}
		if (!art) return;
		clearTimeout(pendingResizes.get(iframe));
		pendingResizes.set(iframe, setTimeout(function () {
			art.sizedFromObserver = true;
			updateArtboardSize(art, Math.max(height, 200));
			pendingResizes.delete(iframe);
		}, 120));
	}
	window.addEventListener('message', function (e) {
		if (!e.data || !e.data.__wchs_preview_size) return;
		// Origin hardening — only accept from known SPA origin
		if (e.origin !== spaOrigin) return;
		for (var i = 0; i < artboardEls.length; i++) {
			if (artboardEls[i].iframe.contentWindow === e.source) {
				handlePreviewSize(artboardEls[i].iframe, e.data.height);
				return;
			}
		}
	});

	// ─── Camera model: {x, y, z} in screen space ───
	// Transform: translate(x, y) scale(z) — x,y is the screen-space offset
	// of the surface origin; z is zoom. World point W maps to screen
	// point W*z + (x,y). Zoom-to-cursor preserves the world point under
	// the cursor by adjusting (x,y) when z changes.
	var camera = { x: 0, y: 0, z: 1 };
	var MIN_Z = 0.1;
	var MAX_Z = 3;
	var userHasPanned = false;

	function applyCamera() {
		surface.style.transform =
			'translate(' + camera.x + 'px, ' + camera.y + 'px) scale(' + camera.z + ')';
		currentZoom = camera.z;
		if (zoomLabel) zoomLabel.textContent = Math.round(camera.z * 100) + '%';
	}

	function zoomAt(screenX, screenY, newZ) {
		newZ = Math.max(MIN_Z, Math.min(MAX_Z, newZ));
		newZ = Math.round(newZ * 100) / 100;
		var rect = canvas.getBoundingClientRect();
		var cx = screenX - rect.left;
		var cy = screenY - rect.top;
		var ratio = newZ / camera.z;
		camera.x = cx - (cx - camera.x) * ratio;
		camera.y = cy - (cy - camera.y) * ratio;
		camera.z = newZ;
		applyCamera();
	}

	function centerCamera() {
		var rect = canvas.getBoundingClientRect();
		var sw = surface.offsetWidth;
		var sh = surface.offsetHeight;
		camera.z = 1;
		camera.x = Math.max(0, Math.round((rect.width - sw) / 2));
		camera.y = 20;
		applyCamera();
	}

	// ─── layoutArtboards: set surface width for grid wrap ───
	function layoutArtboards() {
		var count = artboardEls.length;
		var d = DEVICES[currentDevice];
		var artW = Math.round(d.w * d.scale);
		var gap = 40;
		var padding = 60;
		if (count >= 4) {
			// Force 2-column wrap
			surface.style.width = (artW * 2 + gap + padding * 2) + 'px';
		} else {
			// Let flex size to content (single row)
			surface.style.width = '';
		}
		if (!userHasPanned) centerCamera();
	}

	// ─── Zoom buttons (center-based) ───
	var zoomBtns = admin.querySelectorAll('.wchs-zoom-btn');
	zoomBtns.forEach(function (btn) {
		btn.addEventListener('click', function () {
			var dir = btn.dataset.zoom;
			var step = 0.1;
			var newZ = camera.z + (dir === 'in' ? step : -step);
			var rect = canvas.getBoundingClientRect();
			zoomAt(rect.left + rect.width / 2, rect.top + rect.height / 2, newZ);
		});
	});

	// ─── Ctrl+wheel: zoom to cursor. Plain wheel: pan ───
	canvas.addEventListener('wheel', function (e) {
		e.preventDefault();
		if (e.ctrlKey || e.metaKey) {
			// Zoom toward cursor. Normalize wheel delta so trackpads and
			// mice feel similar — exponential scaling is smoother than linear.
			var dz = Math.exp(-e.deltaY * 0.005);
			zoomAt(e.clientX, e.clientY, camera.z * dz);
		} else {
			userHasPanned = true;
			camera.x -= e.deltaX;
			camera.y -= e.deltaY;
			applyCamera();
		}
	}, { passive: false });

	// ─── Drag pan: space+left, middle-click, click on empty canvas ───
	var spaceHeld = false;
	var panDrag = null; // {startX, startY, startCamX, startCamY}

	function isTypingTarget(el) {
		if (!el) return false;
		var tag = el.tagName;
		return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || el.isContentEditable;
	}

	document.addEventListener('keydown', function (e) {
		if (e.code !== 'Space') return;
		if (isTypingTarget(document.activeElement)) return;
		if (spaceHeld) return;
		spaceHeld = true;
		canvas.classList.add('is-space-held');
		e.preventDefault();
	});

	document.addEventListener('keyup', function (e) {
		if (e.code !== 'Space') return;
		spaceHeld = false;
		canvas.classList.remove('is-space-held');
	});

	// Clear space flag on blur (e.g., alt-tab with space still down)
	window.addEventListener('blur', function () {
		spaceHeld = false;
		canvas.classList.remove('is-space-held');
	});

	canvas.addEventListener('mousedown', function (e) {
		var middle = e.button === 1;
		var left = e.button === 0;
		var emptyBg = e.target === canvas || e.target === surface;
		var shouldPan = middle || (left && (spaceHeld || emptyBg));
		if (!shouldPan) return;
		e.preventDefault();
		panDrag = {
			startX: e.clientX,
			startY: e.clientY,
			startCamX: camera.x,
			startCamY: camera.y,
		};
		canvas.classList.add('is-panning');
	});

	document.addEventListener('mousemove', function (e) {
		if (!panDrag) return;
		camera.x = panDrag.startCamX + (e.clientX - panDrag.startX);
		camera.y = panDrag.startCamY + (e.clientY - panDrag.startY);
		userHasPanned = true;
		applyCamera();
	});

	document.addEventListener('mouseup', function () {
		if (!panDrag) return;
		panDrag = null;
		canvas.classList.remove('is-panning');
	});

	// Block middle-click autoscroll on the canvas
	canvas.addEventListener('auxclick', function (e) {
		if (e.button === 1) e.preventDefault();
	});

	// Initial camera center once surface has size
	requestAnimationFrame(function () {
		layoutArtboards();
	});

	// ─── Live preview: postMessage bridge ───
	// Push config overrides to ALL artboard iframes.
	var syncTimer = null;

	function pushPreview() {
		artboardEls.forEach(function (art) {
			if (readyIframes.has(art.iframe)) {
				pushPreviewToIframe(art.iframe);
			}
		});
	}

	function pushPreviewToIframe(ifr) {
		if (!ifr.contentWindow) return;
		var msg = { __wchs_preview: true };
		var tabMatch = window.location.search.match(/tab=([^&]+)/);
		var tab = tabMatch ? tabMatch[1] : 'homepage';

		// try/catch per-tab so one bad field doesn't kill the whole pipeline
		try {
			if (tab === 'homepage') {
				msg.homepage = assembleHomepageConfig();
			} else if (tab === 'pages') {
				msg.pages = [];
				var pageCards = document.querySelectorAll('.wchs-page-card');
				pageCards.forEach(function (card) {
					var slugInput = card.querySelector('[name*="slug"]');
					if (slugInput && slugInput.value) {
						msg.pages.push(assemblePageConfig(card));
					}
				});
			} else if (tab === 'design') {
				msg.appearance = assembleAppearanceConfig();
			} else if (tab === 'shop') {
				msg.shop = assembleShopConfig();
			} else if (tab === 'pdp') {
				msg.pdp = assemblePdpConfig();
			}
		} catch (err) {
			if (window.console) console.warn('wchs preview assemble failed', err);
			return;
		}

		ifr.contentWindow.postMessage(msg, spaOrigin);
	}

	function assembleHomepageConfig() {
		var hero = {};

		// Text fields
		var textFields = {
			hero_headline: 'headline',
			hero_subheadline: 'subheadline',
			hero_cta_text: 'cta_text',
			hero_cta_link: 'cta_link',
			hero_rating_text: 'rating_text',
			hero_research_badge: 'research_badge',
			hero_cta_secondary_text: 'cta_secondary_text',
			hero_cta_secondary_link: 'cta_secondary_link',
		};
		Object.keys(textFields).forEach(function (name) {
			var el = document.querySelector('[name="' + name + '"]');
			if (el) hero[textFields[name]] = el.value;
		});

		// Toggle/checkbox fields (boolean)
		var toggleFields = {
			hero_show_eyebrow: 'show_eyebrow',
			hero_show_rating: 'show_rating',
			hero_cta_accent: 'cta_accent',
			hero_show_cta: 'show_cta',
		};
		Object.keys(toggleFields).forEach(function (name) {
			var el = document.querySelector('[name="' + name + '"]');
			if (el) hero[toggleFields[name]] = el.checked;
		});

		// Radio fields (string value of checked radio)
		var radioFields = ['hero_layout', 'hero_text_color_mode', 'hero_variant'];
		radioFields.forEach(function (name) {
			var el = document.querySelector('[name="' + name + '"]:checked');
			if (el) hero[name.replace('hero_', '')] = el.value;
		});

		// Select fields
		var selectFields = {
			hero_headline_size: 'headline_size',
			hero_headline_weight: 'headline_weight',
			hero_headline_font: 'headline_font',
			hero_subheadline_size: 'subheadline_size',
		};
		Object.keys(selectFields).forEach(function (name) {
			var el = document.querySelector('[name="' + name + '"]');
			if (el) hero[selectFields[name]] = el.value;
		});

		// Hero images (hidden inputs from media picker)
		var imgFields = { hero_image_desktop: 'image_desktop', hero_image_mobile: 'image_mobile' };
		Object.keys(imgFields).forEach(function (name) {
			var el = document.querySelector('[name="' + name + '"]');
			if (el) hero[imgFields[name]] = el.value;
		});

		// Numeric positioning + zoom (range inputs)
		var numFields = [
			'hero_image_position_x',
			'hero_image_position_y',
			'hero_image_position_mobile_x',
			'hero_image_position_mobile_y',
			'hero_image_zoom',
			'hero_image_zoom_mobile',
		];
		numFields.forEach(function (name) {
			var el = document.querySelector('[name="' + name + '"]');
			if (el) hero[name.replace('hero_', '')] = parseInt(el.value, 10);
		});

		// Trust items repeater — name="hero_trust_items[N][icon]" / [text]
		var trustItems = [];
		document.querySelectorAll('[name^="hero_trust_items"][name$="[icon]"]').forEach(function (iconEl) {
			var idxMatch = iconEl.name.match(/\[(\d+)\]/);
			if (!idxMatch) return;
			var idx = idxMatch[1];
			var textEl = document.querySelector('[name="hero_trust_items[' + idx + '][text]"]');
			trustItems.push({ icon: iconEl.value, text: textEl ? textEl.value : '' });
		});
		hero.trust_items = trustItems;

		var statsTa = document.querySelector('[name="hero_research_stats_json"]');
		if (statsTa) {
			try {
				var parsedStats = JSON.parse(statsTa.value || '[]');
				hero.research_stats = Array.isArray(parsedStats) ? parsedStats : [];
			} catch (e2) {
				hero.research_stats = [];
			}
		}

		// Read modules from the homepage modlist only (never shop/pdp hidden fields).
		var modules = [];
		var modlist = document.querySelector('.wchs-modlist[data-context="homepage"]');
		var modulesInput = modlist ? modlist.querySelector('[name="modules_json"]') : null;
		if (modulesInput) {
			try { modules = JSON.parse(modulesInput.value); } catch(e) {}
		}

		return { hero: hero, modules: modules };
	}

	function assembleShopConfig() {
		var shop = {};
		var cm = document.querySelector('[name="shop_cols_min"]');
		var cx = document.querySelector('[name="shop_cols_max"]');
		if (cm) shop.cols_min = parseInt(cm.value, 10);
		if (cx) shop.cols_max = parseInt(cx.value, 10);
		var sh = document.querySelector('[name="shop_spacing_h"]');
		if (sh) shop.spacing_h = sh.value;
		var modules = [];
		var shopList = document.querySelector('.wchs-modlist[data-context="shop"]');
		var modulesInput = shopList ? shopList.querySelector('[name="modules_json"]') : null;
		if (modulesInput) {
			try { modules = JSON.parse(modulesInput.value); } catch(e) {}
		}
		shop.modules = modules;
		return shop;
	}

	function assemblePdpConfig() {
		var pdp = {};
		var reviewsEl = document.querySelector('[name="pdp_show_reviews"]');
		if (reviewsEl) pdp.show_reviews = reviewsEl.checked;
		var xsellEl = document.querySelector('[name="cross_sell_mode"]:checked')
			|| document.querySelector('[name="cross_sell_mode"]');
		if (xsellEl) pdp.cross_sell_mode = xsellEl.value;
		var excludeEl = document.querySelector('[name="slide_cart_cross_sell_exclude_ids"]');
		if (excludeEl) {
			pdp.slide_cart = pdp.slide_cart || {};
			pdp.slide_cart.cross_sell_exclude_product_ids = (excludeEl.value || '')
				.split(',')
				.map(Number)
				.filter(Boolean);
		}
		var modules = [];
		var pdpList = document.querySelector('.wchs-modlist[data-context="pdp"]');
		var modulesInput = pdpList ? pdpList.querySelector('[name="modules_json"]') : null;
		if (modulesInput) {
			try { modules = JSON.parse(modulesInput.value); } catch(e) {}
		}
		pdp.modules = modules;
		return pdp;
	}

	function assemblePageConfig(cardEl) {
		var slugEl = cardEl.querySelector('[name*="slug"]');
		var titleEl = cardEl.querySelector('[name*="title"]');
		var modulesEl = cardEl.querySelector('[name*="modules_json"]');
		var modules = [];
		if (modulesEl) {
			try { modules = JSON.parse(modulesEl.value); } catch(e) {}
		}
		return {
			slug: slugEl ? slugEl.value : '',
			title: titleEl ? titleEl.value : '',
			modules: modules,
		};
	}

	function assembleAppearanceConfig() {
		var out = {};

		// Typography
		var typo = {};
		var typoFields = {
			typography_heading_font: 'heading_font',
			typography_body_font: 'body_font',
			typography_heading_weight: 'heading_weight',
			typography_body_size: 'body_size',
		};
		Object.keys(typoFields).forEach(function (name) {
			var el = document.querySelector('[name="' + name + '"]');
			if (el) typo[typoFields[name]] = el.value;
		});
		out.typography = typo;

		// Accent color
		var accentEl = document.querySelector('[name="accent_color"]');
		if (accentEl) out.accent_color = accentEl.value || null;

		// Scalar fields (selects, hidden inputs)
		var scalarSelectors = {
			logo_size: 'logo_size',
			brand_position: 'brand_position',
			mobile_hamburger_side: 'mobile_hamburger_side',
		};
		Object.keys(scalarSelectors).forEach(function (name) {
			var el = document.querySelector('[name="' + name + '"]');
			if (el) out[scalarSelectors[name]] = el.value;
		});

		// Radios
		var themeEl = document.querySelector('[name="theme_default"]:checked');
		if (themeEl) out.theme_default = themeEl.value;

		// Logo dark attachment — hidden input holds the attachment ID, plus derived URL
		var logoDarkIdEl = document.querySelector('[name="logo_dark_id"]');
		if (logoDarkIdEl) {
			out.logo_dark_id = parseInt(logoDarkIdEl.value, 10) || 0;
		}
		var logoDarkPreview = document.querySelector('.wchs-logo-dark-preview img');
		if (logoDarkPreview && logoDarkPreview.src) out.logo_dark_url = logoDarkPreview.src;

		// Logo invert bool
		var invertEl = document.querySelector('[name="logo_invert_on_dark"]');
		if (invertEl) out.logo_invert_on_dark = invertEl.checked;

		// Header toggles (grouped under out.header so SPA can patch atomically)
		var header = {};
		var headerBools = [
			'header_show_toggle',
			'header_toggle_accent',
			'header_cart_accent',
			'header_inverted',
			'header_borderless',
			'header_toggle_mobile_pin',
			'header_cart_mobile_pin',
		];
		headerBools.forEach(function (name) {
			var el = document.querySelector('[name="' + name + '"]');
			if (el) header[name.replace('header_', '')] = el.checked;
		});

		// Header links repeater — name="header_links[N][label|url|display|icon|accent|mobile_pin]"
		var links = [];
		var labelInputs = document.querySelectorAll('[name^="header_links"][name$="[label]"]');
		labelInputs.forEach(function (lbl) {
			var idxMatch = lbl.name.match(/\[(\d+)\]/);
			if (!idxMatch) return;
			var idx = idxMatch[1];
			var get = function (field, prop) {
				var el = document.querySelector('[name="header_links[' + idx + '][' + field + ']"]');
				if (!el) return null;
				return prop === 'checked' ? el.checked : el.value;
			};
			links.push({
				label: lbl.value,
				url: get('url') || '',
				display: get('display') || 'text',
				icon: get('icon') || '',
				accent: !!get('accent', 'checked'),
				mobile_pin: !!get('mobile_pin', 'checked'),
			});
		});
		out.header_links = links;
		out.header = header;

		// Footer: columns + tagline
		var footer = { columns: [], tagline: '' };
		var taglineEl = document.querySelector('[name="footer_tagline"]');
		if (taglineEl) footer.tagline = taglineEl.value;
		var colTitles = document.querySelectorAll('[name^="footer_columns"][name$="[title]"]');
		colTitles.forEach(function (tEl) {
			var m = tEl.name.match(/\[(\d+)\]/);
			if (!m) return;
			var cIdx = m[1];
			var linkRows = document.querySelectorAll('[name^="footer_columns[' + cIdx + '][links]"][name$="[label]"]');
			var colLinks = [];
			linkRows.forEach(function (lr) {
				var lm = lr.name.match(/\[links\]\[(\d+)\]/);
				if (!lm) return;
				var lIdx = lm[1];
				var urlEl = document.querySelector('[name="footer_columns[' + cIdx + '][links][' + lIdx + '][url]"]');
				colLinks.push({ label: lr.value, url: urlEl ? urlEl.value : '' });
			});
			footer.columns.push({ title: tEl.value, links: colLinks });
		});
		out.footer = footer;

		// Social links repeater — name="social_links[N][platform|url]"
		var social = [];
		document.querySelectorAll('[name^="social_links"][name$="[platform]"]').forEach(function (pEl) {
			var m = pEl.name.match(/\[(\d+)\]/);
			if (!m) return;
			var idx = m[1];
			var urlEl = document.querySelector('[name="social_links[' + idx + '][url]"]');
			social.push({ platform: pEl.value, url: urlEl ? urlEl.value : '' });
		});
		out.social_links = social;

		// Product card — 14 aesthetic + content options. Keys match the
		// PHP save handler exactly so applyAppearance on the SPA can patch
		// config.data.product_card with a plain spread.
		var pc = {};
		var pcEnumFields = [
			'media_aspect_ratio', 'corner_radius', 'border', 'hover_effect',
			'button_style', 'badge_position', 'badge_style',
			'oos_treatment', 'title_lines',
		];
		pcEnumFields.forEach(function (name) {
			var el = document.querySelector('[name="product_card[' + name + ']"]');
			if (el) pc[name] = el.value;
		});
		var pcBoolFields = ['show_bulk_badge', 'show_tier_hint', 'show_oos_cards', 'secondary_image_on_hover'];
		pcBoolFields.forEach(function (name) {
			var el = document.querySelector('[name="product_card[' + name + ']"]');
			if (el) pc[name] = !!el.checked;
		});
		var saleTextEl = document.querySelector('[name="product_card[sale_badge_text]"]');
		if (saleTextEl) pc.sale_badge_text = saleTextEl.value;
		if (Object.keys(pc).length) out.product_card = pc;

		return out;
	}

	// Debounced sync on any form input change
	function scheduleSync() {
		if (syncTimer) clearTimeout(syncTimer);
		syncTimer = setTimeout(pushPreview, 300);
	}

	// Listen for changes on all inputs/selects/textareas in the settings panel
	var panelEl = admin.querySelector('.wchs-editor__panel');
	if (panelEl) {
		panelEl.addEventListener('input', scheduleSync);
		panelEl.addEventListener('change', scheduleSync);
	}

	// ─── Scroll-jolt guard ───
	// Two jolt sources fight the user's scroll position:
	//   1. Focus-scroll — browser scrolls the nearest scrollable ancestor to
	//      bring the just-focused input into view. Happens within ~50ms of
	//      click.
	//   2. Resize-scroll — after the click triggers scheduleSync (300ms) →
	//      iframe repaints → ResizeObserver → updateArtboardSize, the iframe
	//      height change cascades into a browser reflow that can push body
	//      scroll. Happens 500-900ms after click.
	// Reverting ONLY panel-body forces the browser to escalate to body scroll
	// as a fallback (see WP menu flying off-screen). We guard both scroll
	// axes (panel-body AND window) and extend the revert window when a
	// resize happens so the post-pipeline scroll is also caught.
	var panelBody = admin.querySelector('.wchs-editor__panel-body');
	var jolt = {
		savedTop: 0, savedLeft: 0,
		savedWinY: 0, savedWinX: 0,
		revertUntil: 0,
	};
	function extendJoltRevert(ms) {
		jolt.revertUntil = Math.max(jolt.revertUntil, Date.now() + ms);
	}
	// Primary scroll-jolt fix: intercept focus and re-focus with
	// preventScroll. The browser's implicit scroll-into-view on focus is
	// what jolts the page when radios/selects are clicked — preventScroll
	// stops it at the source, which is more reliable than reactive revert.
	// The Rev-2 reactive revert below stays as a belt-and-suspenders
	// safety net for any browser that doesn't honor preventScroll.
	var DEBUG_JOLT = window.location.search.indexOf('wchs_scroll_debug=1') !== -1;
	// Intercept mousedown BEFORE native focus transfer. Calling preventDefault
	// on mousedown blocks the native focus (and its built-in scroll-into-view)
	// from running at all. Then we explicitly focus with {preventScroll: true}
	// so the input becomes focused without the browser scrolling anywhere.
	// Click event still fires afterward, so the radio's checked state still
	// toggles natively. This is the only way to prevent the jolt at the
	// source — a post-focus revert can't undo a scroll that happens before
	// any JS handler runs.
	if (panelBody) {
		panelBody.addEventListener('mousedown', function (e) {
			var t = e.target;
			if (!t || !t.tagName) return;
			// Walk up to find the actual form control behind the click
			// (label/span clicks cascade focus to their wrapped/for input).
			var focusTarget = null;
			if (t.tagName === 'INPUT' || t.tagName === 'SELECT' || t.tagName === 'TEXTAREA') {
				focusTarget = t;
			} else {
				var label = t.closest && t.closest('label');
				if (label) {
					var forId = label.getAttribute('for');
					if (forId) focusTarget = document.getElementById(forId);
					if (!focusTarget) focusTarget = label.querySelector('input, select, textarea');
				}
			}
			if (!focusTarget || typeof focusTarget.focus !== 'function') return;
			// ONLY preventDefault for radios/checkboxes — selects need their
			// native dropdown to open on mousedown, and preventDefault blocks
			// that entirely. Scroll-into-view on select focus is handled by
			// the reactive revert below (pointerdown → scroll listener).
			var isRadioCheck = focusTarget.tagName === 'INPUT' &&
			                   (focusTarget.type === 'radio' || focusTarget.type === 'checkbox');
			if (!isRadioCheck) return;
			if (DEBUG_JOLT) console.log('[wchs-jolt] mousedown-intercept ' + focusTarget.tagName + ' name=' + focusTarget.name + ' winY=' + window.scrollY);
			e.preventDefault(); // stop native focus + native scroll-into-view
			try { focusTarget.focus({ preventScroll: true }); } catch (err) {}
		}, true);
	}
	if (panelBody) {
		function onPanelScroll() {
			if (Date.now() > jolt.revertUntil) return;
			if (panelBody.scrollTop !== jolt.savedTop) panelBody.scrollTop = jolt.savedTop;
			if (panelBody.scrollLeft !== jolt.savedLeft) panelBody.scrollLeft = jolt.savedLeft;
		}
		function onWindowScroll() {
			if (Date.now() > jolt.revertUntil) return;
			if (window.scrollY !== jolt.savedWinY || window.scrollX !== jolt.savedWinX) {
				window.scrollTo({ top: jolt.savedWinY, left: jolt.savedWinX, behavior: 'instant' });
			}
		}
		function onDownCapture() {
			jolt.savedTop = panelBody.scrollTop;
			jolt.savedLeft = panelBody.scrollLeft;
			jolt.savedWinY = window.scrollY;
			jolt.savedWinX = window.scrollX;
			extendJoltRevert(250);
		}
		panelBody.addEventListener('mousedown', onDownCapture, true);
		panelBody.addEventListener('pointerdown', onDownCapture, true);
		panelBody.addEventListener('keydown', function (e) {
			if (e.key === ' ' || e.key.startsWith('Arrow')) onDownCapture();
		}, true);
		panelBody.addEventListener('scroll', onPanelScroll);
		window.addEventListener('scroll', onWindowScroll);
	}
	// Expose so updateArtboardSize (later in file) can extend the window.
	window.__wchsExtendJoltRevert = extendJoltRevert;

	// Expose pushPreview globally so module save callbacks can trigger it
	window.wchsPushPreview = pushPreview;
})();

/* ═══════════════════════════════════════════════════════════
   Dirty-state tracker — drives three UX affordances so users don't
   forget to save after modal "Apply":
     1. beforeunload guard (browser-native warning on tab close/nav)
     2. .is-dirty class on each form → CSS shows sticky banner + marks
        the tab Save button with an asterisk-style indicator
     3. Success toast after a form submit completes
   Snapshot on load; compare on every input/change; reset on submit.
   ═══════════════════════════════════════════════════════════ */
(function () {
	'use strict';
	var forms = document.querySelectorAll('.wchs-admin form');
	if (!forms.length) return;

	var snapshots = new Map();

	function serialize(form) {
		try {
			return new URLSearchParams(new FormData(form)).toString();
		} catch (e) {
			return null;
		}
	}

	function checkDirty(form) {
		var snap = snapshots.get(form);
		var cur = serialize(form);
		var dirty = cur !== null && snap !== null && cur !== snap;
		form.classList.toggle('is-dirty', dirty);
		syncGlobalSaveState();
		return dirty;
	}

	// Global floppy save button in the canvas toolbar. Reads the dirty state
	// of any WCHS form on the page and reflects it as a visual state. Click
	// submits the currently-visible form; keyboard Cmd+S still works too.
	var globalSaveBtn = document.getElementById('wchs-global-save');
	function syncGlobalSaveState() {
		if (!globalSaveBtn) return;
		// Don't overwrite transient "saving" or "saved" states.
		var s = globalSaveBtn.dataset.state;
		if (s === 'saving' || s === 'saved') return;
		var anyDirty = Array.prototype.some.call(document.querySelectorAll('form.is-dirty'), function () { return true; });
		globalSaveBtn.dataset.state = anyDirty ? 'dirty' : 'idle';
	}
	if (globalSaveBtn) {
		globalSaveBtn.addEventListener('click', function () {
			// Submit the first dirty form (tab-scoped page → one form per tab).
			var dirty = document.querySelector('form.is-dirty');
			if (!dirty) {
				// Flash "saved" briefly so the click isn't silent even when idle
				globalSaveBtn.dataset.state = 'saved';
				setTimeout(function () { syncGlobalSaveState(); }, 800);
				if (typeof toast === 'function') toast('Nothing to save');
				return;
			}
			globalSaveBtn.dataset.state = 'saving';
			try { dirty.requestSubmit(); }
			catch (e) { dirty.submit(); }
		});
	}

	forms.forEach(function (form) {
		snapshots.set(form, serialize(form));
		// Re-check on every user input — debounced so typing doesn't churn
		var t = null;
		var handler = function () {
			clearTimeout(t);
			t = setTimeout(function () { checkDirty(form); }, 120);
		};
		form.addEventListener('input', handler);
		form.addEventListener('change', handler);
		form.addEventListener('submit', function () {
			// Soft alt-text audit: warn (don't block) if any media URL is set
			// without an alt alongside it. Walks the admin form — media inside
			// module modals has its own save path handled by the module
			// editor's internal readers.
			try {
				var missing = 0;
				form.querySelectorAll('.wchs-media-url').forEach(function (input) {
					if (!input.value) return;
					var field = input.closest('.wchs-field, .wchs-media-field');
					if (!field) return;
					// Look for a sibling alt input. Convention: nearest text input
					// with name matching *alt* OR class wchs-media-alt.
					var scope = field.closest('.wchs-field') || field.parentElement;
					var alt = scope && (scope.querySelector('.wchs-media-alt, input[name*="[alt]"], input[name$="alt"]') || null);
					if (alt && !alt.value) missing++;
				});
				if (missing > 0) {
					if (typeof window !== 'undefined' && typeof toast === 'function') {
						toast(missing + ' image(s) missing alt text', 'error');
					}
				}
			} catch (e) { /* non-blocking */ }

			// Flag the post-redirect landing so we can show a success toast
			// — WP strips ?updated=1 from the URL before admin.js can read
			// it, so we carry the signal via sessionStorage instead.
			try { sessionStorage.setItem('wchs_just_saved', '1'); } catch (e) {}
			snapshots.set(form, serialize(form));
			form.classList.remove('is-dirty');
		});
	});

	window.addEventListener('beforeunload', function (e) {
		for (var entry of snapshots.entries()) {
			var form = entry[0], snap = entry[1];
			if (!form.isConnected) continue;
			var cur = serialize(form);
			if (cur !== null && snap !== null && cur !== snap) {
				e.preventDefault();
				e.returnValue = '';
				return '';
			}
		}
	});

	// Success toast: fire if the previous page submitted a form. The
	// sessionStorage flag is set in the submit handler above and checked
	// + cleared here on the next load.
	var justSaved = false;
	try {
		justSaved = sessionStorage.getItem('wchs_just_saved') === '1';
		if (justSaved) sessionStorage.removeItem('wchs_just_saved');
	} catch (e) {}
	if (justSaved) {
		var toast = document.createElement('div');
		toast.className = 'wchs-save-toast';
		toast.textContent = 'Changes saved';
		document.body.appendChild(toast);
		requestAnimationFrame(function () { toast.classList.add('is-visible'); });
		setTimeout(function () {
			toast.classList.remove('is-visible');
			setTimeout(function () { toast.remove(); }, 300);
		}, 2400);
		// Flash green-check on the global save button too.
		var gsb = document.getElementById('wchs-global-save');
		if (gsb) {
			gsb.dataset.state = 'saved';
			setTimeout(function () { gsb.dataset.state = 'idle'; }, 1600);
		}
	}
})();

/* ═══════════════════════════════════════════════════════════
   Pages-tab focus drill-in — ?tab=pages&focus=<slug>
   scrolls the matching .wchs-page-card into view and pulses it
   ═══════════════════════════════════════════════════════════ */
(function () {
	'use strict';
	var focus = new URLSearchParams(window.location.search).get('focus');
	if (!focus) return;
	if (typeof CSS === 'undefined' || typeof CSS.escape !== 'function') return;
	// Defer so render_page_card has mounted
	document.addEventListener('DOMContentLoaded', function () {
		var target = document.querySelector('.wchs-page-card[data-slug="' + CSS.escape(focus) + '"]');
		if (!target) return;
		target.scrollIntoView({ behavior: 'smooth', block: 'start' });
		target.classList.add('is-focused');
		setTimeout(function () { target.classList.remove('is-focused'); }, 2000);
	});
})();

/* ═══════════════════════════════════════════════════════════
   Undo / Redo / Cmd+S — page-builder essentials. Debounced
   state capture on form input, keyboard-driven restore, Cmd+S
   to submit. Sibling to the dirty-state tracker; same form scope.
   ═══════════════════════════════════════════════════════════ */
(function () {
	'use strict';
	var form = document.querySelector('.wchs-admin form[action*="admin-post"]');
	if (!form) return;

	var MAX = 25;
	var undoStack = [];
	var redoStack = [];
	var isRestoring = false;

	function snapshot() {
		try {
			var modHiddens = form.querySelectorAll('.wchs-modlist input[type="hidden"]');
			return {
				form: new URLSearchParams(new FormData(form)).toString(),
				modules: Array.prototype.map.call(modHiddens, function (i) { return i.value; }),
			};
		} catch (e) { return null; }
	}

	function eq(a, b) {
		if (!a || !b) return false;
		if (a.form !== b.form) return false;
		if (a.modules.length !== b.modules.length) return false;
		for (var i = 0; i < a.modules.length; i++) {
			if (a.modules[i] !== b.modules[i]) return false;
		}
		return true;
	}

	function capture() {
		if (isRestoring) return;
		var state = snapshot();
		if (!state) return;
		var top = undoStack[undoStack.length - 1];
		if (top && eq(top, state)) return;
		undoStack.push(state);
		if (undoStack.length > MAX) undoStack.shift();
		redoStack.length = 0;
	}

	// Initial baseline
	capture();

	var capTimer = null;
	function scheduleCapture() {
		clearTimeout(capTimer);
		capTimer = setTimeout(capture, 400);
	}
	form.addEventListener('input', scheduleCapture);
	form.addEventListener('change', scheduleCapture);

	function restore(state) {
		isRestoring = true;
		try {
			var params = new URLSearchParams(state.form);
			// Apply form element values. FormData omits unchecked checkboxes,
			// so we iterate elements explicitly + check by name.
			var seen = Object.create(null);
			Array.prototype.forEach.call(form.elements, function (el) {
				if (!el.name) return;
				if (el.type === 'checkbox' || el.type === 'radio') {
					var values = params.getAll(el.name);
					el.checked = values.indexOf(el.value) !== -1;
				} else if (el.tagName === 'SELECT' || el.tagName === 'TEXTAREA' ||
						   (el.tagName === 'INPUT' && el.type !== 'file')) {
					// For multi-same-name inputs (arrays) take params in order
					if (!seen[el.name]) seen[el.name] = params.getAll(el.name).slice();
					var q = seen[el.name];
					if (q.length) {
						el.value = q.shift();
					}
				}
			});
			// Module hidden inputs + re-render via _wchsRefresh hook
			var hiddens = form.querySelectorAll('.wchs-modlist input[type="hidden"]');
			Array.prototype.forEach.call(hiddens, function (h, i) {
				if (state.modules[i] !== undefined) h.value = state.modules[i];
				var container = h.closest('.wchs-modlist');
				if (container && typeof container._wchsRefresh === 'function') {
					container._wchsRefresh();
				}
			});
			// Kick scheduleSync + dirty tracker
			form.dispatchEvent(new Event('input', { bubbles: true }));
		} finally {
			setTimeout(function () { isRestoring = false; }, 10);
		}
	}

	function showToast(msg) {
		var t = document.getElementById('wchs-shortcut-toast');
		if (!t) {
			t = document.createElement('div');
			t.id = 'wchs-shortcut-toast';
			t.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);' +
				'background:#1f2937;color:#fff;padding:8px 16px;border-radius:6px;' +
				'font-size:12px;font-weight:500;letter-spacing:0.02em;' +
				'pointer-events:none;z-index:100000;opacity:0;' +
				'transition:opacity 0.15s ease';
			document.body.appendChild(t);
		}
		t.textContent = msg;
		t.style.opacity = '1';
		clearTimeout(t._hideTimer);
		t._hideTimer = setTimeout(function () { t.style.opacity = '0'; }, 1400);
	}

	function undo() {
		if (undoStack.length <= 1) { showToast('Nothing to undo'); return; }
		var current = undoStack.pop();
		redoStack.push(current);
		restore(undoStack[undoStack.length - 1]);
		showToast('Undone');
	}

	function redo() {
		if (redoStack.length === 0) { showToast('Nothing to redo'); return; }
		var state = redoStack.pop();
		undoStack.push(state);
		restore(state);
		showToast('Redone');
	}

	// Focus guard — defer Cmd+Z/Y/S when focus is inside a TinyMCE editor
	// so text-editor's own undo history works naturally.
	function isInsideRichText() {
		var ae = document.activeElement;
		if (!ae) return false;
		if (ae.tagName === 'IFRAME') return true; // TinyMCE iframe body
		if (ae.closest && ae.closest('.mce-container, .mce-edit-area, .mce-tinymce')) return true;
		return false;
	}

	window.addEventListener('keydown', function (e) {
		var isCmd = e.ctrlKey || e.metaKey;
		if (!isCmd) return;
		var key = (e.key || '').toLowerCase();
		if (isInsideRichText() && (key === 'z' || key === 'y')) return;
		if (key === 'z' && !e.shiftKey) {
			e.preventDefault(); e.stopPropagation();
			undo();
		} else if ((key === 'z' && e.shiftKey) || key === 'y') {
			e.preventDefault(); e.stopPropagation();
			redo();
		} else if (key === 's') {
			e.preventDefault(); e.stopPropagation();
			if (form.classList.contains('is-dirty')) {
				showToast('Saving…');
				form.requestSubmit();
			} else {
				showToast('Nothing to save');
			}
		}
	}, true); // capture so we beat inputs' default handlers when needed
})();

(function () {
	'use strict';
	var buttons = document.querySelectorAll('.wchs-copy-btn');
	if (!buttons.length) return;

	var toastEl = null;
	var toastTimer = null;

	function showToast(message, kind) {
		if (!toastEl) {
			toastEl = document.createElement('div');
			toastEl.className = 'wchs-toast';
			document.body.appendChild(toastEl);
		}
		toastEl.textContent = message;
		toastEl.dataset.kind = kind || 'info';
		toastEl.classList.add('is-visible');
		clearTimeout(toastTimer);
		toastTimer = setTimeout(function () {
			toastEl.classList.remove('is-visible');
		}, 1600);
	}

	function fallbackCopy(text) {
		var area = document.createElement('textarea');
		area.value = text;
		area.setAttribute('readonly', 'readonly');
		area.style.position = 'fixed';
		area.style.opacity = '0';
		document.body.appendChild(area);
		area.select();
		var ok = false;
		try {
			ok = document.execCommand('copy');
		} catch (e) {}
		document.body.removeChild(area);
		return ok;
	}

	document.addEventListener('click', function (event) {
		var button = event.target.closest('.wchs-copy-btn');
		if (!button) return;
		event.preventDefault();

		var text = button.getAttribute('data-copy-text') || '';
		if (!text) {
			showToast('Nothing to copy', 'error');
			return;
		}

		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(function () {
				showToast('Copied');
			}).catch(function () {
				var ok = fallbackCopy(text);
				showToast(ok ? 'Copied' : 'Copy failed', ok ? 'info' : 'error');
			});
			return;
		}

		var ok = fallbackCopy(text);
		showToast(ok ? 'Copied' : 'Copy failed', ok ? 'info' : 'error');
	});
})();
