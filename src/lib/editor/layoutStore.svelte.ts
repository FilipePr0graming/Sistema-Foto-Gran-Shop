import { writable } from 'svelte/store';
import { uuid } from './id';
import { createTemplate, DEFAULT_DPI } from './template';
import { round3 } from './units';
import type { AnyNode, GridType, LayoutJSON, LayoutSlot, PhotoAsset } from './types';

function buildSlots(json: LayoutJSON): LayoutSlot[] {
	const { marginMm, gapMm, rows, cols, cellWidthMm, cellHeightMm } = json.template.grid;
	const slots: LayoutSlot[] = [];

	for (let r = 0; r < rows; r++) {
		for (let c = 0; c < cols; c++) {
			const xMm = marginMm + c * (cellWidthMm + gapMm);
			const yMm = marginMm + r * (cellHeightMm + gapMm);
			slots.push({
				id: uuid(),
				xMm: round3(xMm),
				yMm: round3(yMm),
				widthMm: round3(cellWidthMm),
				heightMm: round3(cellHeightMm)
			});
		}
	}
	return slots;
}

function createInitialLayout(): LayoutJSON {
	const template = createTemplate('3x3', DEFAULT_DPI);
	const base: LayoutJSON = {
		version: 1,
		unit: 'mm',
		dpi: template.dpi,
		template,
		slots: [],
		assets: { photos: [] },
		nodes: [],
		selected: {}
	};
	base.slots = buildSlots(base);

	return base;
}

function normalize(json: LayoutJSON): LayoutJSON {
	// Enforce invariants for backend stability
	json.version = 1;
	json.unit = 'mm';
	json.dpi = json.dpi || json.template?.dpi || DEFAULT_DPI;
	json.template.dpi = json.dpi;

	// Recompute grid cell sizes if missing
	const g = json.template.grid;
	if (!g.rows || !g.cols) {
		const type: GridType = g.type || '3x3';
		json.template = createTemplate(type, json.dpi);
	}

	if (!Array.isArray(json.slots) || json.slots.length === 0) {
		json.slots = buildSlots(json);
	}

	if (!json.assets) json.assets = { photos: [] };
	if (!Array.isArray(json.assets.photos)) json.assets.photos = [];
	if (!Array.isArray(json.nodes)) json.nodes = [];

	// Back-compat: ensure origin exists so backend can apply coordinates without guessing
	for (const n of json.nodes as any[]) {
		if (!n || typeof n !== 'object') continue;
		if (n.type === 'text' && !n.origin) n.origin = 'topleft';
		if (n.type === 'photo' && !n.origin) n.origin = 'center';
		if (n.type === 'emoji' && !n.origin) n.origin = 'center';
	}
	if (!json.selected) json.selected = {};
	return json;
}

function clone<T>(v: T): T {
	return JSON.parse(JSON.stringify(v)) as T;
}

export const layoutStore = writable<LayoutJSON>(createInitialLayout());

export function exportLayout(): LayoutJSON {
	let v!: LayoutJSON;
	layoutStore.subscribe((x) => (v = x))();
	return clone(v);
}

export function loadLayout(json: LayoutJSON | string): void {
	const parsed: LayoutJSON = typeof json === 'string' ? (JSON.parse(json) as LayoutJSON) : json;
	layoutStore.set(normalize(clone(parsed)));
}

export function setGrid(type: GridType): void {
	layoutStore.update((curr) => {
		const next = clone(curr);
		next.template = createTemplate(type, next.dpi);
		next.slots = buildSlots(next);
		// keep nodes but clamp to their slot bounds (simple clamp)
		for (const n of next.nodes) {
			const s = next.slots.find((x) => x.id === n.slotId);
			if (!s) {
				n.slotId = next.slots[0]?.id ?? n.slotId;
				continue;
			}
			n.xMm = round3(Math.max(s.xMm, Math.min(n.xMm, s.xMm + s.widthMm)));
			n.yMm = round3(Math.max(s.yMm, Math.min(n.yMm, s.yMm + s.heightMm)));
		}
		return next;
	});
}

export function upsertPhotoAsset(asset: Omit<PhotoAsset, 'id'> & { id?: string }): string {
	const id = asset.id ?? uuid();
	layoutStore.update((curr) => {
		const next = clone(curr);
		const idx = next.assets.photos.findIndex((p) => p.id === id);
		const toSave: PhotoAsset = { id, name: asset.name, src: asset.src };
		if (idx >= 0) next.assets.photos[idx] = toSave;
		else next.assets.photos.push(toSave);
		return next;
	});
	return id;
}

export function setNodes(nodes: AnyNode[]): void {
	layoutStore.update((curr) => {
		const next = clone(curr);
		next.nodes = clone(nodes);
		return next;
	});
}

export function patchNode(nodeId: string, patch: Partial<AnyNode>): void {
	layoutStore.update((curr) => {
		const next = clone(curr);
		const idx = next.nodes.findIndex((n) => n.id === nodeId);
		if (idx < 0) return curr;
		next.nodes[idx] = { ...(next.nodes[idx] as any), ...(patch as any) };
		return next;
	});
}

export function selectNode(slotId?: string, nodeId?: string): void {
	layoutStore.update((curr) => ({ ...curr, selected: { slotId, nodeId } }));
}
