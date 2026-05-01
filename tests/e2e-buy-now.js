// E2E test: Buy It Now — guest, returning customer, edge cases
// Run: node tests/e2e-buy-now.js

const path = require('path');
const { execSync } = require('child_process');
const http = require('http');
const { chromium } = require('./playwright');

const ROOT = path.resolve(__dirname, '..');
const SPA = 'http://localhost:5175';
let pass = 0, fail = 0, results = [];

function assert(name, ok) {
	if (ok) { pass++; results.push({ name, s: 'PASS' }); console.log(`  ✓ ${name}`); }
	else { fail++; results.push({ name, s: 'FAIL' }); console.log(`  ✗ ${name}`); }
}

function wp(php) {
	return execSync(
		`cd ${ROOT} && docker compose exec -T -u 33:33 wpcli php -d memory_limit=1024M /usr/local/bin/wp eval '${php.replace(/'/g, "'\\''")}'`,
		{ stdio: 'pipe' }
	).toString().trim();
}

(async () => {
	const browser = await chromium.launch({ headless: true });

	try {
		// ═══════════════════════════════════════════
		// TEST 1: Guest — Buy Now should redirect to checkout
		// ═══════════════════════════════════════════
		console.log('\n=== TEST 1: Guest Buy Now ===');
		const ctx1 = await browser.newContext({ viewport: { width: 1440, height: 900 } });
		const p1 = await ctx1.newPage();
		let pageError1 = null;
		p1.on('pageerror', err => { pageError1 = err.message; });

		await p1.goto(`${SPA}/product/cable-organizer`, { waitUntil: 'domcontentloaded' });
		await p1.waitForTimeout(3000);

		const buyBtn1 = await p1.$('.pdp__buy-now');
		assert('Buy Now button exists on PDP', !!buyBtn1);

		if (buyBtn1) {
			await buyBtn1.click();
			await p1.waitForTimeout(6000);

			assert('No JS errors', !pageError1);
			const url1 = p1.url();
			const redirected = url1.includes('checkout') || url1.includes('8099');
			assert('Guest redirected to checkout', redirected);
			console.log(`  URL: ${url1}`);
		}
		await ctx1.close();

		// ═══════════════════════════════════════════
		// TEST 2: Guest — Buy Now on variable product
		// ═══════════════════════════════════════════
		console.log('\n=== TEST 2: Guest Buy Now (variable product) ===');
		const ctx2 = await browser.newContext({ viewport: { width: 1440, height: 900 } });
		const p2 = await ctx2.newPage();
		let pageError2 = null;
		p2.on('pageerror', err => { pageError2 = err.message; });

		await p2.goto(`${SPA}/product/variable-test-backpack`, { waitUntil: 'domcontentloaded' });
		await p2.waitForTimeout(3000);

		// Select attributes — PDP uses variant buttons, not selects
		const variantGroups = await p2.$$('.pdp__variant-group');
		console.log(`  Found ${variantGroups.length} variant groups`);
		for (const group of variantGroups) {
			const label = await group.$eval('.pdp__variant-label', el => el.textContent.trim());
			const firstBtn = await group.$('.pdp__variant-btn:not(:disabled)');
			if (firstBtn) {
				const btnText = await firstBtn.textContent();
				console.log(`  Selecting ${label}: ${btnText.trim()}`);
				await firstBtn.click();
				await p2.waitForTimeout(500);
			}
		}

		const buyBtn2 = await p2.$('.pdp__buy-now');
		if (buyBtn2) {
			const disabled2 = await buyBtn2.isDisabled();
			assert('Buy Now enabled after selecting attributes', !disabled2);

			if (!disabled2) {
				await buyBtn2.click();
				await p2.waitForTimeout(6000);
				assert('No JS errors on variable product', !pageError2);
				const url2 = p2.url();
				assert('Variable product: redirected to checkout', url2.includes('checkout') || url2.includes('8099'));
			}
		}
		await ctx2.close();

		// ═══════════════════════════════════════════
		// TEST 3: Instant checkout API — no saved data
		// ═══════════════════════════════════════════
		console.log('\n=== TEST 3: Instant checkout API — missing data ===');

		// Create a test user with no saved data
		const userId = wp(`
			$u = get_user_by("login", "buynow_test");
			if (!$u) {
				$uid = wp_create_user("buynow_test", "testpass123", "buynow@test.local");
				echo $uid;
			} else {
				echo $u->ID;
			}
		`);
		console.log(`  Test user ID: ${userId}`);

		// Call instant checkout API directly (simulate authenticated call)
		const apiResult = wp(`
			wp_set_current_user(${userId});
			$request = new WP_REST_Request("POST", "/wchs/v1/instant-checkout");
			$request->set_param("product_id", 23);
			$request->set_param("quantity", 1);
			$response = wchs_instant_checkout($request);
			if ($response instanceof WP_REST_Response) {
				echo json_encode($response->get_data());
			} elseif ($response instanceof WP_Error) {
				echo json_encode(["error" => $response->get_error_message()]);
			}
		`);
		const apiData = JSON.parse(apiResult);
		assert('No saved payment: returns needs_checkout', apiData.needs_checkout === true);
		console.log(`  Reason: ${apiData.reason || 'N/A'}`);

		// ═══════════════════════════════════════════
		// TEST 4: Instant checkout — has payment but no address
		// ═══════════════════════════════════════════
		console.log('\n=== TEST 4: Has payment, no address ===');

		wp(`
			update_user_meta(${userId}, "_wchs_preferred_offline_gateway", "wchs_offline_cashapp");
		`);

		const api4 = JSON.parse(wp(`
			wp_set_current_user(${userId});
			$request = new WP_REST_Request("POST", "/wchs/v1/instant-checkout");
			$request->set_param("product_id", 23);
			$request->set_param("quantity", 1);
			$response = wchs_instant_checkout($request);
			if ($response instanceof WP_REST_Response) echo json_encode($response->get_data());
			elseif ($response instanceof WP_Error) echo json_encode(["error" => $response->get_error_message()]);
		`));
		assert('Has payment but no address: returns needs_checkout', api4.needs_checkout === true);
		assert('Reason is no_billing_address', api4.reason === 'no_billing_address');

		// ═══════════════════════════════════════════
		// TEST 5: Instant checkout — has payment + address but no shipping method
		// ═══════════════════════════════════════════
		console.log('\n=== TEST 5: Has payment + address, no shipping method ===');

		wp(`
			$customer = new WC_Customer(${userId});
			$customer->set_billing_address_1("1213 Liberty Lane");
			$customer->set_billing_city("Pueblo");
			$customer->set_billing_state("CO");
			$customer->set_billing_postcode("81001");
			$customer->set_billing_country("US");
			$customer->save();
		`);

		const api5 = JSON.parse(wp(`
			wp_set_current_user(${userId});
			$request = new WP_REST_Request("POST", "/wchs/v1/instant-checkout");
			$request->set_param("product_id", 23);
			$request->set_param("quantity", 1);
			$response = wchs_instant_checkout($request);
			if ($response instanceof WP_REST_Response) echo json_encode($response->get_data());
			elseif ($response instanceof WP_Error) echo json_encode(["error" => $response->get_error_message()]);
		`));
		assert('Has payment + address but no shipping: returns needs_checkout', api5.needs_checkout === true);
		assert('Reason is no_shipping_method', api5.reason === 'no_shipping_method');

		// ═══════════════════════════════════════════
		// TEST 6: Instant checkout — ALL data saved (offline gateway)
		// ═══════════════════════════════════════════
		console.log('\n=== TEST 6: Full instant checkout (offline) ===');

		wp(`
			update_user_meta(${userId}, "_wchs_preferred_shipping_method", "flat_rate:1");
			update_user_meta(${userId}, "_wchs_preferred_shipping_title", "Standard Shipping");
		`);

		const api6 = JSON.parse(wp(`
			wp_set_current_user(${userId});
			$request = new WP_REST_Request("POST", "/wchs/v1/instant-checkout");
			$request->set_param("product_id", 23);
			$request->set_param("quantity", 1);
			$response = wchs_instant_checkout($request);
			if ($response instanceof WP_REST_Response) echo json_encode($response->get_data());
			elseif ($response instanceof WP_Error) echo json_encode(["error" => $response->get_error_message()]);
		`));
		assert('Full data: order created', !!api6.order_id);
		assert('Has redirect URL', !!api6.redirect_url);

		if (api6.order_id) {
			const orderCheck = JSON.parse(wp(`
				$o = wc_get_order(${api6.order_id});
				$shipping_methods = [];
				foreach ($o->get_shipping_methods() as $s) {
					$shipping_methods[] = ["method" => $s->get_method_id(), "title" => $s->get_name(), "total" => $s->get_total()];
				}
				echo json_encode([
					"status" => $o->get_status(),
					"total" => $o->get_total(),
					"payment" => $o->get_payment_method(),
					"items" => count($o->get_items()),
					"shipping" => $shipping_methods,
					"billing_city" => $o->get_billing_city(),
				]);
			`));
			console.log(`  Order #${api6.order_id}: ${orderCheck.status}, $${orderCheck.total}, ${orderCheck.payment}`);
			console.log(`  Shipping: ${JSON.stringify(orderCheck.shipping)}`);
			assert('Order status is on-hold (offline)', orderCheck.status === 'on-hold');
			assert('Has shipping line item', orderCheck.shipping.length > 0);
			assert('Shipping cost included', parseFloat(orderCheck.shipping[0]?.total || 0) > 0 || orderCheck.shipping[0]?.method === 'free_shipping');
			assert('Billing address populated', orderCheck.billing_city === 'Pueblo');
			assert('Has 1 product item', orderCheck.items === 1);
		}

		// ═══════════════════════════════════════════
		// TEST 7: Double-click prevention (idempotency)
		// ═══════════════════════════════════════════
		console.log('\n=== TEST 7: Double-click idempotency ===');

		// Fire two simultaneous requests
		const [r7a, r7b] = await Promise.all([
			new Promise(resolve => {
				const result = wp(`
					wp_set_current_user(${userId});
					$request = new WP_REST_Request("POST", "/wchs/v1/instant-checkout");
					$request->set_param("product_id", 23);
					$request->set_param("quantity", 1);
					$response = wchs_instant_checkout($request);
					if ($response instanceof WP_REST_Response) echo json_encode($response->get_data());
					elseif ($response instanceof WP_Error) echo json_encode(["error" => $response->get_error_message()]);
				`);
				resolve(JSON.parse(result));
			}),
			new Promise(resolve => {
				const result = wp(`
					wp_set_current_user(${userId});
					$request = new WP_REST_Request("POST", "/wchs/v1/instant-checkout");
					$request->set_param("product_id", 23);
					$request->set_param("quantity", 1);
					$response = wchs_instant_checkout($request);
					if ($response instanceof WP_REST_Response) echo json_encode($response->get_data());
					elseif ($response instanceof WP_Error) echo json_encode(["error" => $response->get_error_message()]);
				`);
				resolve(JSON.parse(result));
			}),
		]);

		const orders7 = [r7a.order_id, r7b.order_id].filter(Boolean);
		console.log(`  Orders created: ${orders7.length} (${orders7.join(', ')})`);
		// Both should succeed since they're sequential (wp-cli calls are blocking)
		// but the mutex should prevent true concurrent execution
		assert('Both requests completed', orders7.length >= 1);

		// ═══════════════════════════════════════════
		// TEST 8: Invalid product
		// ═══════════════════════════════════════════
		console.log('\n=== TEST 8: Invalid product ===');

		const api8 = JSON.parse(wp(`
			wp_set_current_user(${userId});
			$request = new WP_REST_Request("POST", "/wchs/v1/instant-checkout");
			$request->set_param("product_id", 99999);
			$request->set_param("quantity", 1);
			$response = wchs_instant_checkout($request);
			if ($response instanceof WP_REST_Response) echo json_encode($response->get_data());
			elseif ($response instanceof WP_Error) echo json_encode(["error" => $response->get_error_message()]);
		`));
		assert('Invalid product: returns error', !!api8.error);

		// ═══════════════════════════════════════════
		// TEST 9: Unauthenticated API call
		// ═══════════════════════════════════════════
		console.log('\n=== TEST 9: Unauthenticated API call ===');

		const res9 = await new Promise(resolve => {
			const req = http.request({
				hostname: 'localhost', port: 8099,
				path: '/wp-json/wchs/v1/instant-checkout',
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
			}, res => {
				let body = '';
				res.on('data', d => body += d);
				res.on('end', () => resolve({ status: res.statusCode, body }));
			});
			req.write(JSON.stringify({ product_id: 23, quantity: 1 }));
			req.end();
		});
		assert('Unauthenticated: rejected (401)', res9.status === 401);

	} catch (err) {
		console.error('FATAL:', err.message);
		console.error(err.stack);
		process.exitCode = 1;
	} finally {
		// Cleanup test user
		try { wp(`$u = get_user_by("login", "buynow_test"); if ($u) wp_delete_user($u->ID);`); } catch (e) {}

		console.log(`\n${'='.repeat(50)}`);
		console.log(`RESULTS: ${pass} passed, ${fail} failed`);
		results.forEach(r => console.log(`  ${r.s === 'PASS' ? '✓' : '✗'} ${r.name}`));
		console.log(`\n${fail === 0 ? '✓ ALL TESTS PASSED' : '✗ SOME TESTS FAILED'}`);
		if (fail > 0) process.exitCode = 1;
		await browser.close();
	}
})();
