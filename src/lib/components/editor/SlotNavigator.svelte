<script lang="ts">
  export let currentIndex = 0;
  export let total = 0;
  export let onPrev: () => void = () => {};
  export let onNext: () => void = () => {};

  $: label = `Foto ${currentIndex + 1}`;
  $: hasPrev = currentIndex > 0;
  $: hasNext = currentIndex < total - 1;
</script>

<div class="flex items-center gap-2">
  <span class="text-sm font-semibold text-[var(--c-text)]">{label}</span>

  <div class="ml-auto flex items-center gap-1.5">
    <button
      class="grid h-8 w-8 place-items-center rounded-lg border border-[var(--c-border)] bg-white text-slate-700 transition enabled:hover:bg-slate-50 enabled:hover:border-[var(--c-border-strong)] disabled:opacity-40 disabled:cursor-not-allowed focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400"
      on:click={onPrev}
      disabled={!hasPrev}
      aria-label="Foto anterior"
      type="button"
    >
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
      </svg>
    </button>

    <span class="min-w-[3.5rem] text-center text-xs tabular-nums text-[var(--c-text-muted)]">
      {currentIndex + 1} / {total}
    </span>

    <button
      class="grid h-8 w-8 place-items-center rounded-lg border border-[var(--c-border)] bg-white text-slate-700 transition enabled:hover:bg-slate-50 enabled:hover:border-[var(--c-border-strong)] disabled:opacity-40 disabled:cursor-not-allowed focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400"
      on:click={onNext}
      disabled={!hasNext}
      aria-label="Próxima foto"
      type="button"
    >
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
      </svg>
    </button>
  </div>
</div>
