const fs = require('fs');
const path = require('path');

function resolvePlaywrightRoot() {
	const candidates = [];

	if (process.env.PLAYWRIGHT_ROOT) {
		candidates.push(process.env.PLAYWRIGHT_ROOT);
	}

	let dir = __dirname;
	for (let i = 0; i < 6; i++) {
		candidates.push(path.join(dir, 'node_modules', 'playwright'));
		dir = path.resolve(dir, '..');
	}

	return [...new Set(candidates)].find((candidate) => candidate && fs.existsSync(candidate)) || '';
}

const playwrightRoot = resolvePlaywrightRoot();
if (playwrightRoot) {
	module.exports = require(playwrightRoot);
	return;
}

try {
	module.exports = require('playwright');
} catch (error) {
	throw new Error(
		'Playwright install not found. Set PLAYWRIGHT_ROOT or install playwright in a reachable node_modules directory.'
	);
}
