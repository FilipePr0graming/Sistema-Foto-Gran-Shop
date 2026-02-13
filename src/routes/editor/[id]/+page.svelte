<script lang="ts">
  import { onDestroy, onMount } from 'svelte';
  import { page } from '$app/stores';
  import { goto } from '$app/navigation';
  import { layoutStore, exportLayout, loadLayout, patchNode, selectNode, upsertPhotoAsset } from '$lib/editor/layoutStore.svelte';
  import { mmToPx, round3 } from '$lib/editor/units';
  import { uuid } from '$lib/editor/id';
  import type { LayoutJSON, PhotoNode, TextNode } from '$lib/editor/types';

  import { EditorShell, Sidebar, SlotNavigator, CanvasStage, BottomBar, ConfirmDialog } from '$lib/components/editor';

  let StageCmp: any;
  let LayerCmp: any;
  let RectCmp: any;
  let ImageCmp: any;
  let TextCmp: any;
  let TransformerCmp: any;

  let activePhotoAssetId: string | undefined;
  let selectedPhotoAssetIds = new Set<string>();
  let showBackConfirm = false;
  let showFinalizeModal = false;
  let viewportW = 1200;
  let lastActiveSlotForAsset: string | undefined;

  const CAPTION_BAND_MM = 20;

  // --- Reactive layout state ---
  $: layout = $layoutStore;
  $: dpi = layout.dpi;
  $: stageWidthPx = Math.round(mmToPx(layout.template.stage.widthMm, dpi));
  $: stageHeightPx = Math.round(mmToPx(layout.template.stage.heightMm, dpi));
  $: orderId = $page.params.id;
  $: selectedSlotIndex = layout.selected?.slotId ? Math.max(0, layout.slots.findIndex((s) => s.id === layout.selected?.slotId)) : 0;
  $: photoCount = layout.slots.length;
  $: uploadedCount = layout.assets.photos.length;
  $: filledSlotCount = new Set(layout.nodes.filter((n) => n.type === 'photo').map((n: any) => n.slotId)).size;
  $: uploadRemaining = Math.max(0, photoCount - uploadedCount);
  $: activeSlotId = layout.selected?.slotId ?? layout.slots[0]?.id;
  $: slotTextNodes = layout.nodes.filter((n) => n.type === 'text' && n.slotId === activeSlotId).slice(0, 4) as TextNode[];

  // --- Stage dimensions ---
  function mm(n: number): number { return mmToPx(n, dpi); }

  $: captionBandPx = mm(CAPTION_BAND_MM);
  $: captionTopPx = Math.max(0, stageHeightPx - captionBandPx);
  $: photoAreaHpx = captionTopPx;
  $: captionAreaHpx = captionBandPx;

  const POLAROID_MAX_W = 560;
  const POLAROID_VW = 0.82;
  const POLAROID_PADDING = 18;
  const POLAROID_ASPECT = 634 / 710;

  $: polaroidW = Math.min(POLAROID_MAX_W, Math.round(viewportW * POLAROID_VW));
  $: polaroidH = Math.round(polaroidW / POLAROID_ASPECT);
  $: polaroidInnerW = polaroidW - POLAROID_PADDING * 2;
  $: polaroidInnerH = polaroidH - POLAROID_PADDING * 2;
  $: stageScale = Math.min(polaroidInnerW / stageWidthPx, polaroidInnerH / stageHeightPx);
  $: stageScaledW = Math.floor(stageWidthPx * stageScale);
  $: photoScaledH = Math.floor(photoAreaHpx * stageScale);
  $: captionScaledH = Math.floor(captionAreaHpx * stageScale);

  // --- Auto-select first slot ---
  $: if (layout.slots.length > 0 && !layout.selected?.slotId) {
    queueMicrotask(() => selectNode(layout.slots[0].id, undefined));
  }

  // --- Sync active photo asset when slot changes ---
  $: if (activeSlotId && activeSlotId !== lastActiveSlotForAsset) {
    lastActiveSlotForAsset = activeSlotId;
    const p = layout.nodes.find((n) => n.type === 'photo' && n.slotId === activeSlotId) as PhotoNode | undefined;
    activePhotoAssetId = p?.assetId;
  }

  // --- Load Konva dynamically ---
  async function ensureKonvaComponents(): Promise<void> {
    const mod = await import('svelte-konva');
    StageCmp = mod.Stage;
    LayerCmp = mod.Layer;
    RectCmp = mod.Rect;
    ImageCmp = mod.Image;
    TextCmp = mod.Text;
    TransformerCmp = mod.Transformer;
  }

  onMount(() => {
    const update = () => { viewportW = window.innerWidth; };
    update();
    window.addEventListener('resize', update);
    void ensureKonvaComponents();
    return () => window.removeEventListener('resize', update);
  });

  // --- Navigation ---
  function requestBack(): void { showBackConfirm = true; }
  function cancelBack(): void { showBackConfirm = false; }
  function confirmBack(): void { showBackConfirm = false; goto('/'); }

  function prevSlot(): void {
    const i = Math.max(0, selectedSlotIndex - 1);
    selectNode(layout.slots[i].id, undefined);
  }

  function nextSlot(): void {
    const i = Math.min(layout.slots.length - 1, selectedSlotIndex + 1);
    selectNode(layout.slots[i].id, undefined);
  }

  // --- Photo management ---
  function applySelectedPhotoToSlot(): void {
    const slotId = layout.selected?.slotId ?? layout.slots[0]?.id;
    if (!slotId || !activePhotoAssetId) return;
    const slot = layout.slots.find((s) => s.id === slotId);
    if (!slot) return;

    const existing = layout.nodes.find((n) => n.type === 'photo' && n.slotId === slotId) as PhotoNode | undefined;
    if (existing) {
      patchNode(existing.id, { assetId: activePhotoAssetId } as any);
      selectNode(slotId, existing.id);
      return;
    }

    const curr = exportLayout();
    const photoHmm = Math.max(1, slot.heightMm - CAPTION_BAND_MM);
    const node: PhotoNode = {
      id: uuid(), type: 'photo', origin: 'center', slotId,
      assetId: activePhotoAssetId,
      xMm: round3(slot.xMm + slot.widthMm / 2),
      yMm: round3(slot.yMm + photoHmm / 2),
      rotationDeg: 0, scale: 1
    };
    loadLayout({ ...curr, nodes: [...curr.nodes, node], selected: { slotId, nodeId: node.id } } as LayoutJSON);
  }

  function applyPhotoAssetToCurrentSlot(assetId: string): void {
    activePhotoAssetId = assetId;
    applySelectedPhotoToSlot();
  }

  function removePhotoAsset(assetId: string): void {
    const curr = exportLayout();
    const asset = curr.assets.photos.find((p) => p.id === assetId);
    if (asset?.src?.startsWith('blob:')) URL.revokeObjectURL(asset.src);
    curr.assets.photos = curr.assets.photos.filter((p) => p.id !== assetId);
    curr.nodes = curr.nodes.filter((n) => !(n.type === 'photo' && (n as any).assetId === assetId));
    if (activePhotoAssetId === assetId) activePhotoAssetId = undefined;
    if (selectedPhotoAssetIds.has(assetId)) {
      const next = new Set(selectedPhotoAssetIds);
      next.delete(assetId);
      selectedPhotoAssetIds = next;
    }
    loadLayout(curr);
  }

  function togglePhotoSelection(assetId: string): void {
    const next = new Set(selectedPhotoAssetIds);
    if (next.has(assetId)) next.delete(assetId);
    else next.add(assetId);
    selectedPhotoAssetIds = next;
  }

  function toggleSelectAllPhotos(): void {
    const allIds = layout.assets.photos.map((p) => p.id);
    if (allIds.length === 0 || selectedPhotoAssetIds.size === allIds.length) {
      selectedPhotoAssetIds = new Set();
    } else {
      selectedPhotoAssetIds = new Set(allIds);
    }
  }

  function duplicateSelectedPhotos(): void {
    const curr = exportLayout();
    const ids = Array.from(selectedPhotoAssetIds);
    if (ids.length === 0) return;
    const nextSelected = new Set<string>();
    for (const assetId of ids) {
      const asset = curr.assets.photos.find((p) => p.id === assetId);
      if (!asset) continue;
      const copyId = upsertPhotoAsset({ src: asset.src, name: asset.name ? `${asset.name} (cópia)` : 'Cópia' });
      nextSelected.add(copyId);
      activePhotoAssetId = copyId;
    }
    selectedPhotoAssetIds = nextSelected;
  }

  function removeSelectedPhotos(): void {
    const ids = Array.from(selectedPhotoAssetIds);
    if (ids.length === 0) return;
    for (const id of ids) removePhotoAsset(id);
    selectedPhotoAssetIds = new Set();
  }

  function applyStyleToSelectedPhotos(): void {
    const curr = exportLayout();
    const refSlotId = curr.selected?.slotId ?? curr.slots[0]?.id;
    if (!refSlotId) return;
    const ref = curr.nodes.find((n) => n.type === 'photo' && n.slotId === refSlotId) as PhotoNode | undefined;
    if (!ref) return;
    const ids = new Set(selectedPhotoAssetIds);
    const nextNodes = curr.nodes.map((n) => {
      if (n.type !== 'photo') return n;
      const p = n as PhotoNode;
      if (!ids.has(p.assetId)) return n;
      return { ...p, rotationDeg: ref.rotationDeg, scale: ref.scale };
    });
    loadLayout({ ...curr, nodes: nextNodes } as LayoutJSON);
  }

  // --- Photo upload ---
  async function onPickPhoto(ev: Event): Promise<void> {
    const input = ev.target as HTMLInputElement;
    const files = Array.from(input.files ?? []);
    if (files.length === 0) return;

    const curr = exportLayout();
    const startIndex = curr.selected?.slotId ? Math.max(0, curr.slots.findIndex((s) => s.id === curr.selected?.slotId)) : 0;
    const maxAdd = Math.max(0, curr.slots.length - curr.assets.photos.length);
    const toAdd = files.slice(0, maxAdd);
    if (toAdd.length === 0) { input.value = ''; return; }

    const nextNodes = [...curr.nodes];
    let firstAssetId: string | undefined;
    let firstNodeId: string | undefined;
    let firstSlotId: string | undefined;

    for (let i = 0; i < toAdd.length; i++) {
      const file = toAdd[i];
      const url = URL.createObjectURL(file);
      const assetId = uuid();
      curr.assets.photos.push({ id: assetId, src: url, name: file.name });
      const slot = curr.slots[startIndex + i] ?? curr.slots[0];
      if (!slot) continue;
      const photoHmm = Math.max(1, slot.heightMm - CAPTION_BAND_MM);
      if (i === 0) { firstAssetId = assetId; firstSlotId = slot.id; }
      const node: PhotoNode = {
        id: uuid(), type: 'photo', origin: 'center', slotId: slot.id, assetId,
        xMm: round3(slot.xMm + slot.widthMm / 2),
        yMm: round3(slot.yMm + photoHmm / 2),
        rotationDeg: 0, scale: 1
      };
      if (i === 0) firstNodeId = node.id;
      nextNodes.push(node);
    }

    if (firstAssetId) activePhotoAssetId = firstAssetId;
    if (firstSlotId && firstNodeId) {
      loadLayout({ ...curr, nodes: nextNodes, selected: { slotId: firstSlotId, nodeId: firstNodeId } } as LayoutJSON);
    } else {
      loadLayout({ ...curr, nodes: nextNodes } as LayoutJSON);
    }
    input.value = '';
  }

  // --- Text management ---
  function addText(): void {
    const curr = exportLayout();
    const slotId = curr.selected?.slotId ?? curr.slots[0]?.id;
    if (!slotId) return;
    const existingCount = curr.nodes.filter((n) => n.type === 'text' && n.slotId === slotId).length;
    if (existingCount >= 4) return;
    const slot = curr.slots.find((s) => s.id === slotId);
    if (!slot) return;
    const captionTopMm = curr.template.stage.heightMm - CAPTION_BAND_MM;
    const node: TextNode = {
      id: uuid(), type: 'text', origin: 'topleft', slotId,
      text: 'Novo Texto',
      xMm: round3(slot.xMm + slot.widthMm * 0.28),
      yMm: round3(slot.yMm + captionTopMm + 5),
      rotationDeg: 0, fontFamily: 'Pacifico', fontSizeMm: 3, fill: '#111111', fontStyle: 'normal'
    };
    loadLayout({ ...curr, nodes: [...curr.nodes, node], selected: { slotId, nodeId: node.id } } as LayoutJSON);
  }

  // --- Finalize (optional JSON download) ---
  function handleFinalize(): void {
    showFinalizeModal = true;
  }

  function downloadLayoutJSON(): void {
    const data = exportLayout();
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `pedido-${orderId}.json`;
    a.click();
    URL.revokeObjectURL(url);
    showFinalizeModal = false;
  }

  // --- Cleanup ---
  onDestroy(() => {
    const curr = exportLayout();
    for (const p of curr.assets.photos) {
      if (p.src.startsWith('blob:')) URL.revokeObjectURL(p.src);
    }
  });
</script>

<svelte:head>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="anonymous" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Pacifico&display=swap" rel="stylesheet" />
</svelte:head>

{#if StageCmp}
  <EditorShell {orderId} currentSlotIndex={selectedSlotIndex} totalSlots={photoCount} onBack={requestBack}>
    <svelte:fragment slot="sidebar">
      <Sidebar
        photos={layout.assets.photos}
        {activePhotoAssetId}
        selectedPhotoIds={selectedPhotoAssetIds}
        {uploadedCount}
        totalSlots={photoCount}
        {uploadRemaining}
        textNodes={slotTextNodes}
        activePhotoAssetIdForApply={activePhotoAssetId}
        {onPickPhoto}
        onApplyPhoto={applyPhotoAssetToCurrentSlot}
        onRemovePhoto={removePhotoAsset}
        onToggleSelect={togglePhotoSelection}
        onToggleSelectAll={toggleSelectAllPhotos}
        onDeleteSelected={removeSelectedPhotos}
        onDuplicateSelected={duplicateSelectedPhotos}
        onApplyStyleSelected={applyStyleToSelectedPhotos}
        onApplySelectedPhotoToSlot={applySelectedPhotoToSlot}
        onAddText={addText}
      />
    </svelte:fragment>

    <svelte:fragment slot="canvas">
      <div class="rounded-2xl border border-[var(--c-border)] bg-[var(--c-surface)] p-3 shadow-[var(--shadow-card)] backdrop-blur">
        <div class="mb-3">
          <SlotNavigator
            currentIndex={selectedSlotIndex}
            total={photoCount}
            onPrev={prevSlot}
            onNext={nextSlot}
          />
        </div>
        <CanvasStage
          {StageCmp} {LayerCmp} {RectCmp} {ImageCmp} {TextCmp} {TransformerCmp}
          {layout} {activeSlotId} {stageWidthPx} {stageHeightPx} {stageScale}
          {stageScaledW} {photoScaledH} {captionScaledH} {captionTopPx}
          {captionAreaHpx} {photoAreaHpx} {dpi}
        />
      </div>
    </svelte:fragment>

    <svelte:fragment slot="bottombar">
      <BottomBar
        filledCount={filledSlotCount}
        totalSlots={photoCount}
        onBack={requestBack}
        onFinalize={handleFinalize}
      />
    </svelte:fragment>
  </EditorShell>
{:else}
  <div class="grid min-h-screen place-items-center bg-[var(--c-bg)]">
    <div class="flex flex-col items-center gap-3">
      <div class="h-8 w-8 animate-spin rounded-full border-2 border-slate-300 border-t-slate-600"></div>
      <p class="text-sm text-[var(--c-text-muted)]">Carregando editor…</p>
    </div>
  </div>
{/if}

<!-- Back confirmation dialog -->
<ConfirmDialog
  open={showBackConfirm}
  title="Voltar para a tela do pedido?"
  message="Se você voltar agora, suas alterações continuarão aqui enquanto a página estiver aberta, mas você pode perder o que não exportou."
  confirmLabel="Sim, voltar"
  cancelLabel="Cancelar"
  onConfirm={confirmBack}
  onCancel={cancelBack}
/>

<!-- Finalize dialog -->
<ConfirmDialog
  open={showFinalizeModal}
  title="Finalizar Pedido"
  message="O pedido está completo! Por enquanto, você pode baixar o layout como arquivo JSON. Integração com backend será adicionada em breve."
  confirmLabel="Baixar JSON"
  cancelLabel="Fechar"
  onConfirm={downloadLayoutJSON}
  onCancel={() => showFinalizeModal = false}
/>
