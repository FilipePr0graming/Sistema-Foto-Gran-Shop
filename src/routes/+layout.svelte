<script lang="ts">
	import './layout.css';
	import favicon from '$lib/assets/favicon.svg';

	let { children } = $props();
</script>

<svelte:head>
	<link rel="icon" href={favicon} />
	<meta name="description" content="Editor de Polaroids — Monte suas fotos no navegador" />
</svelte:head>
{@render children()}
