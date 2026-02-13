<script lang="ts">
  import { fade, scale } from 'svelte/transition';

  export let open = false;
  export let title = 'Confirmar';
  export let message = '';
  export let confirmLabel = 'Confirmar';
  export let cancelLabel = 'Cancelar';
  export let variant: 'default' | 'danger' = 'default';
  export let onConfirm: () => void = () => {};
  export let onCancel: () => void = () => {};

  function handleKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape') onCancel();
  }
</script>

<svelte:window on:keydown={handleKeydown} />

{#if open}
  <div
    class="fixed inset-0 z-[100] grid place-items-center p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="confirm-dialog-title"
    in:fade={{ duration: 120 }}
    out:fade={{ duration: 100 }}
  >
    <button
      class="absolute inset-0 bg-black/30 backdrop-blur-sm"
      on:click={onCancel}
      aria-label="Fechar diálogo"
      type="button"
      tabindex="-1"
    ></button>

    <div
      class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-[var(--shadow-modal)]"
      transition:scale={{ start: 0.96, duration: 140 }}
    >
      <div class="flex items-start gap-3">
        <div class="grid h-10 w-10 shrink-0 place-items-center rounded-xl {variant === 'danger' ? 'bg-red-100 text-red-600' : 'bg-slate-100 text-slate-700'}">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M12 9v4" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            <path d="M12 17h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            <path d="M10.3 4.2 2.6 18a2 2 0 0 0 1.7 3h15.4a2 2 0 0 0 1.7-3L13.7 4.2a2 2 0 0 0-3.4 0Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
          </svg>
        </div>
        <div class="flex-1 min-w-0">
          <h2 id="confirm-dialog-title" class="text-base font-semibold leading-tight">{title}</h2>
          <p class="mt-1.5 text-sm text-[var(--c-text-muted)] leading-relaxed">{message}</p>
        </div>
      </div>

      <div class="mt-5 flex gap-2">
        <button
          class="flex-1 rounded-xl border border-[var(--c-border-strong)] bg-white px-4 py-2.5 text-sm font-semibold transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400"
          on:click={onCancel}
          type="button"
        >
          {cancelLabel}
        </button>
        <button
          class="flex-1 rounded-xl px-4 py-2.5 text-sm font-semibold text-white transition focus-visible:outline-none focus-visible:ring-2 {variant === 'danger' ? 'bg-[var(--c-danger)] hover:bg-[var(--c-danger-hover)] focus-visible:ring-red-400' : 'bg-[var(--c-primary)] hover:bg-[var(--c-primary-hover)] focus-visible:ring-slate-400'}"
          on:click={onConfirm}
          type="button"
        >
          {confirmLabel}
        </button>
      </div>
    </div>
  </div>
{/if}
