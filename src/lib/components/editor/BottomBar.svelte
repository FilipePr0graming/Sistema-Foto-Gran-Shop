<script lang="ts">
  export let filledCount = 0;
  export let totalSlots = 0;
  export let onBack: () => void = () => {};
  export let onFinalize: () => void = () => {};

  $: remaining = Math.max(0, totalSlots - filledCount);
  $: allFilled = remaining === 0 && totalSlots > 0;
  $: progressPct = totalSlots > 0 ? Math.round((filledCount / totalSlots) * 100) : 0;
</script>

<div class="fixed inset-x-0 bottom-0 z-50 border-t border-[var(--c-border)] bg-white/80 backdrop-blur-lg">
  <div class="mx-auto flex max-w-7xl items-center gap-3 px-4 py-3">
    <!-- Progress info -->
    <div class="flex items-center gap-3 min-w-0">
      <div class="hidden sm:block h-1.5 w-32 overflow-hidden rounded-full bg-slate-100">
        <div
          class="h-full rounded-full transition-all duration-300 {allFilled ? 'bg-emerald-500' : 'bg-[var(--c-accent)]'}"
          style="width: {progressPct}%"
        ></div>
      </div>
      <span class="text-sm text-[var(--c-text-muted)] whitespace-nowrap">
        <span class="font-semibold tabular-nums text-[var(--c-text)]">{filledCount}/{totalSlots}</span>
        preenchidas
      </span>
    </div>

    <!-- Actions -->
    <div class="ml-auto flex items-center gap-2">
      <button
        class="rounded-xl border border-[var(--c-border-strong)] bg-white px-4 py-2 text-sm font-semibold transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400"
        on:click={onBack}
        type="button"
      >
        Voltar
      </button>

      <div class="relative group">
        <button
          class="rounded-xl px-4 py-2 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2
            {allFilled
              ? 'bg-[var(--c-primary)] text-white hover:bg-[var(--c-primary-hover)] focus-visible:ring-slate-400'
              : 'bg-slate-100 text-slate-400 cursor-not-allowed'}"
          disabled={!allFilled}
          on:click={onFinalize}
          type="button"
          aria-describedby={allFilled ? undefined : 'finalize-tooltip'}
        >
          Finalizar Pedido
        </button>

        {#if !allFilled}
          <div
            id="finalize-tooltip"
            role="tooltip"
            class="pointer-events-none absolute bottom-full right-0 mb-2 w-48 rounded-lg bg-slate-900 px-3 py-2 text-xs text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100"
          >
            Preencha todas as {totalSlots} fotos para finalizar
            <div class="absolute -bottom-1 right-4 h-2 w-2 rotate-45 bg-slate-900"></div>
          </div>
        {/if}
      </div>
    </div>
  </div>
</div>
