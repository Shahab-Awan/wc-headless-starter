// Adversarial security tests for the one-click upsell engine
// Tests: race conditions, idempotency, token replay, order state attacks,
//        parameter manipulation, session ownership
//
// Run: node tests/e2e-upsell-security.js

const path = require('path');
const { execSync } = require('child_process');
const http = require('http');

const ROOT = path.resolve(__dirname, '..');
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

function createTestOrder() {
	return JSON.parse(wp(`
		$product = wc_get_products(["limit"=>1,"status"=>"publish"])[0];
		$order = wc_create_order();
		$order->add_product($product, 1);
		$order->set_payment_method("wchs_offline_cashapp");
		$order->set_payment_method_title("CashApp");
		$order->set_billing_email("test@test.local");
		$order->calculate_totals();
		$order->update_status("on-hold");
		$order->save();
		$url = $order->get_checkout_order_received_url();
		$token = $order->get_meta("_wchs_upsell_token");
		echo json_encode([
			"id" => $order->get_id(),
			"key" => $order->get_order_key(),
			"token" => $token,
			"total" => $order->get_total(),
			"items" => count($order->get_items()),
		]);
	`));
}

function getOrder(id) {
	return JSON.parse(wp(`
		$o = wc_get_order(${id});
		$items = [];
		foreach ($o->get_items() as $i) $items[] = $i->get_name();
		echo json_encode([
			"id" => $o->get_id(),
			"total" => $o->get_total(),
			"status" => $o->get_status(),
			"upsell_status" => $o->get_meta("_wchs_upsell_status"),
			"has_token" => !empty($o->get_meta("_wchs_upsell_token")),
			"items" => $items,
			"item_count" => count($items),
		]);
	`));
}

function httpGet(urlStr) {
	return new Promise((resolve) => {
		const url = new URL(urlStr);
		const req = http.get({
			hostname: url.hostname,
			port: url.port,
			path: url.pathname + url.search,
			headers: { 'User-Agent': 'SecurityTest/1.0' },
		}, (res) => {
			let body = '';
			res.on('data', d => body += d);
			res.on('end', () => resolve({ status: res.statusCode, headers: res.headers, body }));
		});
		req.on('error', () => resolve({ status: 0, headers: {}, body: '' }));
		req.end();
	});
}

(async () => {
	try {
		// ═══════════════════════════════════════════
		// TEST 1: Double-click / Race condition
		// ═══════════════════════════════════════════
		console.log('\n=== TEST 1: Double-click race condition ===');
		const order1 = createTestOrder();
		console.log(`  Order #${order1.id}, $${order1.total}`);

		const acceptUrl = `http://localhost:8099/checkout/order-received/${order1.id}/?wchs_upsell_accept=1&order_id=${order1.id}&order_key=${order1.key}&token=${order1.token}`;

		// Fire two accept requests simultaneously
		const [r1, r2] = await Promise.all([
			httpGet(acceptUrl),
			httpGet(acceptUrl),
		]);

		const after1 = getOrder(order1.id);
		assert('Race: only 1 upsell product added (not 2)', after1.item_count === 2);
		assert('Race: status is accepted', after1.upsell_status === 'accepted');
		assert('Race: token invalidated', !after1.has_token);
		console.log(`  Items: ${after1.item_count}, Total: $${after1.total}, Status: ${after1.upsell_status}`);

		// ═══════════════════════════════════════════
		// TEST 2: Token replay after accept
		// ═══════════════════════════════════════════
		console.log('\n=== TEST 2: Token replay after accept ===');
		// Try to use the same token again
		const replay = await httpGet(acceptUrl);
		const after2 = getOrder(order1.id);
		assert('Replay: still only 2 items', after2.item_count === 2);
		assert('Replay: status unchanged', after2.upsell_status === 'accepted');

		// ═══════════════════════════════════════════
		// TEST 3: Token replay after decline
		// ═══════════════════════════════════════════
		console.log('\n=== TEST 3: Token replay after decline ===');
		const order3 = createTestOrder();
		const declineUrl = `http://localhost:8099/checkout/order-received/${order3.id}/?wchs_upsell_decline=1&order_id=${order3.id}&order_key=${order3.key}&token=${order3.token}`;
		await httpGet(declineUrl);
		const after3a = getOrder(order3.id);
		assert('Decline: status is declined', after3a.upsell_status === 'declined');
		assert('Decline: token invalidated', !after3a.has_token);

		// Try to accept with the old token
		const replayAccept = `http://localhost:8099/checkout/order-received/${order3.id}/?wchs_upsell_accept=1&order_id=${order3.id}&order_key=${order3.key}&token=${order3.token}`;
		await httpGet(replayAccept);
		const after3b = getOrder(order3.id);
		assert('Post-decline accept: still 1 item', after3b.item_count === 1);
		assert('Post-decline accept: status still declined', after3b.upsell_status === 'declined');

		// ═══════════════════════════════════════════
		// TEST 4: Fake/wrong token
		// ═══════════════════════════════════════════
		console.log('\n=== TEST 4: Fake token ===');
		const order4 = createTestOrder();
		const fakeTokenUrl = `http://localhost:8099/checkout/order-received/${order4.id}/?wchs_upsell_accept=1&order_id=${order4.id}&order_key=${order4.key}&token=FAKEFAKEFAKE123`;
		await httpGet(fakeTokenUrl);
		const after4 = getOrder(order4.id);
		assert('Fake token: still 1 item', after4.item_count === 1);
		assert('Fake token: status still pending', after4.upsell_status === 'pending');

		// ═══════════════════════════════════════════
		// TEST 5: Wrong order key
		// ═══════════════════════════════════════════
		console.log('\n=== TEST 5: Wrong order key ===');
		const wrongKeyUrl = `http://localhost:8099/checkout/order-received/${order4.id}/?wchs_upsell_accept=1&order_id=${order4.id}&order_key=wc_order_WRONG&token=${order4.token}`;
		await httpGet(wrongKeyUrl);
		const after5 = getOrder(order4.id);
		assert('Wrong key: still 1 item', after5.item_count === 1);
		assert('Wrong key: status still pending', after5.upsell_status === 'pending');

		// ═══════════════════════════════════════════
		// TEST 6: Order ID enumeration
		// ═══════════════════════════════════════════
		console.log('\n=== TEST 6: Order ID enumeration ===');
		const enumUrl = `http://localhost:8099/checkout/order-received/1/?wchs_upsell_accept=1&order_id=1&order_key=wc_order_guess&token=guess`;
		const enumRes = await httpGet(enumUrl);
		assert('Enumeration: redirects (not 200)', enumRes.status === 302 || enumRes.status === 301);

		// ═══════════════════════════════════════════
		// TEST 7: Cancelled order
		// ═══════════════════════════════════════════
		console.log('\n=== TEST 7: Cancelled order ===');
		const order7 = createTestOrder();
		// Cancel the order
		wp(`$o = wc_get_order(${order7.id}); $o->update_status("cancelled"); $o->save();`);
		const cancelAcceptUrl = `http://localhost:8099/checkout/order-received/${order7.id}/?wchs_upsell_accept=1&order_id=${order7.id}&order_key=${order7.key}&token=${order7.token}`;
		await httpGet(cancelAcceptUrl);
		const after7 = getOrder(order7.id);
		assert('Cancelled order: upsell rejected', after7.upsell_status === 'failed' || after7.item_count === 1);
		assert('Cancelled order: still 1 item', after7.item_count === 1);

		// ═══════════════════════════════════════════
		// TEST 8: Cross-order token attack
		// ═══════════════════════════════════════════
		console.log('\n=== TEST 8: Cross-order token attack ===');
		const orderA = createTestOrder();
		const orderB = createTestOrder();
		// Try to use order A's token on order B
		const crossUrl = `http://localhost:8099/checkout/order-received/${orderB.id}/?wchs_upsell_accept=1&order_id=${orderB.id}&order_key=${orderB.key}&token=${orderA.token}`;
		await httpGet(crossUrl);
		const afterB = getOrder(orderB.id);
		assert('Cross-order: order B still 1 item', afterB.item_count === 1);
		assert('Cross-order: order B status still pending', afterB.upsell_status === 'pending');

		// ═══════════════════════════════════════════
		// TEST 9: Accept idempotency (same request 5 times rapid)
		// ═══════════════════════════════════════════
		console.log('\n=== TEST 9: Rapid-fire idempotency (5x) ===');
		const order9 = createTestOrder();
		const rapidUrl = `http://localhost:8099/checkout/order-received/${order9.id}/?wchs_upsell_accept=1&order_id=${order9.id}&order_key=${order9.key}&token=${order9.token}`;
		await Promise.all([
			httpGet(rapidUrl),
			httpGet(rapidUrl),
			httpGet(rapidUrl),
			httpGet(rapidUrl),
			httpGet(rapidUrl),
		]);
		const after9 = getOrder(order9.id);
		assert('5x rapid: only 2 items (1 original + 1 upsell)', after9.item_count === 2);
		assert('5x rapid: status accepted', after9.upsell_status === 'accepted');

		// ═══════════════════════════════════════════
		// TEST 10: Missing parameters
		// ═══════════════════════════════════════════
		console.log('\n=== TEST 10: Missing parameters ===');
		const order10 = createTestOrder();
		// No token
		await httpGet(`http://localhost:8099/checkout/order-received/${order10.id}/?wchs_upsell_accept=1&order_id=${order10.id}&order_key=${order10.key}`);
		const after10a = getOrder(order10.id);
		assert('Missing token: status unchanged', after10a.upsell_status === 'pending');

		// No order_key
		await httpGet(`http://localhost:8099/checkout/order-received/${order10.id}/?wchs_upsell_accept=1&order_id=${order10.id}&token=${order10.token}`);
		const after10b = getOrder(order10.id);
		assert('Missing key: status unchanged', after10b.upsell_status === 'pending');

		// No order_id
		await httpGet(`http://localhost:8099/checkout/order-received/0/?wchs_upsell_accept=1&order_id=0&order_key=${order10.key}&token=${order10.token}`);
		const after10c = getOrder(order10.id);
		assert('Missing/zero order_id: status unchanged', after10c.upsell_status === 'pending');

	} catch (err) {
		console.error('FATAL:', err.message);
		console.error(err.stack);
		process.exitCode = 1;
	}

	console.log(`\n${'='.repeat(50)}`);
	console.log(`RESULTS: ${pass} passed, ${fail} failed`);
	results.forEach(r => console.log(`  ${r.s === 'PASS' ? '✓' : '✗'} ${r.name}`));
	console.log(`\n${fail === 0 ? '✓ ALL TESTS PASSED' : '✗ SOME TESTS FAILED'}`);
	if (fail > 0) process.exitCode = 1;
})();
