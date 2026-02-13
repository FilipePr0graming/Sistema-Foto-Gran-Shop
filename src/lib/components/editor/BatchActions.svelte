<script lang="ts">
  export let count = 0;
  export let onDelete: () => void = () => {};
  export let onDuplicate: () => void = () => {};
  export let onApplyStyle: () => void = () => {};
</script>

{#if count > 0}
  <div class="flex items-center gap-2 rounded-xl border border-[var(--c-border)] bg-slate-50 p-2">
    <span class="pl-1 text-xs font-medium text-[var(--c-text-muted)]">{count} selecionada{count > 1 ? 's' : ''}</span>
    <div class="ml-auto flex items-center gap-1.5">
      <button
        class="rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-[var(--c-text)] border border-[var(--c-border)] transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400"
        on:click={onDuplicate}
        type="button"
        aria-label="Duplicar fotos selecionadas"
      >
        Duplicar
      </button>
      <button
        class="rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-[var(--c-text)] border border-[var(--c-border)] transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400"
        on:click={onApplyStyle}
        type="button"
        aria-label="Aplicar estilo às fotos selecionadas"
      >
        Estilo
      </button>
      <button
        class="rounded-lg bg-[var(--c-danger)] px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-[var(--c-danger-hover)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-400"
        on:click={onDelete}
        type="button"
        aria-label="Deletar fotos selecionadas"
      >
        Deletar
      </button>
    </div>
  </div>
{/if}
