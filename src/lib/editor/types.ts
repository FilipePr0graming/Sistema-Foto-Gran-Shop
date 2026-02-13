export type GridType = '3x3' | '2x3';

export type UUID = string;

export type ProductTemplate = {
	id: string;
	name: string;
	unit: 'mm';
	dpi: number;
	stage: {
		widthMm: number;
		heightMm: number;
	};
	grid: {
		type: GridType;
		rows: number;
		cols: number;
		gapMm: number;
		marginMm: number;
		cellWidthMm: number;
		cellHeightMm: number;
	};
};

export type LayoutSlot = {
	id: UUID;
	xMm: number;
	yMm: number;
	widthMm: number;
	heightMm: number;
};

export type PhotoAsset = {
	id: UUID;
	name?: string;
	src: string;
};

export type PhotoNode = {
	id: UUID;
	type: 'photo';
	origin: 'center';
	slotId: UUID;
	assetId: UUID;
	xMm: number;
	yMm: number;
	rotationDeg: number;
	scale: number;
};

export type TextNode = {
	id: UUID;
	type: 'text';
	origin: 'topleft';
	slotId: UUID;
	text: string;
	xMm: number;
	yMm: number;
	rotationDeg: number;
	fontFamily: string;
	fontSizeMm: number;
	fill: string;
	fontStyle?: 'normal' | 'italic' | 'bold' | 'bold italic';
};

export type EmojiNode = {
	id: UUID;
	type: 'emoji';
	origin: 'center';
	slotId: UUID;
	emoji: string;
	xMm: number;
	yMm: number;
	rotationDeg: number;
	sizeMm: number;
};

export type AnyNode = PhotoNode | TextNode | EmojiNode;

export type LayoutJSON = {
	version: 1;
	unit: 'mm';
	dpi: number;
	template: ProductTemplate;
	slots: LayoutSlot[];
	assets: {
		photos: PhotoAsset[];
	};
	nodes: AnyNode[];
	selected?: {
		slotId?: UUID;
		nodeId?: UUID;
	};
};
