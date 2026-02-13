<script lang="ts">
  import { onDestroy, onMount } from 'svelte';
  import { get } from 'svelte/store';
  import { layoutStore, patchNode, selectNode } from '$lib/editor/layoutStore.svelte';
  import { mmToPx, pxToMm, round3 } from '$lib/editor/units';
  import type { AnyNode, LayoutJSON, PhotoNode, TextNode } from '$lib/editor/types';

  export let StageCmp: any;
  export let LayerCmp: any;
  export let RectCmp: any;
  export let ImageCmp: any;
  export let TextCmp: any;
  export let TransformerCmp: any;

  export let layout: LayoutJSON;
  export let activeSlotId: string;
  export let stageWidthPx: number;
  export let stageHeightPx: number;
  export let stageScale: number;
  export let stageScaledW: number;
  export let photoScaledH: number;
  export let captionScaledH: number;
  export let captionTopPx: number;
  export let captionAreaHpx: number;
  export let photoAreaHpx: number;
  export let dpi: number;

  let stageRef: any;
  let transformerRef: any;
  let wheelBound = false;

  const imageCache = new Map<string, HTMLImageElement>();
  const imageLoading = new Set<string>();
  let imageTick = 0;

  const PHOTO_MIN_SCALE = 0.5;
  const PHOTO_MAX_SCALE = 6;

  function mm(n: number): number {
    return mmToPx(n, dpi);
  }

  function toMm(px: number): number {
    return round3(pxToMm(px, dpi));
  }

  function clamp(n: number, min: number, max: number): number {
    return Math.max(min, Math.min(max, n));
  }

  function ensureImage(src: string): HTMLImageElement | undefined {
    if (!src) return undefined;
    const cached = imageCache.get(src);
    if (cached) return cached;
    if (imageLoading.has(src)) return undefined;
    imageLoading.add(src);
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = () => {
      imageCache.set(src, img);
      imageLoading.delete(src);
      imageTick += 1;
    };
    img.onerror = () => {
      imageLoading.delete(src);
      imageTick += 1;
    };
    img.src = src;
    return undefined;
  }

  function refreshTransformer(): void {
    if (!transformerRef || !stageRef) return;
    const nodeId = get(layoutStore).selected?.nodeId;
    const node = nodeId ? stageRef.findOne(`#${nodeId}`) : null;
    transformerRef.nodes(node ? [node] : []);
    transformerRef.getLayer()?.batchDraw();
  }

  let transformerRaf: number | null = null;
  function scheduleTransformerRefresh(): void {
    if (transformerRaf != null) return;
    transformerRaf = requestAnimationFrame(() => {
      transformerRaf = null;
      refreshTransformer();
    });
  }

  $: if (StageCmp) {
    scheduleTransformerRefresh();
    bindWheelListener();
  }

  function bindWheelListener(): void {
    if (wheelBound || !stageRef) return;
    const el = stageRef.container?.();
    if (!el) return;
    const handler = (evt: WheelEvent) => {
      evt.preventDefault();
      const scaleBy = evt.deltaY > 0 ? 0.92 : 1.08;
      const p = clientToStagePoint(evt.clientX, evt.clientY);
      if (!p) return;
      zoomActivePhotoAt(p, scaleBy);
    };
    el.addEventListener('wheel', handler, { passive: false });
    wheelBound = true;
    onDestroy(() => el.removeEventListener('wheel', handler as any));
  }

  function clientToStagePoint(clientX: number, clientY: number): { x: number; y: number } | null {
    if (!stageRef) return null;
    const rect = stageRef.container?.().getBoundingClientRect?.();
    if (!rect) return null;
    return {
      x: (clientX - rect.left) / stageScale,
      y: (clientY - rect.top) / stageScale
    };
  }

  function getActivePhotoNode(): PhotoNode | undefined {
    return layout.nodes.find((n) => n.type === 'photo' && n.slotId === activeSlotId) as PhotoNode | undefined;
  }

  function zoomActivePhotoAt(point: { x: number; y: number }, scaleBy: number): void {
    const node = getActivePhotoNode();
    if (!node) return;
    const oldScale = node.scale ?? 1;
    const nextScale = clamp(oldScale * scaleBy, PHOTO_MIN_SCALE, PHOTO_MAX_SCALE);
    if (nextScale === oldScale) return;
    const x = mm(node.xMm);
    const y = mm(node.yMm);
    const newX = point.x - (point.x - x) * (nextScale / oldScale);
    const newY = point.y - (point.y - y) * (nextScale / oldScale);
    patchNode(node.id, { scale: round3(nextScale), xMm: toMm(newX), yMm: toMm(newY) } as any);
  }

  let pinchLastDist: number | null = null;
  let pinchLastCenter: { x: number; y: number } | null = null;

  function getTouchCenter(t1: any, t2: any): { x: number; y: number } {
    return { x: (t1.clientX + t2.clientX) / 2, y: (t1.clientY + t2.clientY) / 2 };
  }

  function getTouchDist(t1: any, t2: any): number {
    const dx = t1.clientX - t2.clientX;
    const dy = t1.clientY - t2.clientY;
    return Math.sqrt(dx * dx + dy * dy);
  }

  function onPhotoTouchMove(e: any): void {
    const evt = e.evt;
    if (!evt?.touches || evt.touches.length !== 2) return;
    evt.preventDefault?.();
    const t1 = evt.touches[0];
    const t2 = evt.touches[1];
    const dist = getTouchDist(t1, t2);
    const centerClient = getTouchCenter(t1, t2);
    if (pinchLastDist == null || pinchLastCenter == null) {
      pinchLastDist = dist;
      pinchLastCenter = centerClient;
      return;
    }
    const scaleBy = dist / pinchLastDist;
    const centerStage = clientToStagePoint(centerClient.x, centerClient.y);
    if (!centerStage) return;
    zoomActivePhotoAt(centerStage, scaleBy);
    pinchLastDist = dist;
    pinchLastCenter = centerClient;
  }

  function onPhotoTouchEnd(e: any): void {
    const evt = e.evt;
    if (!evt?.touches || evt.touches.length < 2) {
      pinchLastDist = null;
      pinchLastCenter = null;
    }
  }

  function onStageMouseDown(e: any): void {
    if (e.target === e.target.getStage()) {
      selectNode(undefined, undefined);
    }
  }

  function nodeCommonHandlers(node: AnyNode) {
    return {
      draggable: true,
      id: node.id,
      onClick: () => selectNode(node.slotId, node.id),
      onTap: () => selectNode(node.slotId, node.id),
      onDragMove: (e: any) => {
        if (node.type !== 'text') return;
        const t = e.target;
        const w = t.width();
        const h = t.height();
        const x = clamp(t.x(), 0, Math.max(0, stageWidthPx - w));
        const y = clamp(t.y(), captionTopPx, Math.max(captionTopPx, stageHeightPx - h));
        if (x !== t.x()) t.x(x);
        if (y !== t.y()) t.y(y);
      },
      onDragEnd: (e: any) => {
        if (node.type === 'text') {
          const t = e.target;
          const w = t.width();
          const h = t.height();
          const x = clamp(t.x(), 0, Math.max(0, stageWidthPx - w));
          const y = clamp(t.y(), captionTopPx, Math.max(captionTopPx, stageHeightPx - h));
          t.x(x);
          t.y(y);
        }
        patchNode(node.id, { xMm: toMm(e.target.x()), yMm: toMm(e.target.y()) } as any);
      },
      onTransformEnd: (e: any) => {
        const k = e.target;
        const rotationDeg = k.rotation();
        const scaleX = k.scaleX();
        k.scaleX(1);
        k.scaleY(1);
        if (node.type === 'photo') {
          patchNode(node.id, { rotationDeg, scale: round3((node as PhotoNode).scale * scaleX), xMm: toMm(k.x()), yMm: toMm(k.y()) } as any);
        } else {
          patchNode(node.id, { rotationDeg, xMm: toMm(k.x()), yMm: toMm(k.y()) } as any);
        }
      }
    };
  }

  function captionTextHandlers(node: TextNode) {
    const inset = 8;
    return {
      draggable: true,
      id: node.id,
      onClick: () => selectNode(node.slotId, node.id),
      onTap: () => selectNode(node.slotId, node.id),
      dragBoundFunc: function (this: any, pos: { x: number; y: number }) {
        const rect = this.getClientRect();
        const offsetX = rect.x - this.x();
        const offsetY = rect.y - this.y();
        let x = pos.x;
        let y = pos.y;
        const left = x + offsetX;
        const top = y + offsetY;
        const right = left + rect.width;
        const bottom = top + rect.height;
        if (left < inset) x += inset - left;
        if (top < inset) y += inset - top;
        if (right > stageWidthPx - inset) x -= right - (stageWidthPx - inset);
        if (bottom > captionAreaHpx - inset) y -= bottom - (captionAreaHpx - inset);
        return { x, y };
      },
      onDragEnd: (e: any) => {
        const t = e.target;
        const rect = t.getClientRect();
        const left = rect.x;
        const top = rect.y;
        const right = rect.x + rect.width;
        const bottom = rect.y + rect.height;
        if (left < inset) t.x(t.x() + (inset - left));
        if (top < inset) t.y(t.y() + (inset - top));
        if (right > stageWidthPx - inset) t.x(t.x() - (right - (stageWidthPx - inset)));
        if (bottom > captionAreaHpx - inset) t.y(t.y() - (bottom - (captionAreaHpx - inset)));
        patchNode(node.id, { xMm: toMm(t.x()), yMm: toMm(t.y() + captionTopPx) } as any);
      }
    };
  }

  onDestroy(() => {
    if (transformerRaf != null) cancelAnimationFrame(transformerRaf);
  });
</script>

<div class="grid place-items-center rounded-xl bg-white/60 p-2 ring-1 ring-[var(--c-border)]">
  <div class="canvas-polaroid-frame">
    <!-- Photo area -->
    <div class="overflow-hidden" style="width:{stageScaledW}px; height:{photoScaledH}px;">
      <div style="transform: scale({stageScale}); transform-origin: top left; width:{stageWidthPx}px; height:{photoAreaHpx}px;">
        <svelte:component
          this={StageCmp}
          bind:konvaNode={stageRef}
          width={stageWidthPx}
          height={photoAreaHpx}
          on:mousedown={onStageMouseDown}
          on:touchstart={onStageMouseDown}
          on:touchmove={onPhotoTouchMove}
          on:touchend={onPhotoTouchEnd}
          on:touchcancel={onPhotoTouchEnd}
          style="background: transparent"
        >
          <svelte:component this={LayerCmp} clipX={0} clipY={0} clipWidth={stageWidthPx} clipHeight={photoAreaHpx}>
            <svelte:component this={RectCmp} x={0} y={0} width={stageWidthPx} height={photoAreaHpx} fill="#ffffff" />

            {#each layout.nodes.filter((n) => n.slotId === activeSlotId && n.type === 'photo') as node (node.id)}
              {@const asset = layout.assets.photos.find((p) => p.id === (node as PhotoNode).assetId)}
              {@const _t = imageTick}
              {@const img = asset?.src ? ensureImage(asset.src) : undefined}
              {#if asset && img}
                <svelte:component
                  this={ImageCmp}
                  {...nodeCommonHandlers(node)}
                  x={mm(node.xMm)}
                  y={mm(node.yMm)}
                  rotation={node.rotationDeg}
                  image={img}
                  offsetX={img.width / 2}
                  offsetY={img.height / 2}
                  scaleX={(node as PhotoNode).scale}
                  scaleY={(node as PhotoNode).scale}
                />
              {/if}
            {/each}

            <svelte:component
              this={TransformerCmp}
              bind:konvaNode={transformerRef}
              rotateEnabled={true}
              enabledAnchors={['top-left', 'top-right', 'bottom-left', 'bottom-right']}
              boundBoxFunc={(oldBox: any, newBox: any) => {
                if (newBox.width < 8 || newBox.height < 8) return oldBox;
                return newBox;
              }}
            />
          </svelte:component>
        </svelte:component>
      </div>
    </div>

    <!-- Caption area -->
    <div class="relative bg-white" style="width:{stageScaledW}px; height:{captionScaledH}px;">
      <div class="relative w-full h-full grid place-items-center">
        <div style="transform: scale({stageScale}); transform-origin: top left; width:{stageWidthPx}px; height:{captionAreaHpx}px;">
          <svelte:component this={StageCmp} width={stageWidthPx} height={captionAreaHpx} style="background: transparent">
            <svelte:component this={LayerCmp}>
              <svelte:component this={RectCmp} x={0} y={0} width={stageWidthPx} height={captionAreaHpx} fill="#ffffff" listening={false} />

              {#each layout.nodes.filter((n) => n.slotId === activeSlotId && n.type === 'text') as node (node.id)}
                <svelte:component
                  this={TextCmp}
                  {...captionTextHandlers(node as TextNode)}
                  x={mm((node as TextNode).xMm)}
                  y={mm((node as TextNode).yMm) - captionTopPx}
                  rotation={(node as TextNode).rotationDeg}
                  text={(node as TextNode).text}
                  fontFamily={(node as TextNode).fontFamily}
                  fontSize={mm((node as TextNode).fontSizeMm)}
                  fill={(node as TextNode).fill}
                  fontStyle={(node as TextNode).fontStyle ?? 'normal'}
                />
              {/each}
            </svelte:component>
          </svelte:component>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
  .canvas-polaroid-frame {
    width: fit-content;
    background: #ffffff;
    padding: 10px;
    display: flex;
    flex-direction: column;
    box-shadow:
      0 25px 60px rgba(15, 23, 42, 0.14),
      0 8px 20px rgba(15, 23, 42, 0.06),
      0 2px 0 rgba(255, 255, 255, 0.65) inset;
    animation: canvasPop 380ms cubic-bezier(0.2, 0.9, 0.2, 1) 1;
  }

  @keyframes canvasPop {
    0% {
      transform: translateY(8px) scale(0.99);
      opacity: 0;
    }
    100% {
      transform: translateY(0) scale(1);
      opacity: 1;
    }
  }
</style>
