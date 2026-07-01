<script lang="ts">
	import Boxes from '@lucide/svelte/icons/boxes';
	import BadgePercent from '@lucide/svelte/icons/badge-percent';
	import PackageCheck from '@lucide/svelte/icons/package-check';
	import BadgeCheck from '@lucide/svelte/icons/badge-check';
	import FileCheck from '@lucide/svelte/icons/file-check';
	import Zap from '@lucide/svelte/icons/zap';

	let {
		icon = 'stock',
		size = 28,
		strokeWidth = 1.75,
	}: {
		icon?: string;
		size?: number;
		strokeWidth?: number;
	} = $props();
</script>

{#if icon === 'volume'}
	<BadgePercent {size} {strokeWidth} aria-hidden="true" />
{:else if icon === 'shipping'}
	<PackageCheck {size} {strokeWidth} aria-hidden="true" />
{:else if icon === 'verified'}
	<BadgeCheck {size} {strokeWidth} aria-hidden="true" />
{:else if icon === 'coa'}
	<FileCheck {size} {strokeWidth} aria-hidden="true" />
{:else if icon === 'fulfillment'}
	<Zap {size} {strokeWidth} aria-hidden="true" />
{:else}
	<Boxes {size} {strokeWidth} aria-hidden="true" />
{/if}
