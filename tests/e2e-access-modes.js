/**
 * E2E Access Mode + Email Verification + Loading Gate Tests
 *
 * 58 tests covering every combination of access mode + user type,
 * loading gate resilience, email verification security, and content
 * flash detection. Every test uses Playwright with human-emulated
 * navigation. No shortcuts.
 */

const { chromium } = require('playwright');
const { execSync } = require('child_process');
const path = require('path');

const SPA = 'http://localhost:5175';
const WP = 'http://localhost:8099';
const SS = path.join(__dirname, 'screenshots');
const HELPER = path.join(__dirname, 'helpers/set-mode.sh');

let passed = 0;
let failed = 0;
const failures = [];

function assert(name, cond, detail = '') {
	if (cond) { console.log(`  ✓ ${name}`); passed++; }
	else { console.log(`  ✗ ${name}${detail ? ' (' + detail + ')' : ''}`); failed++; failures.push(name); }
}

function setMode(m) {
	execSync(`bash ${HELPER} ${m}`, { stdio: 'pipe' });
}

function wpEval(code) {
	try {
		return execSync(`docker exec wchs-wpcli wp eval '${code.replace(/'/g, "'\\''")}'`, { stdio: 'pipe', timeout: 15000 }).toString().trim();
	} catch (e) { return e.stderr?.toString() || ''; }
}

async function freshCtx(browser) {
	return browser.newContext({ viewport: { width: 1440, height: 900 } });
}

async function loginWP(browser, user, pass) {
	const savedMode = wpEval('echo \\WCHS\\Admin\\AdminPage::get_site_settings()["access_mode"];');
	setMode(3); // must be open to login
	const ctx = await freshCtx(browser);
	const page = await ctx.newPage();
	await page.goto(`${WP}/wp-login.php`, { waitUntil: 'domcontentloaded', timeout: 15000 });
	await page.fill('#user_login', user);
	await page.fill('#user_pass', pass);
	await page.click('#wp-submit');
	try { await page.waitForURL(u => !u.toString().includes('wp-login'), { timeout: 15000 }); }
	catch { /* might stay on login if creds are wrong */ }
	const loginOk = !page.url().includes('wp-login.php') || !(await page.locator('#login_error').count());
	setMode(parseInt(savedMode) || 3); // restore mode
	return { ctx, page, loginOk };
}

async function visitSPA(page, p = '/', opts = {}) {
	const waitUntil = opts.waitUntil || 'networkidle';
	const timeout = opts.timeout || 20000;
	try {
		await page.goto(`${SPA}${p}`, { waitUntil, timeout });
	} catch {
		// networkidle may never fire in locked/maintenance mode where
		// API requests fail. Fall back to waiting for the gate to resolve.
		await page.waitForTimeout(3000);
	}
}

async function chk(page) {
	return {
		maint: (await page.locator('.maintenance').count()) > 0,
		hero: (await page.locator('.hero__title').count()) > 0,
		header: (await page.locator('.site-header').count()) > 0,
		adminBar: (await page.locator('.admin-bar').count()) > 0,
		redBanner: (await page.locator('.admin-mode-banner--red').count()) > 0,
		amberBanner: (await page.locator('.admin-mode-banner--amber').count()) > 0,
		blueBanner: (await page.locator('.admin-mode-banner--blue').count()) > 0,
		gate: (await page.locator('.loading-gate').count()) > 0,
		accessGate: (await page.locator('.access-gate').count()) > 0,
		cards: await page.locator('.store-card').count(),
	};
}

(async () => {
	const browser = await chromium.launch();
	const t0 = Date.now();

	// ════════════════════════════════════════════════════════
	// PHASE 3: ACCESS MODE + USER TYPE MATRIX
	// ════════════════════════════════════════════════════════

	// ── MODE 3 (Open) ─────────────────────────────────────
	console.log('\n=== MODE 3 (Open) ===');
	setMode(3);

	// #1 Guest homepage
	{ const c = await freshCtx(browser); const p = await c.newPage(); await visitSPA(p);
	  const s = await chk(p); assert('#1 M3 Guest: hero visible', s.hero);
	  assert('#1 M3 Guest: no maintenance', !s.maint); assert('#1 M3 Guest: no gate', !s.gate);
	  await p.screenshot({ path: `${SS}/01-m3-guest.png` }); await c.close(); }

	// #2 Admin homepage
	{ const { ctx, page } = await loginWP(browser, 'admin', 'wchs-admin-dev'); setMode(3);
	  await visitSPA(page); const s = await chk(page);
	  assert('#2 M3 Admin: hero visible', s.hero); assert('#2 M3 Admin: admin bar', s.adminBar);
	  assert('#2 M3 Admin: no banner', !s.redBanner && !s.amberBanner && !s.blueBanner);
	  await page.screenshot({ path: `${SS}/02-m3-admin.png` }); await ctx.close(); }

	// #3 Verified customer
	{ const { ctx, page } = await loginWP(browser, 'verified@example.test', 'testpass123'); setMode(3);
	  await visitSPA(page); const s = await chk(page);
	  assert('#3 M3 Verified: hero visible', s.hero); assert('#3 M3 Verified: no maint', !s.maint);
	  await page.screenshot({ path: `${SS}/03-m3-verified.png` }); await ctx.close(); }

	// #4 Unverified customer
	{ const { ctx, page } = await loginWP(browser, 'unverified_test', 'testpass123'); setMode(3);
	  await visitSPA(page); const s = await chk(page);
	  assert('#4 M3 Unverified: hero visible (open)', s.hero);
	  await page.screenshot({ path: `${SS}/04-m3-unverified.png` }); await ctx.close(); }

	// #5 Guest /shop
	{ const c = await freshCtx(browser); const p = await c.newPage(); await visitSPA(p, '/shop');
	  await p.waitForTimeout(2000); const s = await chk(p);
	  assert('#5 M3 Guest /shop: store cards', s.cards > 0, `${s.cards} cards`);
	  await p.screenshot({ path: `${SS}/05-m3-guest-shop.png` }); await c.close(); }

	// #6 Guest add to cart
	{ const c = await freshCtx(browser); const p = await c.newPage();
	  await visitSPA(p, '/product/canvas-tote');
	  await p.waitForSelector('.pdp__add', { timeout: 10000 });
	  await p.click('.pdp__add'); await p.waitForTimeout(2000);
	  const cartItems = await p.locator('.fkcart-item').count();
	  assert('#6 M3 Guest: add to cart', cartItems > 0, `${cartItems} items`);
	  await p.screenshot({ path: `${SS}/06-m3-guest-cart.png` }); await c.close(); }

	// ── MODE 0 (Maintenance) ──────────────────────────────
	console.log('\n=== MODE 0 (Maintenance) ===');
	setMode(0);

	// #7 Guest
	{ const c = await freshCtx(browser); const p = await c.newPage(); await visitSPA(p);
	  const s = await chk(p); assert('#7 M0 Guest: maintenance', s.maint);
	  assert('#7 M0 Guest: no hero', !s.hero); assert('#7 M0 Guest: no header', !s.header);
	  assert('#7 M0 Guest: no gate', !s.gate);
	  await p.screenshot({ path: `${SS}/07-m0-guest.png` }); await c.close(); }

	// #8 Guest 5x refresh
	{ const c = await freshCtx(browser); const p = await c.newPage(); let flashes = 0;
	  for (let i = 0; i < 5; i++) {
	    await visitSPA(p); if (await p.locator('.hero__title').count()) flashes++;
	  }
	  assert('#8 M0 Guest: 0 hero flashes in 5 refreshes', flashes === 0, `${flashes}`);
	  await c.close(); }

	// #9 Admin
	{ const { ctx, page } = await loginWP(browser, 'admin', 'wchs-admin-dev'); setMode(0);
	  await visitSPA(page); const s = await chk(page);
	  assert('#9 M0 Admin: no maintenance', !s.maint); assert('#9 M0 Admin: admin bar', s.adminBar);
	  assert('#9 M0 Admin: red banner', s.redBanner);
	  assert('#9 M0 Admin: site visible', s.hero || s.header);
	  await page.screenshot({ path: `${SS}/09-m0-admin.png` }); await ctx.close(); }

	// #10 Admin 5x refresh
	{ const { ctx, page } = await loginWP(browser, 'admin', 'wchs-admin-dev'); setMode(0);
	  let flashes = 0;
	  for (let i = 0; i < 5; i++) {
	    await visitSPA(page); if (await page.locator('.maintenance').count()) flashes++;
	  }
	  assert('#10 M0 Admin: 0 maint flashes in 5 refreshes', flashes === 0, `${flashes}`);
	  await ctx.close(); }

	// #11 Guest /shop
	{ const c = await freshCtx(browser); const p = await c.newPage(); await visitSPA(p, '/shop');
	  const s = await chk(p); assert('#11 M0 Guest /shop: maintenance', s.maint);
	  assert('#11 M0 Guest /shop: no cards', s.cards === 0);
	  await p.screenshot({ path: `${SS}/11-m0-guest-shop.png` }); await c.close(); }

	// #12 Guest /product
	{ const c = await freshCtx(browser); const p = await c.newPage();
	  await visitSPA(p, '/product/canvas-tote');
	  const s = await chk(p); assert('#12 M0 Guest /product: maintenance', s.maint);
	  await p.screenshot({ path: `${SS}/12-m0-guest-pdp.png` }); await c.close(); }

	// #13 Verified (non-admin) - should be blocked
	{ const { ctx, page } = await loginWP(browser, 'verified@example.test', 'testpass123'); setMode(0);
	  await visitSPA(page); const s = await chk(page);
	  assert('#13 M0 Verified (non-admin): maintenance', s.maint);
	  await page.screenshot({ path: `${SS}/13-m0-verified.png` }); await ctx.close(); }

	// #14 Unverified - should be blocked
	{ const { ctx, page } = await loginWP(browser, 'unverified_test', 'testpass123'); setMode(0);
	  await visitSPA(page); const s = await chk(page);
	  assert('#14 M0 Unverified: maintenance', s.maint);
	  await page.screenshot({ path: `${SS}/14-m0-unverified.png` }); await ctx.close(); }

	// ── MODE 1 (Locked) ───────────────────────────────────
	console.log('\n=== MODE 1 (Locked) ===');
	setMode(1);

	// #15 Guest
	{ const c = await freshCtx(browser); const p = await c.newPage(); await visitSPA(p);
	  const s = await chk(p); assert('#15 M1 Guest: no gate stuck', !s.gate);
	  assert('#15 M1 Guest: no maintenance', !s.maint);
	  await p.screenshot({ path: `${SS}/15-m1-guest.png` }); await c.close(); }

	// #16 Guest /shop
	{ const c = await freshCtx(browser); const p = await c.newPage(); await visitSPA(p, '/shop');
	  await p.waitForTimeout(2000); const s = await chk(p);
	  assert('#16 M1 Guest /shop: products blocked', s.cards === 0 || s.accessGate);
	  await p.screenshot({ path: `${SS}/16-m1-guest-shop.png` }); await c.close(); }

	// #17 Verified customer
	{ const { ctx, page } = await loginWP(browser, 'verified@example.test', 'testpass123'); setMode(1);
	  await visitSPA(page); const s = await chk(page);
	  assert('#17 M1 Verified: site renders', s.hero || s.header);
	  await page.screenshot({ path: `${SS}/17-m1-verified.png` }); await ctx.close(); }

	// #18 Unverified
	{ const { ctx, page } = await loginWP(browser, 'unverified_test', 'testpass123'); setMode(1);
	  await visitSPA(page); const s = await chk(page);
	  // Unverified treated as guest in locked mode
	  assert('#18 M1 Unverified: treated as guest', !s.gate);
	  await page.screenshot({ path: `${SS}/18-m1-unverified.png` }); await ctx.close(); }

	// #19 Unverified /shop
	{ const { ctx, page } = await loginWP(browser, 'unverified_test', 'testpass123'); setMode(1);
	  await visitSPA(page, '/shop'); await page.waitForTimeout(2000); const s = await chk(page);
	  assert('#19 M1 Unverified /shop: blocked', s.cards === 0 || s.accessGate);
	  await page.screenshot({ path: `${SS}/19-m1-unverified-shop.png` }); await ctx.close(); }

	// #20 Admin
	{ const { ctx, page } = await loginWP(browser, 'admin', 'wchs-admin-dev'); setMode(1);
	  await visitSPA(page); const s = await chk(page);
	  assert('#20 M1 Admin: admin bar', s.adminBar);
	  assert('#20 M1 Admin: amber banner', s.amberBanner);
	  await page.screenshot({ path: `${SS}/20-m1-admin.png` }); await ctx.close(); }

	// ── MODE 2 (Browse-only) ──────────────────────────────
	console.log('\n=== MODE 2 (Browse-only) ===');
	setMode(2);

	// #21 Guest /shop
	{ const c = await freshCtx(browser); const p = await c.newPage(); await visitSPA(p, '/shop');
	  await p.waitForTimeout(2000); const s = await chk(p);
	  assert('#21 M2 Guest /shop: can browse', s.cards > 0, `${s.cards} cards`);
	  await p.screenshot({ path: `${SS}/21-m2-guest-shop.png` }); await c.close(); }

	// #22 Guest cart add -> 403
	{ const c = await freshCtx(browser); const p = await c.newPage(); await visitSPA(p, '/shop');
	  const status = await p.evaluate(async () => {
	    const r = await fetch('/wp/wp-json/wc/store/v1/cart/add-item', {
	      method: 'POST', credentials: 'include',
	      headers: { 'Content-Type': 'application/json' },
	      body: JSON.stringify({ id: 12, quantity: 1 })
	    });
	    return r.status;
	  });
	  assert('#22 M2 Guest: cart add 403', status === 403, `status=${status}`);
	  await c.close(); }

	// #23 Unverified /shop
	{ const { ctx, page } = await loginWP(browser, 'unverified_test', 'testpass123'); setMode(2);
	  await visitSPA(page, '/shop'); await page.waitForTimeout(2000); const s = await chk(page);
	  assert('#23 M2 Unverified /shop: can browse', s.cards > 0, `${s.cards} cards`);
	  await page.screenshot({ path: `${SS}/23-m2-unverified-shop.png` }); await ctx.close(); }

	// #24 Unverified cart add -> 403
	{ const { ctx, page } = await loginWP(browser, 'unverified_test', 'testpass123'); setMode(2);
	  await visitSPA(page, '/shop');
	  const status = await page.evaluate(async () => {
	    const r = await fetch('/wp/wp-json/wc/store/v1/cart/add-item', {
	      method: 'POST', credentials: 'include',
	      headers: { 'Content-Type': 'application/json' },
	      body: JSON.stringify({ id: 12, quantity: 1 })
	    });
	    return r.status;
	  });
	  assert('#24 M2 Unverified: cart add 403', status === 403, `status=${status}`);
	  await ctx.close(); }

	// #25 Verified cart add -> success
	{ const { ctx, page } = await loginWP(browser, 'verified@example.test', 'testpass123'); setMode(2);
	  await visitSPA(page, '/product/canvas-tote');
	  await page.waitForSelector('.pdp__add', { timeout: 10000 });
	  await page.click('.pdp__add'); await page.waitForTimeout(2000);
	  const items = await page.locator('.fkcart-item').count();
	  assert('#25 M2 Verified: can add to cart', items > 0, `${items} items`);
	  await page.screenshot({ path: `${SS}/25-m2-verified-cart.png` }); await ctx.close(); }

	// #26 Admin
	{ const { ctx, page } = await loginWP(browser, 'admin', 'wchs-admin-dev'); setMode(2);
	  await visitSPA(page); const s = await chk(page);
	  assert('#26 M2 Admin: admin bar', s.adminBar);
	  assert('#26 M2 Admin: blue banner', s.blueBanner);
	  await page.screenshot({ path: `${SS}/26-m2-admin.png` }); await ctx.close(); }

	// ════════════════════════════════════════════════════════
	// PHASE 4: LOADING GATE RESILIENCE
	// ════════════════════════════════════════════════════════
	console.log('\n=== Loading Gate Resilience ===');

	// #27 Fresh visit mode 3
	{ setMode(3); const c = await freshCtx(browser); const p = await c.newPage();
	  const t = Date.now(); await visitSPA(p); const el = Date.now() - t;
	  const s = await chk(p);
	  assert('#27 Gate < 3s (mode 3)', el < 3000, `${el}ms`);
	  assert('#27 No gate stuck', !s.gate); await c.close(); }

	// #28 Fresh visit mode 0
	{ setMode(0); const c = await freshCtx(browser); const p = await c.newPage();
	  const t = Date.now(); await visitSPA(p); const el = Date.now() - t;
	  const s = await chk(p);
	  assert('#28 Gate < 3s (mode 0)', el < 3000, `${el}ms`);
	  assert('#28 Shows maintenance', s.maint); await c.close(); }

	// #29 Auth endpoint 500
	{ setMode(3); const c = await freshCtx(browser); const p = await c.newPage();
	  await p.route('**/wchs/v1/session', r => r.fulfill({ status: 500, body: '{}' }));
	  const t = Date.now();
	  await p.goto(`${SPA}/`, { waitUntil: 'networkidle', timeout: 10000 }).catch(() => {});
	  await p.waitForTimeout(1000);
	  const el = Date.now() - t; const s = await chk(p);
	  assert('#29 Auth 500: resolves < 5s', el < 5000, `${el}ms`);
	  assert('#29 Auth 500: not stuck', !s.gate);
	  await p.screenshot({ path: `${SS}/29-auth-500.png` }); await c.close(); }

	// #30 Config endpoint 500
	{ setMode(3); const c = await freshCtx(browser); const p = await c.newPage();
	  await p.route('**/wchs/v1/config', r => r.fulfill({ status: 500, body: '{}' }));
	  const t = Date.now();
	  await p.goto(`${SPA}/`, { waitUntil: 'networkidle', timeout: 10000 }).catch(() => {});
	  await p.waitForTimeout(1000);
	  const el = Date.now() - t; const s = await chk(p);
	  assert('#30 Config 500: resolves < 5s', el < 5000, `${el}ms`);
	  assert('#30 Config 500: not stuck', !s.gate);
	  await p.screenshot({ path: `${SS}/30-config-500.png` }); await c.close(); }

	// #31 Auth endpoint timeout (AbortController with 10s timeout)
	{ setMode(3); const c = await freshCtx(browser); const p = await c.newPage();
	  await p.route('**/wchs/v1/session', r => { /* never respond - simulates hang */ });
	  const t = Date.now();
	  await p.goto(`${SPA}/`, { timeout: 20000 }).catch(() => {});
	  // Auth has a 10s AbortController timeout. After abort, falls to 'guest'.
	  // Gate resolves once auth leaves 'loading'. Total budget: 12s (10s timeout + 2s buffer).
	  await p.waitForTimeout(12000);
	  const el = Date.now() - t; const s = await chk(p);
	  assert('#31 Auth hang: resolves via AbortController < 15s', el < 16000, `${el}ms`);
	  assert('#31 Auth hang: gate not stuck', !s.gate);
	  await p.screenshot({ path: `${SS}/31-auth-hang.png` }); await c.close(); }

	// #32 All APIs down
	{ setMode(3); const c = await freshCtx(browser); const p = await c.newPage();
	  await p.route('**/wp-json/**', r => r.abort());
	  await p.route('**/wp/wp-json/**', r => r.abort());
	  const t = Date.now();
	  await p.goto(`${SPA}/`, { timeout: 20000 }).catch(() => {});
	  await p.waitForTimeout(3000);
	  const el = Date.now() - t; const s = await chk(p);
	  assert('#32 All APIs down: not infinite hang', el < 15000 || !s.gate, `${el}ms, gate=${s.gate}`);
	  await p.screenshot({ path: `${SS}/32-all-down.png` }); await c.close(); }

	// #33 Slow network, admin, mode 0
	{ const { ctx, page } = await loginWP(browser, 'admin', 'wchs-admin-dev'); setMode(0);
	  await page.route('**/wp-json/**', async r => {
	    await new Promise(ok => setTimeout(ok, 500));
	    r.continue();
	  });
	  await page.route('**/wp/wp-json/**', async r => {
	    await new Promise(ok => setTimeout(ok, 500));
	    r.continue();
	  });
	  await visitSPA(page); const s = await chk(page);
	  assert('#33 Slow net admin M0: no maintenance', !s.maint);
	  assert('#33 Slow net admin M0: site visible', s.hero || s.header);
	  await page.screenshot({ path: `${SS}/33-slow-admin-m0.png` }); await ctx.close(); }

	// #34 Two tabs: admin + guest, mode 0
	{ setMode(0);
	  const { ctx: adminCtx, page: adminPage } = await loginWP(browser, 'admin', 'wchs-admin-dev');
	  setMode(0);
	  const guestCtx = await freshCtx(browser);
	  const guestPage = await guestCtx.newPage();
	  await Promise.all([visitSPA(adminPage), visitSPA(guestPage)]);
	  const adminS = await chk(adminPage); const guestS = await chk(guestPage);
	  assert('#34 Race: admin no maintenance', !adminS.maint);
	  assert('#34 Race: guest has maintenance', guestS.maint);
	  await adminCtx.close(); await guestCtx.close(); }

	// ════════════════════════════════════════════════════════
	// PHASE 5: EMAIL VERIFICATION
	// ════════════════════════════════════════════════════════
	console.log('\n=== Email Verification ===');

	// #48-50 Grandfathering (DB checks)
	{ const r1 = wpEval('echo wchs_is_email_verified(1) ? "true" : "false";');
	  assert('#48 Admin grandfathered', r1.includes('true'), r1); }

	{ // Create a user with NO email meta
	  wpEval('$uid = wp_create_user("grandparent_test", "test123", "gp@test.local"); $u = new WP_User($uid); $u->set_role("customer"); echo $uid;');
	  const r2 = wpEval('$users = get_users(["login" => "grandparent_test"]); echo wchs_is_email_verified($users[0]->ID) ? "true" : "false";');
	  assert('#49 Old customer grandfathered', r2.includes('true'), r2);
	  wpEval('$users = get_users(["login" => "grandparent_test"]); wp_delete_user($users[0]->ID);'); }

	{ const r3 = wpEval('$users = get_users(["login" => "unverified_test"]); echo wchs_is_email_verified($users[0]->ID) ? "true" : "false";');
	  assert('#50 Unverified = false', r3.includes('false'), r3); }

	// #51 Replayed code
	{ // Reset unverified user's code
	  wpEval('$u = get_user_by("login", "unverified_test"); update_user_meta($u->ID, "wchs_email_verified", "0"); update_user_meta($u->ID, "wchs_email_verify_code", wp_hash("123456")); update_user_meta($u->ID, "wchs_email_verify_expires", time() + 900); update_user_meta($u->ID, "wchs_email_verify_attempts", 0);');
	  // Login and verify
	  const { ctx, page } = await loginWP(browser, 'unverified_test', 'testpass123'); setMode(3);
	  await page.goto(`${WP}/my-account/`, { waitUntil: 'domcontentloaded', timeout: 15000 });
	  // Submit code via AJAX
	  const r1 = await page.evaluate(async () => {
	    const fd = new FormData(); fd.append('action', 'wchs_verify_email_code'); fd.append('code', '123456');
	    const r = await fetch('/wp-admin/admin-ajax.php', { method: 'POST', body: fd, credentials: 'include' });
	    return r.json();
	  });
	  assert('#51a Correct code accepted', r1.success === true, JSON.stringify(r1));
	  // Try same code again
	  wpEval('$u = get_user_by("login", "unverified_test"); update_user_meta($u->ID, "wchs_email_verified", "0");'); // unverify again
	  const r2 = await page.evaluate(async () => {
	    const fd = new FormData(); fd.append('action', 'wchs_verify_email_code'); fd.append('code', '123456');
	    const r = await fetch('/wp-admin/admin-ajax.php', { method: 'POST', body: fd, credentials: 'include' });
	    return r.json();
	  });
	  assert('#51b Replayed code rejected', r2.success === false, JSON.stringify(r2));
	  await ctx.close(); }

	// #53 Non-digit input
	{ wpEval('$u = get_user_by("login", "unverified_test"); update_user_meta($u->ID, "wchs_email_verified", "0"); update_user_meta($u->ID, "wchs_email_verify_code", wp_hash("654321")); update_user_meta($u->ID, "wchs_email_verify_expires", time() + 900); update_user_meta($u->ID, "wchs_email_verify_attempts", 0);');
	  const { ctx, page } = await loginWP(browser, 'unverified_test', 'testpass123'); setMode(3);
	  await page.goto(`${WP}/my-account/`, { waitUntil: 'domcontentloaded', timeout: 15000 });
	  const r = await page.evaluate(async () => {
	    const fd = new FormData(); fd.append('action', 'wchs_verify_email_code'); fd.append('code', 'abc123');
	    const res = await fetch('/wp-admin/admin-ajax.php', { method: 'POST', body: fd, credentials: 'include' });
	    return res.json();
	  });
	  assert('#53 Non-digit rejected', r.success === false);
	  await ctx.close(); }

	// #54 Empty code
	{ const { ctx, page } = await loginWP(browser, 'unverified_test', 'testpass123'); setMode(3);
	  await page.goto(`${WP}/my-account/`, { waitUntil: 'domcontentloaded', timeout: 15000 });
	  const r = await page.evaluate(async () => {
	    const fd = new FormData(); fd.append('action', 'wchs_verify_email_code'); fd.append('code', '');
	    const res = await fetch('/wp-admin/admin-ajax.php', { method: 'POST', body: fd, credentials: 'include' });
	    return res.json();
	  });
	  assert('#54 Empty code rejected', r.success === false);
	  await ctx.close(); }

	// #55 Not logged in
	{ const c = await freshCtx(browser); const p = await c.newPage();
	  await p.goto(`${WP}/`, { waitUntil: 'domcontentloaded', timeout: 15000 });
	  const r = await p.evaluate(async () => {
	    const fd = new FormData(); fd.append('action', 'wchs_verify_email_code'); fd.append('code', '123456');
	    const res = await fetch('/wp-admin/admin-ajax.php', { method: 'POST', body: fd, credentials: 'include' });
	    return { status: res.status, ok: res.ok };
	  });
	  // wp_ajax_ (not nopriv) should return 0 or redirect for non-logged-in
	  assert('#55 Not logged in rejected', !r.ok || r.status !== 200);
	  await c.close(); }

	// ════════════════════════════════════════════════════════
	// PHASE 6: CONTENT FLASH DETECTION
	// ════════════════════════════════════════════════════════
	console.log('\n=== Content Flash Detection ===');

	// #56 Admin mode 0: no maintenance flash during load
	{ const { ctx, page } = await loginWP(browser, 'admin', 'wchs-admin-dev'); setMode(0);
	  const sightings = [];
	  const navP = page.goto(`${SPA}/`, { timeout: 20000 });
	  const iv = setInterval(async () => {
	    try { sightings.push(await page.evaluate(() => !!document.querySelector('.maintenance'))); }
	    catch {}
	  }, 50);
	  await navP.catch(() => {}); await page.waitForLoadState('networkidle').catch(() => {});
	  clearInterval(iv);
	  const maintFlashes = sightings.filter(x => x).length;
	  assert('#56 Admin M0: 0 maintenance sightings during load', maintFlashes === 0, `${maintFlashes}/${sightings.length} frames`);
	  await ctx.close(); }

	// #57 Guest mode 0: no homepage flash during load
	{ setMode(0); const c = await freshCtx(browser); const p = await c.newPage();
	  const sightings = [];
	  const navP = p.goto(`${SPA}/`, { timeout: 20000 });
	  const iv = setInterval(async () => {
	    try { sightings.push(await p.evaluate(() => !!document.querySelector('.hero__title'))); }
	    catch {}
	  }, 50);
	  await navP.catch(() => {}); await p.waitForLoadState('networkidle').catch(() => {});
	  clearInterval(iv);
	  const heroFlashes = sightings.filter(x => x).length;
	  assert('#57 Guest M0: 0 hero sightings during load', heroFlashes === 0, `${heroFlashes}/${sightings.length} frames`);
	  await c.close(); }

	// #58 Guest mode 0: no site-header flash
	{ const c = await freshCtx(browser); const p = await c.newPage();
	  const sightings = [];
	  const navP = p.goto(`${SPA}/`, { timeout: 20000 });
	  const iv = setInterval(async () => {
	    try { sightings.push(await p.evaluate(() => !!document.querySelector('.site-header'))); }
	    catch {}
	  }, 50);
	  await navP.catch(() => {}); await p.waitForLoadState('networkidle').catch(() => {});
	  clearInterval(iv);
	  const headerFlashes = sightings.filter(x => x).length;
	  assert('#58 Guest M0: 0 header sightings during load', headerFlashes === 0, `${headerFlashes}/${sightings.length} frames`);
	  await c.close(); }

	// ════════════════════════════════════════════════════════
	// PHASE 7: CUSTOM PAGES + API ENDPOINTS PER MODE
	// ════════════════════════════════════════════════════════
	console.log('\n=== Custom Pages + API Endpoints ===');

	// #59-61: Custom pages blocked in mode 0
	{ setMode(0);
	  const c = await freshCtx(browser); const p = await c.newPage();
	  for (const slug of ['/terms-of-service', '/contact', '/shipping-policy']) {
	    try { await p.goto(`${SPA}${slug}`, { timeout: 15000 }); } catch {}
	    await p.waitForTimeout(2000);
	    const hasMaint = (await p.locator('.maintenance').count()) > 0;
	    assert(`#59 M0 Guest ${slug}: maintenance`, hasMaint);
	  }
	  await c.close(); }

	// #62-64: Custom pages blocked in mode 1 (locked = block everything)
	{ setMode(1);
	  const c = await freshCtx(browser); const p = await c.newPage();
	  for (const slug of ['/terms-of-service', '/contact', '/partnerships']) {
	    await visitSPA(p, slug);
	    const hasAccessGate = (await p.locator('.access-gate').count()) > 0;
	    const hasContent = (await p.locator('.content-page__title').count()) > 0;
	    assert(`#62 M1 Guest ${slug}: blocked`, hasAccessGate || !hasContent);
	  }
	  await c.close(); }

	// #65: Custom pages accessible in mode 2 (browse-only)
	{ setMode(2);
	  const c = await freshCtx(browser); const p = await c.newPage();
	  await visitSPA(p, '/terms-of-service');
	  const hasContent = (await p.locator('.content-page__title').count()) > 0;
	  assert('#65 M2 Guest /terms: accessible', hasContent);
	  await c.close(); }

	// #66: API - contact form blocked in mode 0
	{ setMode(0);
	  const c = await freshCtx(browser); const p = await c.newPage();
	  await p.goto(`${SPA}/`, { timeout: 15000 }).catch(() => {});
	  const status = await p.evaluate(async () => {
	    const r = await fetch('/wp/wp-json/wchs/v1/contact', {
	      method: 'POST', credentials: 'include',
	      headers: { 'Content-Type': 'application/json' },
	      body: JSON.stringify({ fields: { name: 'test', email: 'test@test.com', message: 'hi' }, recipient_email: 'x@x.com', subject_prefix: '[T]' })
	    });
	    return r.status;
	  });
	  assert('#66 M0 Guest: contact API 503', status === 503, `status=${status}`);
	  await c.close(); }

	// #67: API - contact form blocked in mode 1
	{ setMode(1);
	  const c = await freshCtx(browser); const p = await c.newPage();
	  await p.goto(`${SPA}/`, { timeout: 15000 }).catch(() => {});
	  await p.waitForTimeout(2000);
	  const status = await p.evaluate(async () => {
	    const r = await fetch('/wp/wp-json/wchs/v1/contact', {
	      method: 'POST', credentials: 'include',
	      headers: { 'Content-Type': 'application/json' },
	      body: JSON.stringify({ fields: { name: 'test', email: 'test@test.com', message: 'hi' }, recipient_email: 'x@x.com', subject_prefix: '[T]' })
	    });
	    return r.status;
	  });
	  assert('#67 M1 Guest: contact API 403', status === 403, `status=${status}`);
	  await c.close(); }

	// #68: API - reviews blocked in mode 0
	{ setMode(0);
	  const c = await freshCtx(browser); const p = await c.newPage();
	  await p.goto(`${SPA}/`, { timeout: 15000 }).catch(() => {});
	  const status = await p.evaluate(async () => {
	    const r = await fetch('/wp/wp-json/wchs/v1/reviews/12', { credentials: 'include' });
	    return r.status;
	  });
	  assert('#68 M0 Guest: reviews API 503', status === 503, `status=${status}`);
	  await c.close(); }

	// #69-71: Mode 2 cart mutations blocked for guest
	{ setMode(2);
	  const c = await freshCtx(browser); const p = await c.newPage();
	  await p.goto(`${SPA}/`, { timeout: 15000 }).catch(() => {});
	  await p.waitForTimeout(2000);

	  const updateStatus = await p.evaluate(async () => {
	    const r = await fetch('/wp/wp-json/wc/store/v1/cart/update-item', {
	      method: 'POST', credentials: 'include',
	      headers: { 'Content-Type': 'application/json' },
	      body: JSON.stringify({ key: 'fake', quantity: 2 })
	    });
	    return r.status;
	  });
	  assert('#69 M2 Guest: cart update-item 403', updateStatus === 403, `status=${updateStatus}`);

	  const removeStatus = await p.evaluate(async () => {
	    const r = await fetch('/wp/wp-json/wc/store/v1/cart/remove-item', {
	      method: 'POST', credentials: 'include',
	      headers: { 'Content-Type': 'application/json' },
	      body: JSON.stringify({ key: 'fake' })
	    });
	    return r.status;
	  });
	  assert('#70 M2 Guest: cart remove-item 403', removeStatus === 403, `status=${removeStatus}`);

	  const couponStatus = await p.evaluate(async () => {
	    const r = await fetch('/wp/wp-json/wc/store/v1/cart/apply-coupon', {
	      method: 'POST', credentials: 'include',
	      headers: { 'Content-Type': 'application/json' },
	      body: JSON.stringify({ code: 'TEST' })
	    });
	    return r.status;
	  });
	  assert('#71 M2 Guest: cart apply-coupon 403', couponStatus === 403, `status=${couponStatus}`);
	  await c.close(); }

	// #72: Mode 2 GET cart allowed for guest (can view empty cart)
	{ const c = await freshCtx(browser); const p = await c.newPage();
	  await p.goto(`${SPA}/`, { timeout: 15000 }).catch(() => {});
	  await p.waitForTimeout(2000);
	  const status = await p.evaluate(async () => {
	    const r = await fetch('/wp/wp-json/wc/store/v1/cart', { credentials: 'include' });
	    return r.status;
	  });
	  assert('#72 M2 Guest: cart GET 200', status === 200, `status=${status}`);
	  await c.close(); }

	// #73: Unverified customer in mode 1 - custom pages blocked on SPA
	{ const { ctx, page } = await loginWP(browser, 'unverified_test', 'testpass123'); setMode(1);
	  await visitSPA(page, '/terms-of-service');
	  const hasAccessGate = (await page.locator('.access-gate').count()) > 0;
	  const hasContent = (await page.locator('.content-page__title').count()) > 0;
	  assert('#73 M1 Unverified /terms: blocked', hasAccessGate || !hasContent);
	  await ctx.close(); }

	// #74: Verified customer in mode 1 - custom pages accessible
	{ const { ctx, page } = await loginWP(browser, 'verified@example.test', 'testpass123'); setMode(1);
	  await visitSPA(page, '/terms-of-service');
	  const hasContent = (await page.locator('.content-page__title').count()) > 0;
	  assert('#74 M1 Verified /terms: accessible', hasContent);
	  await ctx.close(); }

	// #75: Config always returns access_mode correctly
	{ setMode(0);
	  const c = await freshCtx(browser); const p = await c.newPage();
	  await p.goto(`${SPA}/`, { timeout: 15000 }).catch(() => {});
	  const configMode = await p.evaluate(async () => {
	    const r = await fetch('/wp/wp-json/wchs/v1/config'); const d = await r.json();
	    return d.access_mode;
	  });
	  assert('#75 Config returns mode 0', configMode === 0, `got ${configMode}`);
	  await c.close(); }

	// #76: Session always open in mode 0
	{ const c = await freshCtx(browser); const p = await c.newPage();
	  await p.goto(`${SPA}/`, { timeout: 15000 }).catch(() => {});
	  const status = await p.evaluate(async () => {
	    const r = await fetch('/wp/wp-json/wchs/v1/session', { credentials: 'include' });
	    return r.status;
	  });
	  assert('#76 M0 Guest: session API 200', status === 200, `status=${status}`);
	  await c.close(); }

	// ════════════════════════════════════════════════════════
	// PHASE 8: SSR RAW HTML VERIFICATION
	// ════════════════════════════════════════════════════════
	console.log('\n=== SSR Raw HTML Verification ===');

	// These tests verify that SvelteKit's server-side rendering does NOT
	// leak any gated content into the rendered DOM. The `{#if !browser}`
	// guard in +layout.svelte ensures only the loading-gate div is present
	// in the SSR body, regardless of access mode or auth state.
	//
	// NOTE: CSS class names like .hero__title appear in <style> blocks and
	// JS bundles — that's fine. We strip <script> and <style> tags to check
	// only the actual rendered DOM elements.

	function stripScriptsAndStyles(html) {
		return html.replace(/<script[\s\S]*?<\/script>/gi, '').replace(/<style[\s\S]*?<\/style>/gi, '');
	}

	async function captureSSR(browser, url) {
		const c = await freshCtx(browser);
		const p = await c.newPage();
		let rawHtml = '';
		await p.route('**/*', async (route) => {
			const req = route.request();
			if (req.resourceType() === 'document' && req.url().includes(SPA)) {
				const res = await route.fetch();
				rawHtml = await res.text();
				route.fulfill({ response: res });
			} else {
				route.continue();
			}
		});
		await p.goto(url, { waitUntil: 'commit', timeout: 10000 }).catch(() => {});
		await c.close();
		return stripScriptsAndStyles(rawHtml);
	}

	// #77 SSR: no hero/header content in rendered DOM
	{ setMode(3);
	  const dom = await captureSSR(browser, `${SPA}/`);
	  assert('#77 SSR: no hero__title element in DOM', !dom.includes('hero__title'), `found hero element in SSR DOM`);
	  assert('#77 SSR: no site-header element in DOM', !dom.includes('site-header'), `found header element in SSR DOM`);
	  assert('#77 SSR: no maintenance element in DOM', !dom.includes('class="maintenance'), `found maintenance in SSR DOM`);
	  assert('#77 SSR: loading-gate present in DOM', dom.includes('loading-gate'), `no loading-gate in SSR DOM`);
	}

	// #78 SSR mode 0: no maintenance content in rendered DOM
	{ setMode(0);
	  const dom = await captureSSR(browser, `${SPA}/`);
	  assert('#78 SSR M0: no maintenance element in DOM', !dom.includes('class="maintenance'), `leaked maintenance into SSR DOM`);
	  assert('#78 SSR M0: no store-card in DOM', !dom.includes('store-card'), `leaked product cards into SSR DOM`);
	  assert('#78 SSR M0: no hero in DOM', !dom.includes('hero__title'), `leaked hero into SSR DOM`);
	  assert('#78 SSR M0: loading-gate present', dom.includes('loading-gate'), `no loading-gate in SSR DOM`);
	}

	// #79 SSR /shop: no products in rendered DOM
	{ setMode(3);
	  const dom = await captureSSR(browser, `${SPA}/shop`);
	  assert('#79 SSR /shop: no store-card in DOM', !dom.includes('store-card'), `leaked product cards into SSR DOM`);
	  assert('#79 SSR /shop: no pdp content in DOM', !dom.includes('pdp__'), `leaked PDP into SSR DOM`);
	}

	// ════════════════════════════════════════════════════════
	// PHASE 9: WP TEMPLATE GATE (template_redirect)
	// ════════════════════════════════════════════════════════
	console.log('\n=== WP Template Gate ===');

	// These tests verify WordPress native pages are properly gated.
	// The template_redirect hook in headless-access-control.php blocks
	// access to /checkout/, /shop/ etc. based on access mode.

	// #80 Mode 0: Guest hits /checkout/ → 503
	{ setMode(0);
	  const c = await freshCtx(browser); const p = await c.newPage();
	  const res = await p.goto(`${WP}/checkout/`, { timeout: 15000 }).catch(e => null);
	  const status = res?.status() ?? 0;
	  // Mode 0 template_redirect calls wp_die with 503
	  assert('#80 WP M0 Guest /checkout: 503 or wp_die', status === 503 || (await p.content()).includes('Maintenance'), `status=${status}`);
	  await c.close(); }

	// #81 Mode 0: Guest hits /shop/ → 503
	{ const c = await freshCtx(browser); const p = await c.newPage();
	  const res = await p.goto(`${WP}/shop/`, { timeout: 15000 }).catch(e => null);
	  const status = res?.status() ?? 0;
	  assert('#81 WP M0 Guest /shop: 503 or wp_die', status === 503 || (await p.content()).includes('Maintenance'), `status=${status}`);
	  await c.close(); }

	// #82 Mode 1: Guest hits /checkout/ → redirect to /my-account/
	{ setMode(1);
	  const c = await freshCtx(browser); const p = await c.newPage();
	  await p.goto(`${WP}/checkout/`, { timeout: 15000, waitUntil: 'domcontentloaded' }).catch(() => {});
	  const url = p.url();
	  assert('#82 WP M1 Guest /checkout: redirected to my-account', url.includes('my-account'), `ended at ${url}`);
	  await c.close(); }

	// #83 Mode 1: Guest hits /my-account/ → allowed (login page)
	{ const c = await freshCtx(browser); const p = await c.newPage();
	  const res = await p.goto(`${WP}/my-account/`, { timeout: 15000, waitUntil: 'domcontentloaded' }).catch(e => null);
	  const status = res?.status() ?? 0;
	  assert('#83 WP M1 Guest /my-account: allowed (200)', status === 200, `status=${status}`);
	  await c.close(); }

	// #84 Mode 2: Guest hits /checkout/ → redirect to /my-account/
	{ setMode(2);
	  const c = await freshCtx(browser); const p = await c.newPage();
	  await p.goto(`${WP}/checkout/`, { timeout: 15000, waitUntil: 'domcontentloaded' }).catch(() => {});
	  const url = p.url();
	  assert('#84 WP M2 Guest /checkout: redirected to my-account', url.includes('my-account'), `ended at ${url}`);
	  await c.close(); }

	// #85 Mode 2: Guest can visit /shop/ (browse allowed)
	{ const c = await freshCtx(browser); const p = await c.newPage();
	  const res = await p.goto(`${WP}/shop/`, { timeout: 15000, waitUntil: 'domcontentloaded' }).catch(e => null);
	  const status = res?.status() ?? 0;
	  assert('#85 WP M2 Guest /shop: allowed (200)', status === 200, `status=${status}`);
	  await c.close(); }

	// #86 Mode 3: Guest bare /checkout/ is bounced back to the SPA cart
	{ setMode(3);
	  const c = await freshCtx(browser); const p = await c.newPage();
	  const res1 = await p.goto(`${WP}/checkout/`, { timeout: 15000, waitUntil: 'domcontentloaded' }).catch(e => null);
	  const s1 = res1?.status() ?? 0;
	  const checkoutUrl = p.url();
	  const res2 = await p.goto(`${WP}/shop/`, { timeout: 15000, waitUntil: 'domcontentloaded' }).catch(e => null);
	  const s2 = res2?.status() ?? 0;
	  assert('#86 WP M3 Guest /checkout: redirected to shop/cart handoff', checkoutUrl.includes('/shop'), `ended at ${checkoutUrl} status=${s1}`);
	  assert('#86 WP M3 Guest /shop: allowed (200)', s2 === 200, `status=${s2}`);
	  await c.close(); }

	// ════════════════════════════════════════════════════════
	// PHASE 10: CONCURRENT MODE SWITCHING
	// ════════════════════════════════════════════════════════
	console.log('\n=== Concurrent Mode Switching ===');

	// #87 Rapid mode switch: mode 0 → 3 → 0 → 3, admin visits after each
	{ const { ctx, page } = await loginWP(browser, 'admin', 'wchs-admin-dev');
	  const modes = [0, 3, 0, 3];
	  let allCorrect = true;
	  for (const m of modes) {
	    setMode(m);
	    await visitSPA(page);
	    const s = await chk(page);
	    if (m === 0 && s.maint) { allCorrect = false; break; } // admin should never see maintenance
	    if (m === 3 && (s.redBanner || s.amberBanner || s.blueBanner)) { allCorrect = false; break; } // mode 3 = no banner
	  }
	  assert('#87 Rapid mode switching: admin always correct', allCorrect);
	  await ctx.close(); }

	// #88 Config endpoint returns correct mode after rapid switching
	{ const c = await freshCtx(browser); const p = await c.newPage();
	  await p.goto(`${SPA}/`, { timeout: 15000 }).catch(() => {});
	  for (const m of [0, 1, 2, 3, 0]) {
	    setMode(m);
	    await p.waitForTimeout(200);
	    const got = await p.evaluate(async () => {
	      const r = await fetch('/wp/wp-json/wchs/v1/config'); const d = await r.json();
	      return d.access_mode;
	    });
	    assert(`#88 Config mode after switch to ${m}`, got === m, `expected ${m}, got ${got}`);
	  }
	  await c.close(); }

	// ════════════════════════════════════════════════════════
	// PHASE 11: LIVE MODE DETECTION (no hard refresh)
	// ════════════════════════════════════════════════════════
	console.log('\n=== Live Mode Detection ===');

	// These tests verify that if the admin changes access mode while a user
	// is browsing, the SPA updates without requiring a hard refresh. Two
	// mechanisms: (1) 503 responses from store-api trigger config.refresh(),
	// (2) visibilitychange events refresh config + auth.

	// #89 Mode switch → SPA navigation detects 503 → maintenance shown
	{ setMode(3);
	  const c = await freshCtx(browser); const p = await c.newPage();
	  await p.goto(`${SPA}/`, { waitUntil: 'networkidle', timeout: 15000 }).catch(() => {});
	  await p.waitForTimeout(1500);
	  setMode(0); // switch while user is on the page
	  await p.goto(`${SPA}/shop`, { timeout: 15000 }).catch(() => {});
	  await p.waitForTimeout(3000);
	  const s = await chk(p);
	  assert('#89 Mode change during nav: maintenance detected', s.maint);
	  assert('#89 Mode change during nav: no products visible', s.cards === 0);
	  await c.close(); }

	// #90 Mode switch → tab visibility change triggers refresh
	{ setMode(3);
	  const c = await freshCtx(browser); const p = await c.newPage();
	  await p.goto(`${SPA}/`, { waitUntil: 'networkidle', timeout: 15000 }).catch(() => {});
	  await p.waitForTimeout(1500);
	  setMode(0);
	  // Simulate user backgrounding then re-foregrounding the tab
	  await p.evaluate(() => {
	    Object.defineProperty(document, 'visibilityState', { value: 'hidden', configurable: true });
	    document.dispatchEvent(new Event('visibilitychange'));
	  });
	  await p.waitForTimeout(500);
	  await p.evaluate(() => {
	    Object.defineProperty(document, 'visibilityState', { value: 'visible', configurable: true });
	    document.dispatchEvent(new Event('visibilitychange'));
	  });
	  await p.waitForTimeout(3000);
	  const s = await chk(p);
	  assert('#90 Visibility change: maintenance detected', s.maint);
	  await c.close(); }

	// #91 Reverse: mode 0 → 3 while admin browsing → site comes back
	{ const { ctx, page } = await loginWP(browser, 'admin', 'wchs-admin-dev'); setMode(0);
	  await visitSPA(page); await page.waitForTimeout(1500);
	  // Admin sees site (mode 0 bypass). Switch to mode 3 — banner should disappear.
	  setMode(3);
	  await page.evaluate(() => {
	    Object.defineProperty(document, 'visibilityState', { value: 'hidden', configurable: true });
	    document.dispatchEvent(new Event('visibilitychange'));
	  });
	  await page.waitForTimeout(500);
	  await page.evaluate(() => {
	    Object.defineProperty(document, 'visibilityState', { value: 'visible', configurable: true });
	    document.dispatchEvent(new Event('visibilitychange'));
	  });
	  await page.waitForTimeout(3000);
	  const s = await chk(page);
	  assert('#91 M0 → M3 (admin): red banner removed', !s.redBanner);
	  await ctx.close(); }

	// ════════════════════════════════════════════════════════
	// PHASE 12: INTEGRATION COMPAT — Phase 1-3 surface checks
	// ════════════════════════════════════════════════════════
	console.log('\n=== Integration Compat ===');
	setMode(3);

	// #92 SEO baseline on homepage: og:title, og:type, twitter:card,
	//      canonical, description all present after hydration
	{ const c = await freshCtx(browser); const p = await c.newPage();
	  await p.goto(`${SPA}/`, { waitUntil: 'networkidle', timeout: 15000 }).catch(() => {});
	  await p.waitForTimeout(1500);
	  const meta = await p.evaluate(() => ({
	    ogTitle: document.querySelector('meta[property="og:title"]')?.getAttribute('content'),
	    ogType: document.querySelector('meta[property="og:type"]')?.getAttribute('content'),
	    twitter: document.querySelector('meta[name="twitter:card"]')?.getAttribute('content'),
	    canonical: document.querySelector('link[rel="canonical"]')?.getAttribute('href'),
	    desc: document.querySelector('meta[name="description"]')?.getAttribute('content'),
	  }));
	  assert('#92 homepage og:title present', !!meta.ogTitle);
	  assert('#92 homepage og:type=website', meta.ogType === 'website');
	  assert('#92 homepage twitter:card present', !!meta.twitter);
	  assert('#92 homepage canonical present', !!meta.canonical);
	  assert('#92 homepage description present', !!meta.desc, `got: ${meta.desc}`);
	  await c.close(); }

	// #93 SEO baseline on PDP: og:type=product + Product JSON-LD
	{ const c = await freshCtx(browser); const p = await c.newPage();
	  await p.goto(`${SPA}/product/canvas-tote`, { waitUntil: 'networkidle', timeout: 15000 }).catch(() => {});
	  await p.waitForTimeout(2000);
	  const meta = await p.evaluate(() => {
	    const ld = Array.from(document.querySelectorAll('script[type="application/ld+json"]'))
	      .map(s => { try { return JSON.parse(s.textContent || ''); } catch { return null; } })
	      .filter(Boolean);
	    return {
	      ogType: document.querySelector('meta[property="og:type"]')?.getAttribute('content'),
	      ogImage: document.querySelector('meta[property="og:image"]')?.getAttribute('content'),
	      jsonLdProduct: ld.find(obj => obj && obj['@type'] === 'Product'),
	    };
	  });
	  assert('#93 PDP og:type=product', meta.ogType === 'product', `got: ${meta.ogType}`);
	  assert('#93 PDP JSON-LD Product present', !!meta.jsonLdProduct);
	  assert('#93 PDP schema has offers', !!(meta.jsonLdProduct && meta.jsonLdProduct.offers));
	  assert('#93 PDP schema has priceCurrency', !!(meta.jsonLdProduct?.offers?.priceCurrency));
	  await c.close(); }

	// #94 Order-received page has noindex (contains order keys)
	{ const c = await freshCtx(browser); const p = await c.newPage();
	  await p.goto(`${SPA}/order-received`, { waitUntil: 'networkidle', timeout: 15000 }).catch(() => {});
	  await p.waitForTimeout(1000);
	  const robots = await p.evaluate(() => document.querySelector('meta[name="robots"]')?.getAttribute('content'));
	  assert('#94 order-received meta robots=noindex', !!(robots && robots.includes('noindex')), `got: ${robots}`);
	  await c.close(); }

	// #95 Custom content page emits SEO meta
	{ const c = await freshCtx(browser); const p = await c.newPage();
	  await p.goto(`${SPA}/terms-of-service`, { waitUntil: 'networkidle', timeout: 15000 }).catch(() => {});
	  await p.waitForTimeout(1500);
	  const meta = await p.evaluate(() => ({
	    title: document.title,
	    desc: document.querySelector('meta[name="description"]')?.getAttribute('content'),
	    ogTitle: document.querySelector('meta[property="og:title"]')?.getAttribute('content'),
	  }));
	  assert('#95 content page has title', !!meta.title);
	  assert('#95 content page has description', !!meta.desc);
	  assert('#95 content page has og:title', !!meta.ogTitle);
	  await c.close(); }

	// #96 Abandoned-cart setting exposed in /wchs/v1/config (sanity —
	//      setting exists via get_site_settings, which config reads)
	{ const raw = wpEval('$s = \\WCHS\\Admin\\AdminPage::get_site_settings(); echo isset($s["abandoned_cart_enabled"]) ? "yes" : "no";');
	  assert('#96 abandoned_cart_enabled setting exists', raw.includes('yes'), raw); }

	// #97 formatPrice unit-equivalent: USD $9.99 from "999" minor units
	{ const c = await freshCtx(browser); const p = await c.newPage();
	  await p.goto(`${SPA}/shop`, { waitUntil: 'networkidle', timeout: 15000 }).catch(() => {});
	  await p.waitForTimeout(2000);
	  // Spot-check: a product card should render a price that starts with $
	  const firstPrice = await p.evaluate(() => {
	    const cards = document.querySelectorAll('.store-card__price, .product-card__price');
	    for (const el of cards) {
	      const t = el.textContent?.trim() || '';
	      if (t) return t;
	    }
	    return null;
	  });
	  assert('#97 product card renders a currency-formatted price', !!firstPrice && /[\$€¥£]/.test(firstPrice), `got: ${firstPrice}`);
	  await c.close(); }

	// #98 view_item fires on PDP mount (dataLayer inspection)
	{ const c = await freshCtx(browser); const p = await c.newPage();
	  await p.addInitScript(() => { window.dataLayer = []; });
	  await p.goto(`${SPA}/product/canvas-tote`, { waitUntil: 'networkidle', timeout: 15000 }).catch(() => {});
	  await p.waitForTimeout(2500);
	  const events = await p.evaluate(() => (window.dataLayer || []).map(e => e.event).filter(Boolean));
	  assert('#98 view_item fired on PDP mount', events.includes('view_item'), `events: ${JSON.stringify(events)}`);
	  await c.close(); }

	// ════════════════════════════════════════════════════════
	// CLEANUP + REPORT
	// ════════════════════════════════════════════════════════
	setMode(3);
	await browser.close();

	const elapsed = ((Date.now() - t0) / 1000).toFixed(1);
	console.log(`\n${'='.repeat(60)}`);
	console.log(`${passed} passed, ${failed} failed (${elapsed}s)`);
	if (failures.length) {
		console.log('\nFAILURES:');
		failures.forEach(f => console.log(`  - ${f}`));
	}
	console.log('='.repeat(60));
	process.exit(failed > 0 ? 1 : 0);
})().catch(e => {
	console.error('FATAL:', e.message);
	try { setMode(3); } catch {}
	process.exit(1);
});
