import type { GridType, ProductTemplate } from './types';

export const DEFAULT_DPI = 300;

// Polaroid-like example: 88mm x 107mm (approx). You can change later.
export function createTemplate(grid: GridType, dpi = DEFAULT_DPI): ProductTemplate {
	const stage = { widthMm: 88, heightMm: 107 };
	const marginMm = 6;
	const gapMm = 3;

	let rows = 3;
	let cols = 3;
	if (grid === '2x3') {
		rows = 2;
		cols = 3;
	}

	const usableW = stage.widthMm - marginMm * 2 - gapMm * (cols - 1);
	const usableH = stage.heightMm - marginMm * 2 - gapMm * (rows - 1);
	const cellWidthMm = usableW / cols;
	const cellHeightMm = usableH / rows;

	return {
		id: 'polaroid',
		name: 'Polaroid',
		unit: 'mm',
		dpi,
		stage,
		grid: {
			type: grid,
			rows,
			cols,
			gapMm,
			marginMm,
			cellWidthMm,
			cellHeightMm
		}
	};
}
