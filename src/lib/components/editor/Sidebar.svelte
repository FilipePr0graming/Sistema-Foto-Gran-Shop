<script lang="ts">
  import type { PhotoAsset, TextNode } from '$lib/editor/types';
  import SidebarSection from './SidebarSection.svelte';
  import PhotoGallery from './PhotoGallery.svelte';
  import CaptionEditor from './CaptionEditor.svelte';

  export let photos: PhotoAsset[] = [];
  export let activePhotoAssetId: string | undefined = undefined;
  export let selectedPhotoIds: Set<string> = new Set();
  export let uploadedCount = 0;
  export let totalSlots = 0;
  export let uploadRemaining = 0;
  export let textNodes: TextNode[] = [];
  export let activePhotoAssetIdForApply: string | undefined = undefined;

  export let onPickPhoto: (ev: Event) => void = () => {};
  export let onApplyPhoto: (assetId: string) => void = () => {};
  export let onRemovePhoto: (assetId: string) => void = () => {};
  export let onToggleSelect: (assetId: string) => void = () => {};
  export let onToggleSelectAll: () => void = () => {};
  export let onDeleteSelected: () => void = () => {};
  export let onDuplicateSelected: () => void = () => {};
  export let onApplyStyleSelected: () => void = () => {};
  export let onApplySelectedPhotoToSlot: () => void = () => {};
  export let onAddText: () => void = () => {};
</script>

<aside class="flex flex-col gap-3" aria-label="Painel de ferramentas">
  <!-- Upload section -->
  <SidebarSection title="Enviar Fotos" badge="{uploadedCount}/{totalSlots}" defaultOpen={true}>
    <label
      class="group flex items-center gap-3 rounded-xl border-2 border-dashed p-3.5 transition
        {uploadRemaining > 0
          ? 'border-slate-200 bg-white cursor-pointer hover:border-[var(--c-accent)] hover:bg-orange-50/30'
          : 'border-slate-100 bg-slate-50 cursor-not-allowed opacity-50'}"
    >
      <input
        class="hidden"
        type="file"
        accept="image/*"
        multiple
        disabled={uploadRemaining <= 0}
        on:change={onPickPhoto}
        aria-label="Escolher arquivos de foto"
      />
      <div class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-slate-100 text-slate-500 transition group-hover:bg-orange-100 group-hover:text-[var(--c-accent)]">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M12 5v14" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
          <path d="M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
        </svg>
      </div>
      <div class="min-w-0">
        <div class="text-sm font-medium text-[var(--c-text)]">Escolher arquivos</div>
        <div class="text-xs text-[var(--c-text-muted)]">JPG, PNG, WEBP · até {uploadRemaining} foto{uploadRemaining !== 1 ? 's' : ''}</div>
      </div>
    </label>

    {#if activePhotoAssetIdForApply}
      <button
        class="mt-2 w-full rounded-xl bg-[var(--c-primary)] px-3 py-2.5 text-sm font-semibold text-white transition hover:bg-[var(--c-primary-hover)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400"
        on:click={onApplySelectedPhotoToSlot}
        type="button"
      >
        Aplicar foto ao slot
      </button>
    {/if}
  </SidebarSection>

  <!-- Gallery section -->
  <SidebarSection title="Suas Fotos" badge="{photos.length}" defaultOpen={true}>
    <PhotoGallery
      {photos}
      {activePhotoAssetId}
      selectedIds={selectedPhotoIds}
      onApply={onApplyPhoto}
      onRemove={onRemovePhoto}
      {onToggleSelect}
      {onToggleSelectAll}
      {onDeleteSelected}
      {onDuplicateSelected}
      {onApplyStyleSelected}
    />
  </SidebarSection>

  <!-- Text tools section -->
  <SidebarSection title="Texto" badge="{textNodes.length}/4" defaultOpen={true}>
    <CaptionEditor
      {textNodes}
      maxTexts={4}
      {onAddText}
    />
  </SidebarSection>
</aside>
