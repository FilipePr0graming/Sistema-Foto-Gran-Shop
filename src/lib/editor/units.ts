export function mmToPx(mm: number, dpi: number): number {
	return (mm / 25.4) * dpi;
}

export function pxToMm(px: number, dpi: number): number {
	return (px / dpi) * 25.4;
}

export function round3(n: number): number {
	return Math.round(n * 1000) / 1000;
}
