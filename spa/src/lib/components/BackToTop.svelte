<script lang="ts">
  import { onMount } from 'svelte';

  let visible = $state(false);
  let threshold = 600;

  function onScroll() {
    visible = window.scrollY > threshold;
  }
  function toTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
  onMount(() => {
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
    return () => window.removeEventListener('scroll', onScroll);
  });
</script>

{#if visible}
  <button
    class="back-to-top"
    type="button"
    aria-label="Back to top"
    onclick={toTop}
  >
    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M12 19V5" />
      <path d="M5 12l7-7 7 7" />
    </svg>
  </button>
{/if}

<style>
  .back-to-top {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 90;
    width: 44px;
    height: 44px;
    border: 1px solid var(--border);
    border-radius: 50%;
    background: var(--bg);
    color: var(--fg);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    opacity: 0.9;
    transition: opacity var(--dur-fast) var(--ease), transform var(--dur-fast) var(--ease), border-color var(--dur-fast) var(--ease);
  }
  .back-to-top:hover {
    opacity: 1;
    transform: translateY(-2px);
    border-color: var(--fg);
  }
  .back-to-top:focus-visible {
    outline: 2px solid var(--accent, var(--fg));
    outline-offset: 3px;
  }
  @media (max-width: 600px) {
    .back-to-top { bottom: 16px; right: 16px; width: 40px; height: 40px; }
  }
  @media (prefers-reduced-motion: reduce) {
    .back-to-top { transition: none; }
    .back-to-top:hover { transform: none; }
  }
</style>
