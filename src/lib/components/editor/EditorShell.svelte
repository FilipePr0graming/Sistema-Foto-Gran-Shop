<script lang="ts">
  import { fade, fly } from 'svelte/transition';

  export let orderId = '';
  export let currentSlotIndex = 0;
  export let totalSlots = 0;
  export let onBack: () => void = () => {};

  $: progressPct = totalSlots > 0 ? Math.round(((currentSlotIndex + 1) / totalSlots) * 100) : 0;
</script>

<div class="editor-shell min-h-screen w-full text-[var(--c-text)]">
  <div class="editor-bg">
    <!-- Header -->
    <header
      class="relative z-30 mx-auto flex max-w-[1440px] items-center gap-4 px-4 py-3 lg:px-6"
      in:fade={{ duration: 200 }}
    >
      <div class="flex items-center gap-2.5">
        <button
          class="rounded-xl border border-[var(--c-border-strong)] bg-white/80 px-3 py-2 text-xs font-semibold backdrop-blur transition hover:bg-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400"
          on:click={onBack}
          type="button"
          aria-label="Voltar para tela inicial"
        >
          ← Voltar
        </button>

        <div class="hidden sm:flex items-center gap-2">
          <div class="grid h-8 w-8 place-items-center rounded-lg bg-[var(--c-accent)]/10 text-[var(--c-accent)]">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M4 7a2 2 0 0 1 2-2h2l1-1h6l1 1h2a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7Z" stroke="currentColor" stroke-width="2" />
              <path d="M8 14l2-2 2 2 3-3 3 3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </div>
          <div>
            <div class="text-sm font-semibold leading-tight">Polaroids</div>
            <div class="text-[11px] text-[var(--c-text-muted)]">Pedido: <span class="font-mono font-semibold text-[var(--c-text)]">{orderId}</span></div>
          </div>
        </div>
      </div>

      <div class="ml-auto flex items-center gap-3" in:fly={{ y: -4, duration: 200 }}>
        <span class="text-xs tabular-nums text-[var(--c-text-muted)]">{currentSlotIndex + 1} / {totalSlots}</span>
        <div class="h-1.5 w-28 sm:w-40 overflow-hidden rounded-full bg-slate-200/60">
          <div
            class="h-full rounded-full bg-[var(--c-accent)] transition-all duration-300"
            style="width: {progressPct}%"
          ></div>
        </div>
      </div>
    </header>

    <!-- Main content -->
    <main
      class="relative z-20 mx-auto grid max-w-[1440px] gap-4 px-4 pb-24 lg:px-6
        grid-cols-1 lg:grid-cols-[var(--sidebar-w)_1fr]"
    >
      <!-- Sidebar slot -->
      <div class="relative z-30" in:fly={{ x: -12, duration: 240 }}>
        <slot name="sidebar" />
      </div>

      <!-- Canvas slot -->
      <div class="relative z-20" in:fly={{ x: 12, duration: 240 }}>
        <slot name="canvas" />
      </div>
    </main>

    <!-- Bottom bar slot -->
    <slot name="bottombar" />
  </div>
</div>

<style>
  .editor-bg {
    position: relative;
    isolation: isolate;
    min-height: 100vh;
    background:
      radial-gradient(800px 500px at 8% 8%, rgba(99, 102, 241, 0.10), transparent 55%),
      radial-gradient(700px 500px at 90% 15%, rgba(251, 146, 60, 0.08), transparent 50%),
      radial-gradient(800px 600px at 50% 100%, rgba(34, 211, 238, 0.06), transparent 50%),
      linear-gradient(180deg, #f8fafc, #f1f5f9);
    background-size: 120% 120%;
    animation: bgShift 20s ease-in-out infinite;
  }

  @keyframes bgShift {
    0% { background-position: 0% 0%; }
    50% { background-position: 100% 100%; }
    100% { background-position: 0% 0%; }
  }
</style>
