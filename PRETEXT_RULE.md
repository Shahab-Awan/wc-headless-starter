# Pretext Rule - wc-headless-starter

**The constitutional rule for text rendering in this project.**

Established 2026-04-11 after project typography research. This rule is
non-negotiable without explicit discussion.

---

## The rule

**Pretext is used for any text where layout depends on its measured
dimensions.** Skip it for static, fixed-size, or long-form body text.

If you can't answer "what happens if this text is a different length
than I expected?" with "the container sizes to it correctly without
shifting the page" - use Pretext.

---

## What gets Pretext

| Element | Pretext? | Why |
|---|---|---|
| Product card titles | **YES** | Cross-card balancing, truncation, exact height |
| Product prices | **YES** | Multi-currency width harmonization |
| Product descriptions (truncated previews) | **YES** | Smart sentence-level truncation, not mid-word |
| Cart drawer item names | **YES** | Prevents mid-add layout shift |
| FAQ accordion answers | **YES** | Pre-measure for smooth transitions |
| Toast / notice messages | **YES** | Dynamic container width (200-400px) |

## What does NOT get Pretext

| Element | Pretext? | Why |
|---|---|---|
| Button labels ("Add to Cart", "Checkout") | **NO** | Fixed-width buttons, no layout consequence |
| Navigation links | **NO** | Fixed menu items |
| Page headings (h1/h2 on routes) | **NO** | Rarely change, CSS handles fine |
| Full product descriptions (paragraph flow) | **NO** | Overhead doesn't pay off at scale |
| Static body copy | **NO** | CSS flow layout is already correct |
| Form labels | **NO** | Fixed content |

---

## Three hard rules to enforce forever

1. **`await pretext.ready()` before your first `prepare()` call.** This
   gates on `document.fonts.ready`, which is when web fonts have loaded.
   Measuring against fallback fonts gives wrong widths and everything
   shifts after the real font arrives.

2. **Never call `prepare()` or `layout()` directly from components.**
   Always go through `pretext` (the singleton exported from
   `$lib/pretext/engine`). That's where caching lives. Bypassing the
   engine defeats the cache.

3. **Font strings must match exactly what CSS resolves.** If your CSS
   says `font: 500 14px "Outfit", sans-serif`, the Pretext variant's
   font string must be `'500 14px "Outfit", sans-serif'`. Not
   abbreviated, not reordered, not missing the quotes around fonts with
   spaces. A mismatch measures the fallback.

---

## Canonical pattern in a Svelte 5 component

```svelte
<script lang="ts">
	import { pretext } from '$lib/pretext/engine';
	import { onMount } from 'svelte';

	let { product, cardWidth = 252 } = $props();
	let fontsReady = $state(false);

	onMount(async () => {
		await pretext.ready();
		fontsReady = true;
	});

	// Cached behind the engine. Recomputes only when text or width changes.
	const titleLayout = $derived.by(() => {
		if (!fontsReady) return null;
		return pretext.measure(product.name, 'title', cardWidth - 32, 20);
	});
</script>

<div class="card" style={titleLayout ? `--title-h: ${titleLayout.height}px` : ''}>
	<h3 class="card__title">{product.name}</h3>
</div>

<style>
	.card__title {
		height: var(--title-h, auto);
		min-height: 1lh; /* Fallback before pretext measures */
	}
</style>
```

Notes on this pattern:

- `fontsReady` state flips once. We don't block the initial render -
  we just don't set the pretext-computed height until we can.
- `$derived.by` is used (not `$derived`) because the measurement has
  early-return logic.
- The CSS `min-height: 1lh` ensures the card isn't zero-height before
  pretext has computed. Once the style variable fills in, the final
  height locks.
- `max-width - 32` accounts for 16px horizontal padding on each side.

---

## Variants defined in the engine

See `spa/src/lib/pretext/engine.ts`. Add a new variant there (not inline
in a component) whenever you have a new text role. Variants are a small
finite set - don't proliferate them for micro-styling.

---

## Cache invalidation

`pretext.invalidate()` is called automatically:
- On `ready()` after fonts load (measurements before fonts are stale)

Call it manually when:
- A variant's font string changes (`pretext.setVariantFont(...)` does it)
- The theme toggle swaps fonts (if we ever do that)

Don't call it on every render or theme toggle unless fonts actually
differ. The theme toggle in this project changes colors only - fonts
stay the same - so no invalidation is needed for dark/light switching.

---

## If you're about to bend the rule

Don't. If you think a piece of text "probably doesn't need" Pretext but
does affect layout (grid cell sizing, card balancing, truncation math),
that's exactly the case Pretext is for. The overhead is trivial
(~0.04ms per unique string after caching).

If you think Pretext is overkill for a given piece of text, it probably
is - prefer NOT to add it. The rule is conservative for a reason: we
don't want Pretext noise on every `<p>` in the project. But for the
specific patterns in the YES table above, it's mandatory.
