/**
 * CSS color parser for WebGL uniform uploads.
 *
 * Handles hex, rgb/rgba (with comma or space separators), and oklch — any
 * of which browsers may return from getComputedStyle depending on version.
 *
 * Takes an explicit fallback so a parse failure can't silently produce
 * an all-black shader (which was a bug in the previous inline parser).
 *
 * Shared color helpers for resolving readable foreground/background pairs.
 */

function clamp01(value: number): number {
	return Math.min(1, Math.max(0, value));
}

function srgbFromLinear(value: number): number {
	const clamped = clamp01(value);
	if (clamped <= 0.0031308) return 12.92 * clamped;
	return 1.055 * Math.pow(clamped, 1 / 2.4) - 0.055;
}

function parseHexColor(css: string): [number, number, number] | null {
	const value = css.trim();
	if (!value.startsWith('#')) return null;
	const hex = value.slice(1);
	if (hex.length === 3) {
		const r = parseInt(hex[0] + hex[0], 16);
		const g = parseInt(hex[1] + hex[1], 16);
		const b = parseInt(hex[2] + hex[2], 16);
		return [r / 255, g / 255, b / 255];
	}
	if (hex.length === 6) {
		const r = parseInt(hex.slice(0, 2), 16);
		const g = parseInt(hex.slice(2, 4), 16);
		const b = parseInt(hex.slice(4, 6), 16);
		return [r / 255, g / 255, b / 255];
	}
	return null;
}

function parseRgbColor(css: string): [number, number, number] | null {
	const match = css.match(/rgba?\(([^)]+)\)/i);
	if (!match) return null;
	const channels = match[1]
		.split(/[,\s/]+/)
		.map((part) => part.trim())
		.filter(Boolean)
		.slice(0, 3);
	if (channels.length < 3) return null;

	const parsed = channels.map((channel) => {
		if (channel.endsWith('%')) return (Number(channel.slice(0, -1)) / 100) * 255;
		return Number(channel);
	});
	if (parsed.some((value) => Number.isNaN(value))) return null;
	return [parsed[0] / 255, parsed[1] / 255, parsed[2] / 255];
}

function parseOklchColor(css: string): [number, number, number] | null {
	const match = css.match(/oklch\(([^)]+)\)/i);
	if (!match) return null;

	const parts = match[1]
		.replace(/\//g, ' ')
		.split(/\s+/)
		.map((part) => part.trim())
		.filter(Boolean);

	if (parts.length < 3) return null;

	const parseLightness = (value: string) =>
		value.endsWith('%') ? Number(value.slice(0, -1)) / 100 : Number(value);

	const lightness = parseLightness(parts[0]);
	const chroma = Number(parts[1]);
	const hue = Number(parts[2]);
	if ([lightness, chroma, hue].some((value) => Number.isNaN(value))) return null;

	const radians = (hue * Math.PI) / 180;
	const a = chroma * Math.cos(radians);
	const b = chroma * Math.sin(radians);

	const l_ = lightness + 0.3963377774 * a + 0.2158037573 * b;
	const m_ = lightness - 0.1055613458 * a - 0.0638541728 * b;
	const s_ = lightness - 0.0894841775 * a - 1.291485548 * b;

	const l = l_ ** 3;
	const m = m_ ** 3;
	const s = s_ ** 3;

	const linearR = 4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s;
	const linearG = -1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s;
	const linearB = -0.0041960863 * l - 0.7034186147 * m + 1.707614701 * s;

	return [
		clamp01(srgbFromLinear(linearR)),
		clamp01(srgbFromLinear(linearG)),
		clamp01(srgbFromLinear(linearB)),
	];
}

export function parseCssColor(css: string, fallback: [number, number, number]): [number, number, number] {
	const value = css.trim();
	if (!value) return fallback;

	return parseHexColor(value)
		?? parseRgbColor(value)
		?? parseOklchColor(value)
		?? fallback;
}
