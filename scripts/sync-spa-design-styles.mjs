#!/usr/bin/env node
/**
 * Copy shared design-system CSS into the SPA tree.
 * Source of truth: wp/mu-plugins/wchs-design-system/assets/
 * Git does not store symlinks here (breaks Linux CI checkout).
 */
import { copyFileSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const pairs = [
	['wp/mu-plugins/wchs-design-system/assets/tokens.css', 'spa/src/lib/styles/tokens.css'],
	['wp/mu-plugins/wchs-design-system/assets/header.css', 'spa/src/lib/styles/header.css'],
];

for (const [fromRel, toRel] of pairs) {
	const from = join(root, fromRel);
	const to = join(root, toRel);
	mkdirSync(dirname(to), { recursive: true });
	copyFileSync(from, to);
}

console.log('Synced tokens.css + header.css → spa/src/lib/styles/');
