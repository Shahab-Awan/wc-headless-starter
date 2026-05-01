// Smoke test for the custom one-click upsell flow.
//
// Prerequisites:
//   - A test card gateway configured if testing card-based upsells
//   - Gateway webhooks configured if the selected plugin requires them
//   - Upsell product ID set in WCHS admin → Site Configuration
//
// Run: node tests/smoke-upsell.js

const path = require('path');
const fs = require('fs');
const { execSync } = require('child_process');

const { chromium } = require('./playwright');

const SPA = 'http://localhost:5175';
const WP = 'http://localhost:8099';
const ROOT = path.resolve(__dirname, '..');
const SHOTS = path.resolve(__dirname, 'screenshots', 'smoke-upsell');
fs.rmSync(SHOTS, { recursive: true, force: true });
fs.mkdirSync(SHOTS, { recursive: true });

let stepCounter = 0;
async function shoot(page, label) {
	const n = String(++stepCounter).padStart(2, '0');
	await page.screenshot({ path: path.join(SHOTS, `${n}-${label}.png`), fullPage: false });
	console.log(`  📸 ${n}-${label}`);
}

function wp(php) {
	return execSync(
		`cd ${ROOT} && docker compose exec -T -u 33:33 wpcli php -d memory_limit=1024M /usr/local/bin/wp eval '${php.replace(/'/g, "'\\''")}'`,
		{ stdio: 'pipe' }
	).toString().trim();
}

(async () => {
	// Ensure upsell is configured
	const config = JSON.parse(execSync(`curl -s ${WP}/wp-json/wchs/v1/config`).toString());
	const upsellPid = config.upsell_product_id || 0;
	console.log(`Upsell product ID: ${upsellPid || '(not set — check admin)'}`);

	// Restock upsell product
	if (upsellPid) {
		wp(`$p=wc_get_product(${upsellPid}); if($p){$p->set_stock_quantity(1000);$p->set_stock_status("instock");$p->save();echo "restocked";}`);
	}

	const browser = await chromium.launch({ headless: true });

	try {
		// ── Customer flow ─────────────────────────────────────
		console.log('\n=== CUSTOMER CHECKOUT + UPSELL ===');
		const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
		const page = await ctx.newPage();

		// Add item to cart
		console.log('→ adding item to cart');
		await page.goto(`${SPA}/shop`, { waitUntil: 'networkidle' });
		const card = await page.$('a[href*="/product/"]');
		if (card) {
			await card.click();
			await page.waitForFunction(
				() => Array.from(document.querySelectorAll('button')).some(b => /add to cart/i.test(b.textContent)),
				{ timeout: 8000 }
			).catch(() => {});
			const atc = await page.evaluateHandle(() =>
				Array.from(document.querySelectorAll('button')).find(b => /add to cart/i.test(b.textContent))
			);
			if (atc.asElement()) {
				await atc.asElement().click();
				await page.waitForTimeout(1500);
			}
		}

		// Navigate to checkout
		console.log('→ navigating to checkout');
		const coLink = await page.evaluateHandle(() =>
			Array.from(document.querySelectorAll('a')).find(a => /checkout/i.test(a.textContent) && /checkout/.test(a.href))
		);
		if (coLink.asElement()) {
			const href = await coLink.asElement().getAttribute('href');
			await page.goto(href, { waitUntil: 'domcontentloaded' });
			await page.waitForSelector('#place_order', { timeout: 15000 }).catch(() => {});
			await page.waitForTimeout(2000);
		}
		await shoot(page, 'checkout-landed');

		// Fill billing
		console.log('→ filling billing');
		await page.fill('#billing_first_name', 'Smoke');
		await page.fill('#billing_last_name', 'Tester');
		await page.fill('#billing_address_1', '123 Dev Lane');
		await page.fill('#billing_city', 'Testville');
		await page.fill('#billing_postcode', '90210');
		await page.fill('#billing_phone', '5555551234');
		await page.fill('#billing_email', `smoke+${Date.now()}@test.local`);

		// Fill Stripe card
		console.log('→ filling Stripe card');
		await page.waitForTimeout(1500);
		for (const f of page.frames()) {
			if (/stripe.*elements-inner/i.test(f.url())) {
				try {
					await f.waitForSelector('input[name="number"]', { timeout: 5000 });
					await f.fill('input[name="number"]', '4242424242424242');
					await f.fill('input[name="expiry"]', '12 / 30');
					await f.fill('input[name="cvc"]', '123');
					const postal = await f.$('input[name="postal"]');
					if (postal) await f.fill('input[name="postal"]', '90210');
					console.log('  ✓ card entered');
					break;
				} catch (e) { /* try next frame */ }
			}
		}

		// Submit
		console.log('→ placing order');
		await page.click('#place_order');
		try {
			await page.waitForURL(/order-received|wchs_upsell/, { timeout: 30000 });
		} catch (e) {
			console.log('  ! timeout — at', page.url());
		}
		await page.waitForTimeout(2000);
		await shoot(page, 'after-submit');
		const postUrl = page.url();
		console.log(`  post-submit URL: ${postUrl}`);

		// Check if upsell page appeared
		if (/wchs_upsell=1/.test(postUrl)) {
			console.log('  ✓ upsell offer page detected');
			await shoot(page, 'upsell-offer');

			// Click Yes
			console.log('→ clicking accept');
			const acceptLink = await page.$('.wchs-offer__accept');
			if (acceptLink) {
				await acceptLink.click();
				await page.waitForURL(/order-received/, { timeout: 20000 }).catch(() => {});
				await page.waitForTimeout(2000);
				await shoot(page, 'after-accept');
				console.log(`  post-accept URL: ${page.url()}`);
			} else {
				console.log('  ✗ no accept button found');
			}
		} else {
			console.log('  ! upsell page did not appear');
		}

		// Verify the order
		const latestOrder = JSON.parse(wp(`
			$o = wc_get_orders(["limit"=>1,"orderby"=>"date","order"=>"DESC"])[0];
			$items = [];
			foreach ($o->get_items() as $i) $items[] = ["name"=>$i->get_name(),"qty"=>$i->get_quantity(),"total"=>$i->get_total()];
			echo json_encode([
				"id" => $o->get_id(),
				"total" => $o->get_total(),
				"status" => $o->get_status(),
				"upsell_status" => $o->get_meta("_wchs_upsell_status"),
				"items" => $items
			]);
		`));
		console.log('\n=== ORDER RESULT ===');
		console.log(`  Order #${latestOrder.id}: $${latestOrder.total} (${latestOrder.status})`);
		console.log(`  Upsell: ${latestOrder.upsell_status}`);
		latestOrder.items.forEach(i => console.log(`  ${i.name} x${i.qty} = $${i.total}`));

		const passed = latestOrder.upsell_status === 'accepted' && latestOrder.items.length === 2;
		console.log(`\n${passed ? '✓' : '✗'} Upsell flow ${passed ? 'PASSED' : 'FAILED'}`);

		await ctx.close();
	} catch (err) {
		console.error('❌ smoke failed:', err.message);
		process.exitCode = 1;
	} finally {
		await browser.close();
	}
})();
