export function uuid(): string {
	if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) return crypto.randomUUID();
	return `id_${Math.random().toString(16).slice(2)}_${Date.now()}`;
}
