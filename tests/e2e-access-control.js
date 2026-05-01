// Adversarial access control tests
// Tests all 3 modes with direct API calls (no browser, no SPA UX layer)
// This tests the REAL security boundary, not the cosmetic SPA gate.
//
// Run: node tests/e2e-access-control.js

const http = require('http');
const path = require('path');
const { execSync } = require('child_process');

const ROOT = path.resolve(__dirname, '..');
const WP = 'http://localhost:8099';

let pass = 0, fail = 0, results = [];
function assert(name, ok) {
	if (ok) { pass++; results.push({ name, s: 'PASS' }); console.log(`    ✓ ${name}`); }
	else { fail++; results.push({ name, s: 'FAIL' }); console.log(`    ✗ ${name}`); }
}

function wp(php) {
	return execSync(
		`cd ${ROOT} && docker compose exec -T -u 33:33 wpcli php -d memory_limit=1024M /usr/local/bin/wp eval '${php.replace(/'/g, "'\\''")}'`,
		{ stdio: 'pipe' }
	).toString().trim();
}

function setMode(mode) {
	wp(`$s = \\WCHS\\Admin\\AdminPage::get_site_settings(); $s["access_mode"] = ${mode}; update_option("wchs_site_settings", $s);`);
}

function apiCall(method, endpoint, body) {
	return new Promise((resolve) => {
		const url = new URL(WP + endpoint);
		const opts = {
			hostname: url.hostname,
			port: url.port,
			path: url.pathname + url.search,
			method: method,
			headers: {
				'Content-Type': 'application/json',
				'Accept': 'application/json',
			},
		};
		const req = http.request(opts, (res) => {
			let data = '';
			res.on('data', d => data += d);
			res.on('end', () => {
				let json = null;
				try { json = JSON.parse(data); } catch (e) {}
				resolve({ status: res.statusCode, body: json, raw: data });
			});
		});
		req.on('error', () => resolve({ status: 0, body: null, raw: '' }));
		if (body) req.write(JSON.stringify(body));
		req.end();
	});
}

(async () => {
	try {
		// Get a product ID for testing
		const productId = wp(`echo wc_get_products(["limit"=>1,"status"=>"publish"])[0]->get_id();`);

		for (const mode of [1, 2, 3]) {
			console.log(`\n${'='.repeat(50)}`);
			console.log(`MODE ${mode}: ${mode === 1 ? 'LOCKED' : mode === 2 ? 'BROWSE-ONLY' : 'OPEN'}`);
			console.log('='.repeat(50));
			setMode(mode);

			// ── Always-allowed endpoints ──
			console.log('\n  Always-allowed:');
			const config = await apiCall('GET', '/wp-json/wchs/v1/config');
			assert('Config endpoint accessible', config.status === 200);
			assert('Config returns access_mode=' + mode, config.body?.access_mode === mode);

			const session = await apiCall('GET', '/wp-json/wchs/v1/session');
			assert('Session endpoint accessible', session.status === 200);
			assert('Session shows guest', session.body?.authenticated === false);

			// ── Product endpoints ──
			console.log('\n  Products (guest):');
			const products = await apiCall('GET', '/wp-json/wc/store/v1/products');
			if (mode === 1) {
				assert('Products blocked (403)', products.status === 403);
			} else {
				assert('Products accessible', products.status === 200);
			}

			const singleProduct = await apiCall('GET', `/wp-json/wc/store/v1/products/${productId}`);
			if (mode === 1) {
				assert('Single product blocked (403)', singleProduct.status === 403);
			} else {
				assert('Single product accessible', singleProduct.status === 200);
			}

			// ── Cart endpoints ──
			console.log('\n  Cart (guest):');
			const cartGet = await apiCall('GET', '/wp-json/wc/store/v1/cart');
			if (mode === 1) {
				assert('Cart GET blocked', cartGet.status === 403);
			} else {
				assert('Cart GET accessible', cartGet.status === 200 || cartGet.status === 404);
			}

			const cartAdd = await apiCall('POST', '/wp-json/wc/store/v1/cart/add-item', {
				id: parseInt(productId),
				quantity: 1,
			});
			if (mode === 1 || mode === 2) {
				assert('Cart add-item blocked (403)', cartAdd.status === 403);
			} else {
				// Mode 3 might fail for other reasons (nonce, etc) but shouldn't be 403
				assert('Cart add-item not access-blocked', cartAdd.status !== 403);
			}

			// ── Checkout endpoints ──
			console.log('\n  Checkout (guest):');
			const checkout = await apiCall('POST', '/wp-json/wc/store/v1/checkout', {});
			if (mode === 1 || mode === 2) {
				assert('Checkout blocked (403)', checkout.status === 403);
			} else {
				assert('Checkout not access-blocked', checkout.status !== 403);
			}

			// ── Reviews endpoint (requires product_id) ──
			// Mode 1: blocked (locked site). Mode 2+3: accessible (browsing includes reviews)
			console.log('\n  Reviews (guest):');
			const reviews = await apiCall('GET', `/wp-json/wchs/v1/reviews/${productId}`);
			if (mode === 1) {
				assert('Reviews blocked in locked mode', reviews.status === 403);
			} else {
				assert('Reviews accessible in browse/open mode', reviews.status === 200);
			}

			// ── My orders (always requires auth) ──
			console.log('\n  My orders (guest):');
			const orders = await apiCall('GET', '/wp-json/wchs/v1/my-orders');
			assert('My orders blocked for guest', orders.status === 401 || orders.status === 403);

			// ── Direct API bypass attempts ──
			console.log('\n  Bypass attempts:');

			// Try adding auth headers (fake)
			const fakeAuth = await apiCall('GET', '/wp-json/wc/store/v1/products');
			// This is the same as unauthenticated since we're not sending cookies

			// Try POST to products (shouldn't exist but check)
			const productPost = await apiCall('POST', '/wp-json/wc/store/v1/products', { name: 'hack' });
			assert('Product creation blocked', productPost.status === 403 || productPost.status === 401 || productPost.status === 404);

			// Try accessing WC REST API (admin endpoints) without auth
			const wcOrders = await apiCall('GET', '/wp-json/wc/v3/orders');
			assert('WC admin orders blocked', wcOrders.status === 401);

			// Try accessing address validation AJAX without nonce
			const avNoNonce = await apiCall('POST', '/wp-admin/admin-ajax.php?action=wchs_validate_address', {
				street1: '123 Test St',
				city: 'Denver',
				state: 'CO',
				zip: '80202',
			});
			assert('Address validation without nonce rejected', avNoNonce.status === 403 || avNoNonce.body?.success === false || (avNoNonce.raw && avNoNonce.raw.includes('-1')));
		}

		// ── Mode transition test ──
		console.log(`\n${'='.repeat(50)}`);
		console.log('MODE TRANSITION TEST');
		console.log('='.repeat(50));

		// Set mode 1, verify blocked
		setMode(1);
		const blocked = await apiCall('GET', '/wp-json/wc/store/v1/products');
		assert('Mode 1: products blocked', blocked.status === 403);

		// Switch to mode 3, verify immediately accessible
		setMode(3);
		const unblocked = await apiCall('GET', '/wp-json/wc/store/v1/products');
		assert('Mode 3: products immediately accessible', unblocked.status === 200);

		// Switch back to mode 1
		setMode(1);
		const reblocked = await apiCall('GET', '/wp-json/wc/store/v1/products');
		assert('Mode 1 again: products re-blocked', reblocked.status === 403);

		// ── 403 response body test ──
		console.log('\n  403 response body:');
		assert('403 includes access_mode', reblocked.body?.data?.access_mode === 1);

	} catch (err) {
		console.error('FATAL:', err.message);
		process.exitCode = 1;
	} finally {
		// Restore mode 3
		setMode(3);

		console.log(`\n${'='.repeat(50)}`);
		console.log(`RESULTS: ${pass} passed, ${fail} failed`);
		results.forEach(r => console.log(`  ${r.s === 'PASS' ? '✓' : '✗'} ${r.name}`));
		console.log(`\n${fail === 0 ? '✓ ALL TESTS PASSED' : '✗ SOME TESTS FAILED'}`);
		if (fail > 0) process.exitCode = 1;
	}
})();
