// Twemoji CDN PNG pattern: https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/72x72/1f60d.png

function toCodePoints(str: string): string {
	const cps: number[] = [];
	for (const ch of str) cps.push(ch.codePointAt(0)!);
	return cps.map((cp) => cp.toString(16)).join('-');
}

export function twemojiPngUrl(emoji: string): string {
	const hex = toCodePoints(emoji);
	return `https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/72x72/${hex}.png`;
}
