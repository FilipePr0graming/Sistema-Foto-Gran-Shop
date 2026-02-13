# Polaroids — Editor de Fotos

Editor visual de Polaroids 100% no navegador. O cliente acessa pelo código do pedido, faz upload de fotos, posiciona/ajusta cada uma nos slots da grade, adiciona textos personalizados e (futuramente) finaliza o pedido.

## Stack

- **SvelteKit** com `adapter-static` (site estático, sem SSR)
- **Svelte 5** + **TypeScript**
- **Tailwind CSS v4**
- **Konva.js** + **svelte-konva** (canvas de edição)
- **Vite** (bundler)

## Pré-requisitos

- Node.js >= 18
- npm >= 9

## Rodar local

```sh
npm install
npm run dev
```

Acesse `http://localhost:5173`. Para abrir automaticamente no navegador:

```sh
npm run dev -- --open
```

## Build estático

```sh
npm run build
```

Os arquivos serão gerados na pasta `build/`. Para preview local do build:

```sh
npm run preview
```

## Deploy em hospedagem estática

O projeto usa `adapter-static` com **fallback SPA** (`200.html`), necessário para que rotas dinâmicas como `/editor/[id]` funcionem.

### Hostinger / Netlify / Vercel

1. Importe o repositório via GitHub.
2. Configure:
   - **Build command:** `npm run build`
   - **Publish directory:** `build`
3. Para **Hostinger** ou qualquer hospedagem que não suporte SPA nativamente, crie um arquivo `.htaccess` na raiz do `build/`:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /200.html [L]
```

Para **Netlify**, crie `build/_redirects`:

```
/* /200.html 200
```

Para **Vercel**, o framework é detectado automaticamente.

### Cloudflare Pages

- Build command: `npm run build`
- Build output directory: `build`
- Crie `build/_redirects` com: `/* /200.html 200`

## Estrutura do projeto

```
src/
├── lib/
│   ├── components/editor/   ← Componentes do editor (UI)
│   │   ├── EditorShell.svelte
│   │   ├── Sidebar.svelte
│   │   ├── PhotoGallery.svelte
│   │   ├── BatchActions.svelte
│   │   ├── SlotNavigator.svelte
│   │   ├── CanvasStage.svelte
│   │   ├── CaptionEditor.svelte
│   │   ├── BottomBar.svelte
│   │   ├── ConfirmDialog.svelte
│   │   ├── SidebarSection.svelte
│   │   └── index.ts
│   └── editor/              ← Lógica de domínio (store, tipos, utils)
│       ├── layoutStore.svelte.ts
│       ├── template.ts
│       ├── types.ts
│       ├── units.ts
│       ├── id.ts
│       └── twemoji.ts
├── routes/
│   ├── +page.svelte         ← Tela de entrada (código do pedido)
│   └── editor/[id]/
│       └── +page.svelte     ← Editor principal
└── app.html
```

## Scripts disponíveis

| Comando | Descrição |
|---------|-----------|
| `npm run dev` | Servidor de desenvolvimento |
| `npm run build` | Build estático para produção |
| `npm run preview` | Preview do build local |
| `npm run check` | Type-check com svelte-check |
| `npm run lint` | Lint com ESLint + Prettier |
| `npm run format` | Formatar código com Prettier |
