<script lang="ts">
  import type { PhotoAsset } from '$lib/editor/types';
  import BatchActions from './BatchActions.svelte';

  export let photos: PhotoAsset[] = [];
  export let activePhotoAssetId: string | undefined = undefined;
  export let selectedIds: Set<string> = new Set();
  export let onApply: (assetId: string) => void = () => {};
  export let onRemove: (assetId: string) => void = () => {};
  export let onToggleSelect: (assetId: string) => void = () => {};
  export let onToggleSelectAll: () => void = () => {};
  export let onDeleteSelected: () => void = () => {};
  export let onDuplicateSelected: () => void = () => {};
  export let onApplyStyleSelected: () => void = () => {};

  $: allSelected = photos.length > 0 && selectedIds.size === photos.length;
  $: noneSelected = selectedIds.size === 0;
</script>

<div class="space-y-3">
  <!-- Header with segmented select all / clear -->
  <div class="flex items-center justify-between">
    <h3 class="text-xs font-semibold uppercase tracking-wider text-[var(--c-text-muted)]">Suas Fotos</h3>
    {#if photos.length > 0}
      <button
        class="rounded-lg px-2.5 py-1 text-xs font-medium transition
          {allSelected
            ? 'bg-slate-900 text-white'
            : 'bg-slate-100 text-slate-600 hover:bg-slate-200'}
          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400"
        on:click={onToggleSelectAll}
        type="button"
        aria-label={allSelected ? 'Limpar seleção' : 'Selecionar tudo'}
      >
        {allSelected ? 'Limpar' : 'Selecionar tudo'}
      </button>
    {/if}
  </div>

  <!-- Empty state -->
  {#if photos.length === 0}
    <div class="flex flex-col items-center gap-2 rounded-xl border-2 border-dashed border-slate-200 bg-slate-50/50 py-8 text-center">
      <svg width="32" height="32" viewBox="0 0 24 24" fill="none" class="text-slate-300" aria-hidden="true">
        <path d="M4 7a2 2 0 0 1 2-2h2l1-1h6l1 1h2a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7Z" stroke="currentColor" stroke-width="1.5" />
        <path d="M8 14l2-2 2 2 3-3 3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
      </svg>
      <p class="text-sm text-[var(--c-text-faint)]">Envie suas fotos para começar</p>
    </div>
  {:else}
    <!-- Thumbnail grid -->
    <div class="grid grid-cols-4 gap-2">
      {#each photos as p, i (p.id)}
        {@const isChecked = selectedIds.has(p.id)}
        {@const isActive = activePhotoAssetId === p.id}
        <div
          class="group relative aspect-square overflow-hidden rounded-xl border-2 transition-all
            {isActive ? 'border-[var(--c-accent)] shadow-[0_0_0_1px_var(--c-accent)]' : 'border-transparent hover:border-slate-300'}"
        >
          <!-- Main click: apply to slot -->
          <button
            class="block h-full w-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[var(--c-accent)]"
            on:click={() => onApply(p.id)}
            type="button"
            title={p.name ?? `Foto ${i + 1}`}
            aria-label="Aplicar foto {i + 1} ao slot ativo"
          >
            <img
              class="pointer-events-none h-full w-full object-cover"
              src={p.src}
              alt={p.name ?? `Foto ${i + 1}`}
              loading="lazy"
              draggable="false"
            />
          </button>

          <!-- Checkbox overlay -->
          <button
            class="absolute left-1 top-1 z-10 grid h-6 w-6 place-items-center rounded-md transition
              {isChecked
                ? 'bg-[var(--c-primary)] text-white shadow-sm'
                : 'bg-white/80 text-slate-500 opacity-0 shadow-sm backdrop-blur group-hover:opacity-100'}
              focus-visible:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400"
            on:click|stopPropagation={() => onToggleSelect(p.id)}
            type="button"
            aria-label={isChecked ? 'Desmarcar foto' : 'Marcar foto'}
          >
            {#if isChecked}
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M20 6 9 17l-5-5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            {:else}
              <div class="h-3 w-3 rounded border-2 border-current"></div>
            {/if}
          </button>

          <!-- Remove button -->
          <button
            class="absolute right-1 top-1 z-10 grid h-6 w-6 place-items-center rounded-md bg-white/80 text-slate-500 opacity-0 shadow-sm backdrop-blur transition hover:bg-red-50 hover:text-red-500 group-hover:opacity-100 focus-visible:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-400"
            on:click|stopPropagation={() => onRemove(p.id)}
            type="button"
            aria-label="Remover foto {i + 1}"
            title="Remover"
          >
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M18 6 6 18M6 6l12 12" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" />
            </svg>
          </button>

          <!-- Index badge -->
          <span class="pointer-events-none absolute bottom-1 left-1 z-10 grid h-5 min-w-5 place-items-center rounded-md bg-black/50 px-1 text-[10px] font-semibold tabular-nums text-white">
            {i + 1}
          </span>
        </div>
      {/each}
    </div>

    <!-- Batch actions -->
    <BatchActions
      count={selectedIds.size}
      onDelete={onDeleteSelected}
      onDuplicate={onDuplicateSelected}
      onApplyStyle={onApplyStyleSelected}
    />
  {/if}
</div>
