<script lang="ts">
  import { goto } from '$app/navigation';
  import { fade } from 'svelte/transition';

  let orderId = '';
  let hasError = false;

  function submit(): void {
    const id = orderId.trim();
    if (!id) {
      hasError = true;
      return;
    }
    hasError = false;
    goto(`/editor/${encodeURIComponent(id)}`);
  }

  function onInput(): void {
    if (hasError && orderId.trim()) hasError = false;
  }
</script>

<svelte:head>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="anonymous" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <title>Polaroids — Editor de Fotos</title>
</svelte:head>

<div class="entry-bg grid min-h-screen w-full place-items-center p-4">
  <div in:fade={{ duration: 300 }}>
    <form
      class="w-full max-w-sm rounded-2xl border border-[var(--c-border)] bg-white p-8 shadow-[var(--shadow-elevated)]"
      on:submit|preventDefault={submit}
      aria-label="Formulário de acesso ao editor"
    >
      <!-- Icon -->
      <div class="mb-5 flex justify-center">
        <div class="grid h-14 w-14 place-items-center rounded-2xl bg-gradient-to-br from-orange-100 to-amber-50 text-[var(--c-accent)]">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M4 7a2 2 0 0 1 2-2h2l1-1h6l1 1h2a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7Z" stroke="currentColor" stroke-width="2" />
            <path d="M8 14l2-2 2 2 3-3 3 3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </div>
      </div>

      <!-- Title -->
      <div class="text-center">
        <h1 class="text-xl font-bold tracking-tight text-[var(--c-text)]">Acessar Editor</h1>
        <p class="mt-1.5 text-sm text-[var(--c-text-muted)]">Digite o código do seu pedido para começar.</p>
      </div>

      <!-- Input -->
      <div class="mt-6">
        <label for="orderId" class="mb-1.5 block text-xs font-semibold text-[var(--c-text-muted)] uppercase tracking-wider">
          Código do Pedido
        </label>
        <input
          id="orderId"
          class="w-full rounded-xl border px-4 py-3 text-sm outline-none transition
            {hasError
              ? 'border-red-400 bg-red-50/50 focus:border-red-500 focus:ring-2 focus:ring-red-200'
              : 'border-[var(--c-border-strong)] bg-slate-50 focus:border-[var(--c-accent)] focus:bg-white focus:ring-2 focus:ring-orange-100'}"
          placeholder="Ex: PED-12345"
          bind:value={orderId}
          on:input={onInput}
          autocomplete="off"
          aria-invalid={hasError}
          aria-describedby={hasError ? 'order-error' : undefined}
        />
        {#if hasError}
          <p id="order-error" class="mt-1.5 text-xs text-red-500" role="alert">
            Informe o código do pedido para continuar.
          </p>
        {/if}
      </div>

      <!-- Submit -->
      <button
        class="mt-5 w-full rounded-xl bg-[var(--c-accent)] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[var(--c-accent-hover)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-300 focus-visible:ring-offset-2 active:scale-[0.98]"
        type="submit"
      >
        Acessar Editor
      </button>
    </form>

    <!-- Footer -->
    <p class="mt-4 text-center text-xs text-[var(--c-text-faint)]">
      Sistema de edição de Polaroids · 100% no navegador
    </p>
  </div>
</div>

<style>
  .entry-bg {
    background:
      radial-gradient(600px 400px at 30% 20%, rgba(251, 146, 60, 0.08), transparent 60%),
      radial-gradient(500px 400px at 80% 80%, rgba(99, 102, 241, 0.06), transparent 50%),
      linear-gradient(180deg, #f8fafc, #f1f5f9);
  }
</style>
