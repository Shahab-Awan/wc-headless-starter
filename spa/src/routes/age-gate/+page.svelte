<script lang="ts">
	import BridgeAgeGate from '$lib/components/BridgeAgeGate.svelte';
	import { config } from '$lib/config.svelte';
</script>

<svelte:head>
	<title>{config.data.homepage.bridge_age_gate?.title || '21+ Research Use Only'} | {config.data.brand_name}</title>
	<meta name="robots" content="noindex, nofollow" />
</svelte:head>

<BridgeAgeGate />
