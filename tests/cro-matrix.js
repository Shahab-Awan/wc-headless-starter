#!/usr/bin/env node
/**
 * CRO matrix test.
 *
 * Verifies every upsell / cross-sell / tier-pricing mechanism that the
 * SPA can consume end-to-end through the Store API. Covers:
 *
 *  1. Product endpoint exposes wchs_cro.{regular_price, tier_type, tiers, cross_sell_ids}
 *  2. Cart item extension returns correct savings_per_unit / savings_line_total /
 *     savings_pct for qty 1,2,3,4,7,8 across both 'fixed' and 'percentage' rule types
 *  3. Next-tier prompt appears when qty is below a higher tier and disappears at the top
 *  4. Cart-level total_savings sums line savings across multiple items
 *  5. Cart-level cross_sell_ids unions items and excludes products already in the cart
 *  6. Switching a product's rule type at runtime is reflected on the next /cart GET
 *  7. Free-cart (zero items) returns a valid empty-ish wchs_cro block
 *
 * Runs against localhost WP + fresh cart token per test to stay isolated.
 */

const WP = 'http://localhost:8099';
const SPA_ORIGIN = 'http://localhost:5175';

let pass = 0;
let fail = 0;
const failures = [];

function ok(label) { pass++; console.log(`  \x1b[32m✓\x1b[0m ${label}`); }
function bad(label, detail) {
	fail++;
	failures.push({ label, detail });
	console.log(`  \x1b[31m✗\x1b[0m ${label}`);
	if (detail !== undefined) console.log(`      ${JSON.stringify(detail)}`);
}
function eq(actual, expected, label) {
	if (JSON.stringify(actual) === JSON.stringify(expected)) ok(label);
	else bad(label, `expected ${JSON.stringify(expected)}, got ${JSON.stringify(actual)}`);
}
function truthy(cond, label, detail) { if (cond) ok(label); else bad(label, detail); }
function section(title) { console.log(`\n\x1b[36m== ${title} ==\x1b[0m`); }

/**
 * Call a docker-wpcli eval to mutate tier rules on a product. Blocks until
 * the mutation lands. We use eval-string (single-line PHP) to avoid temp
 * files.
 */
async function setTiers(productId, type, rules) {
	const { execSync } = require('child_process');
	const phpRules = Object.entries(rules)
		.map(([qty, val]) => `${qty}=>${val}`)
		.join(',');
	const metaKey = type === 'fixed' ? '_fixed_price_rules' : '_percentage_price_rules';
	const otherKey = type === 'fixed' ? '_percentage_price_rules' : '_fixed_price_rules';
	const script = `update_post_meta(${productId}, '_tiered_price_rules_type', '${type}'); update_post_meta(${productId}, '${metaKey}', [${phpRules}]); update_post_meta(${productId}, '${otherKey}', []);`;
	execSync(
		`docker exec wchs-wpcli wp eval "${script}"`,
		{ stdio: 'pipe' }
	);
}

async function freshCart() {
	const r = await fetch(`${WP}/wp-json/wc/store/v1/cart`, {
		headers: { origin: SPA_ORIGIN }
	});
	return {
		cartToken: r.headers.get('Cart-Token'),
		nonce: r.headers.get('Nonce')
	};
}

async function cartGet(session) {
	const r = await fetch(`${WP}/wp-json/wc/store/v1/cart`, {
		headers: {
			'Cart-Token': session.cartToken,
			Nonce: session.nonce,
			origin: SPA_ORIGIN
		}
	});
	return r.json();
}

async function cartAdd(session, productId, qty) {
	const r = await fetch(`${WP}/wp-json/wc/store/v1/cart/add-item`, {
		method: 'POST',
		headers: {
			'Cart-Token': session.cartToken,
			Nonce: session.nonce,
			origin: SPA_ORIGIN,
			'content-type': 'application/json'
		},
		body: JSON.stringify({ id: productId, quantity: qty })
	});
	return r.json();
}

async function cartUpdate(session, key, qty) {
	const r = await fetch(`${WP}/wp-json/wc/store/v1/cart/update-item`, {
		method: 'POST',
		headers: {
			'Cart-Token': session.cartToken,
			Nonce: session.nonce,
			origin: SPA_ORIGIN,
			'content-type': 'application/json'
		},
		body: JSON.stringify({ key, quantity: qty })
	});
	return r.json();
}

// ---------- TESTS ----------

async function test1_product_endpoint_cro_block() {
	section('1. Product endpoint surfaces wchs_cro block');

	// Set percentage tiers on product 23 for this test
	await setTiers(23, 'percentage', { 2: 5, 4: 10, 8: 15 });

	const p = await (await fetch(`${WP}/wp-json/wc/store/v1/products/23`)).json();
	const cro = p.extensions?.wchs_cro;

	truthy(!!cro, 'extensions.wchs_cro present', p.extensions);
	eq(cro?.regular_price, 3900, 'regular_price=3900 minor units');
	eq(cro?.tier_type, 'percentage', 'tier_type=percentage');
	truthy(Array.isArray(cro?.tiers) && cro.tiers.length === 3, '3 tiers returned', cro?.tiers?.length);
	eq(cro?.tiers?.[0]?.min_qty, 2, 'tier[0].min_qty=2');
	eq(cro?.tiers?.[0]?.unit_price, 3705, 'tier[0].unit_price=3705');
	eq(cro?.tiers?.[0]?.savings_per_unit, 195, 'tier[0].savings_per_unit=195');
	eq(cro?.tiers?.[0]?.savings_pct, 5, 'tier[0].savings_pct=5');
	eq(cro?.tiers?.[2]?.unit_price, 3315, 'tier[2].unit_price=3315');
	truthy(Array.isArray(cro?.cross_sell_ids) && cro.cross_sell_ids.length === 3, 'cross_sell_ids has 3 entries', cro?.cross_sell_ids);
}

async function test2_cart_item_savings_percentage() {
	section('2. Cart item savings (percentage tiers)');

	await setTiers(23, 'percentage', { 2: 5, 4: 10, 8: 15 });

	const expected = {
		1: { unit: 3900, save: 0, pct: 0 },
		2: { unit: 3705, save: 195, pct: 5 },
		3: { unit: 3705, save: 195, pct: 5 },  // still in tier 2
		4: { unit: 3510, save: 390, pct: 10 },
		7: { unit: 3510, save: 390, pct: 10 },
		8: { unit: 3315, save: 585, pct: 15 }
	};

	for (const [qty, exp] of Object.entries(expected)) {
		const q = Number(qty);
		const s = await freshCart();
		await cartAdd(s, 23, q);
		const c = await cartGet(s);
		const it = c.items[0];
		const cro = it?.extensions?.wchs_cro;
		truthy(!!cro, `qty=${q}: cart item has wchs_cro`, it?.extensions);
		eq(cro?.regular_unit_price, 3900, `qty=${q}: regular_unit_price=3900`);
		eq(cro?.effective_unit_price, exp.unit, `qty=${q}: effective_unit_price=${exp.unit}`);
		eq(cro?.savings_per_unit, exp.save, `qty=${q}: savings_per_unit=${exp.save}`);
		eq(cro?.savings_line_total, exp.save * q, `qty=${q}: savings_line_total=${exp.save * q}`);
		eq(cro?.savings_pct, exp.pct, `qty=${q}: savings_pct=${exp.pct}`);
	}
}

async function test3_cart_item_savings_fixed() {
	section('3. Cart item savings (fixed tiers)');

	// Fixed: $37.05/$35.10/$33.15 at qty 2/4/8
	await setTiers(23, 'fixed', { 2: 37.05, 4: 35.10, 8: 33.15 });

	const cases = [
		{ qty: 1, unit: 3900 },
		{ qty: 2, unit: 3705 },
		{ qty: 4, unit: 3510 },
		{ qty: 8, unit: 3315 }
	];
	for (const c of cases) {
		const s = await freshCart();
		await cartAdd(s, 23, c.qty);
		const cart = await cartGet(s);
		const cro = cart.items[0]?.extensions?.wchs_cro;
		eq(cro?.effective_unit_price, c.unit, `fixed qty=${c.qty}: unit=${c.unit}`);
		eq(cro?.savings_line_total, (3900 - c.unit) * c.qty, `fixed qty=${c.qty}: savings_line_total`);
	}
}

async function test4_next_tier_prompt() {
	section('4. Next tier prompt logic');

	await setTiers(23, 'percentage', { 2: 5, 4: 10, 8: 15 });

	// qty=1 → should suggest tier at qty=2
	{
		const s = await freshCart();
		await cartAdd(s, 23, 1);
		const it = (await cartGet(s)).items[0];
		const nt = it?.extensions?.wchs_cro?.next_tier;
		truthy(!!nt, 'qty=1 has next_tier', nt);
		eq(nt?.qty_needed, 1, 'qty=1 → qty_needed=1 (need 1 more for tier 2)');
		eq(nt?.next_min_qty, 2, 'qty=1 → next_min_qty=2');
		eq(nt?.next_savings_pct, 5, 'qty=1 → next_savings_pct=5');
	}

	// qty=3 → should suggest tier at qty=4
	{
		const s = await freshCart();
		await cartAdd(s, 23, 3);
		const it = (await cartGet(s)).items[0];
		const nt = it?.extensions?.wchs_cro?.next_tier;
		eq(nt?.qty_needed, 1, 'qty=3 → qty_needed=1');
		eq(nt?.next_min_qty, 4, 'qty=3 → next_min_qty=4');
	}

	// qty=8 → no next tier (already at highest)
	{
		const s = await freshCart();
		await cartAdd(s, 23, 8);
		const it = (await cartGet(s)).items[0];
		const nt = it?.extensions?.wchs_cro?.next_tier;
		eq(nt, null, 'qty=8 at top tier → next_tier=null');
	}
}

async function test5_cart_total_savings() {
	section('5. Cart total_savings aggregation');

	await setTiers(23, 'percentage', { 2: 5, 4: 10, 8: 15 });
	await setTiers(22, 'percentage', { 2: 5, 4: 10, 8: 15 });

	const s = await freshCart();
	await cartAdd(s, 23, 4); // Product 23: 4 × $3.90 savings = $15.60
	await cartAdd(s, 22, 2); // Product 22: $89 → $84.55 × 2 = $8.90
	const c = await cartGet(s);
	const cro = c.extensions?.wchs_cro;
	truthy(!!cro, 'cart has wchs_cro', c.extensions);

	// 4 × 390 (product 23 @ 10%) + 2 × 445 (product 22 @ 5%) = 1560 + 890 = 2450
	eq(cro?.total_savings, 1560 + 890, 'total_savings = 2450 cents');
}

async function test6_crosssells_exclude_cart() {
	section('6. Cart cross_sell_ids exclude items already in the cart');

	const s = await freshCart();
	// Add product 23. Its cross-sells are [22, 20, 21].
	await cartAdd(s, 23, 1);
	// Also add id=22 which is one of the cross-sells — it should drop out.
	await cartAdd(s, 22, 1);
	const c = await cartGet(s);
	const xsellIds = c.extensions?.wchs_cro?.cross_sell_ids || [];
	truthy(!xsellIds.includes(22), '22 removed (in cart)', xsellIds);
	truthy(!xsellIds.includes(23), '23 removed (in cart)', xsellIds);
	truthy(xsellIds.length > 0, 'some cross-sells remain', xsellIds);
}

async function test7_empty_cart_cro_block() {
	section('7. Empty cart wchs_cro block is valid');

	const s = await freshCart();
	const c = await cartGet(s);
	const cro = c.extensions?.wchs_cro;
	truthy(!!cro, 'empty cart has wchs_cro', c.extensions);
	eq(cro?.total_savings, 0, 'empty cart total_savings=0');
	eq(cro?.cross_sell_ids, [], 'empty cart cross_sell_ids=[]');
}

async function test8_runtime_type_switch() {
	section('8. Runtime tier type switch is reflected on next /cart GET');

	await setTiers(23, 'fixed', { 2: 36, 4: 32, 8: 28 });
	const s = await freshCart();
	await cartAdd(s, 23, 2);
	let c = await cartGet(s);
	eq(c.items[0]?.extensions?.wchs_cro?.effective_unit_price, 3600, 'fixed tier @ qty=2 = 3600');

	await setTiers(23, 'percentage', { 2: 50, 4: 10, 8: 15 }); // massive 50% for detectability
	c = await cartGet(s);
	eq(c.items[0]?.extensions?.wchs_cro?.effective_unit_price, 1950, 'after switch to percentage 50% = 1950');

	// Clean up — reset product 23 to seeded fixed defaults
	await setTiers(23, 'fixed', { 2: 37.05, 4: 35.10, 8: 33.15 });
}

async function main() {
	const t0 = Date.now();
	console.log('CRO matrix test suite\n');
	try {
		await test1_product_endpoint_cro_block();
		await test2_cart_item_savings_percentage();
		await test3_cart_item_savings_fixed();
		await test4_next_tier_prompt();
		await test5_cart_total_savings();
		await test6_crosssells_exclude_cart();
		await test7_empty_cart_cro_block();
		await test8_runtime_type_switch();
	} catch (err) {
		console.error('\n\x1b[31munhandled\x1b[0m', err);
		fail++;
	}
	const dur = ((Date.now() - t0) / 1000).toFixed(1);
	console.log(`\n\x1b[36m== result ==\x1b[0m`);
	console.log(`${pass} passed  ${fail} failed  in ${dur}s`);
	if (fail > 0) {
		console.log('\nfailures:');
		for (const f of failures) console.log(`  - ${f.label}: ${JSON.stringify(f.detail)}`);
		process.exit(1);
	}
}

main();
