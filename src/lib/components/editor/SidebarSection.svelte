<script lang="ts">
  import { slide } from 'svelte/transition';

  export let title = '';
  export let badge = '';
  export let defaultOpen = true;

  let open = defaultOpen;

  function toggle() {
    open = !open;
  }
</script>

<div class="rounded-2xl border border-[var(--c-border)] bg-[var(--c-surface)] shadow-[var(--shadow-card)] backdrop-blur">
  <button
    class="flex w-full items-center gap-2 px-4 py-3 text-left transition hover:bg-white/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-slate-400 {open ? 'rounded-t-2xl' : 'rounded-2xl'}"
    on:click={toggle}
    type="button"
    aria-expanded={open}
  >
    <svg
      width="12" height="12" viewBox="0 0 24 24" fill="none"
      class="shrink-0 text-[var(--c-text-muted)] transition-transform duration-200 {open ? 'rotate-90' : ''}"
      aria-hidden="true"
    >
      <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
    <span class="text-sm font-semibold text-[var(--c-text)]">{title}</span>
    {#if badge}
      <span class="ml-auto rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-medium tabular-nums text-[var(--c-text-muted)]">
        {badge}
      </span>
    {/if}
  </button>

  {#if open}
    <div class="px-4 pb-4" transition:slide={{ duration: 180 }}>
      <slot />
    </div>
  {/if}
</div>
