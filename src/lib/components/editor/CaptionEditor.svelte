<script lang="ts">
  import type { TextNode } from '$lib/editor/types';
  import { patchNode } from '$lib/editor/layoutStore.svelte';

  export let textNodes: TextNode[] = [];
  export let maxTexts = 4;
  export let onAddText: () => void = () => {};

  function styleFlags(fontStyle: string | undefined): { bold: boolean; italic: boolean } {
    const s = (fontStyle ?? 'normal').toLowerCase();
    return { bold: s.includes('bold'), italic: s.includes('italic') };
  }

  function composeFontStyle(bold: boolean, italic: boolean): string {
    if (bold && italic) return 'bold italic';
    if (bold) return 'bold';
    if (italic) return 'italic';
    return 'normal';
  }

  function handleTextInput(nodeId: string, e: Event) {
    const val = (e.currentTarget as HTMLInputElement).value.slice(0, 80);
    patchNode(nodeId, { text: val } as any);
  }

  function handleSizeInput(nodeId: string, e: Event) {
    const val = Number((e.currentTarget as HTMLInputElement).value);
    if (val >= 2 && val <= 18) {
      patchNode(nodeId, { fontSizeMm: val } as any);
    }
  }

  function handleColorInput(nodeId: string, e: Event) {
    patchNode(nodeId, { fill: (e.currentTarget as HTMLInputElement).value } as any);
  }
</script>

<div class="space-y-3">
  <div class="flex items-center justify-between">
    <h3 class="text-xs font-semibold uppercase tracking-wider text-[var(--c-text-muted)]">Textos</h3>
    <span class="text-xs tabular-nums text-[var(--c-text-faint)]">{textNodes.length}/{maxTexts}</span>
  </div>

  <button
    class="w-full rounded-xl border border-dashed border-slate-300 bg-white px-3 py-2.5 text-sm font-medium text-[var(--c-text-muted)] transition
      enabled:hover:border-slate-400 enabled:hover:text-[var(--c-text)] disabled:opacity-40 disabled:cursor-not-allowed
      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400"
    on:click={onAddText}
    disabled={textNodes.length >= maxTexts}
    type="button"
    aria-label="Adicionar texto"
  >
    + Adicionar texto
  </button>

  {#each textNodes as t (t.id)}
    {@const flags = styleFlags(t.fontStyle)}
    <div class="rounded-xl border border-[var(--c-border)] bg-white p-3 space-y-2.5">
      <input
        class="w-full rounded-lg border border-[var(--c-border)] bg-slate-50 px-3 py-2 text-sm outline-none transition focus:border-slate-400 focus:bg-white focus:ring-1 focus:ring-slate-300"
        value={t.text}
        on:input={(e) => handleTextInput(t.id, e)}
        maxlength={80}
        placeholder="Digite o texto..."
        aria-label="Conteúdo do texto"
      />

      <div class="flex items-center gap-2">
        <label class="flex items-center gap-1.5 text-xs text-[var(--c-text-muted)]">
          <span>Tam.</span>
          <input
            class="w-14 rounded-md border border-[var(--c-border)] bg-slate-50 px-2 py-1 text-xs tabular-nums outline-none transition focus:border-slate-400 focus:ring-1 focus:ring-slate-300"
            type="number"
            min={2}
            max={18}
            step={0.5}
            value={t.fontSizeMm}
            on:input={(e) => handleSizeInput(t.id, e)}
            aria-label="Tamanho da fonte em mm"
          />
          <span>mm</span>
        </label>

        <div class="ml-auto flex items-center gap-1">
          <input
            class="h-7 w-8 cursor-pointer rounded-md border border-[var(--c-border)] bg-white"
            type="color"
            value={t.fill}
            on:input={(e) => handleColorInput(t.id, e)}
            aria-label="Cor do texto"
            title="Cor"
          />

          <button
            class="grid h-7 w-7 place-items-center rounded-md text-xs font-bold transition
              {flags.bold ? 'bg-[var(--c-primary)] text-white' : 'border border-[var(--c-border)] bg-white text-slate-600 hover:bg-slate-50'}
              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400"
            on:click={() => patchNode(t.id, { fontStyle: composeFontStyle(!flags.bold, flags.italic) } as any)}
            type="button"
            aria-label={flags.bold ? 'Remover negrito' : 'Aplicar negrito'}
            title="Negrito"
          >
            B
          </button>

          <button
            class="grid h-7 w-7 place-items-center rounded-md text-xs transition
              {flags.italic ? 'bg-[var(--c-primary)] text-white' : 'border border-[var(--c-border)] bg-white text-slate-600 hover:bg-slate-50'}
              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400"
            style="font-style: italic"
            on:click={() => patchNode(t.id, { fontStyle: composeFontStyle(flags.bold, !flags.italic) } as any)}
            type="button"
            aria-label={flags.italic ? 'Remover itálico' : 'Aplicar itálico'}
            title="Itálico"
          >
            I
          </button>
        </div>
      </div>
    </div>
  {/each}
</div>
