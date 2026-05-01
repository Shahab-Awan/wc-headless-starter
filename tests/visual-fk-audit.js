const { chromium } = require('./playwright');
const path = require('path');
const SHOTS = path.join(__dirname, 'screenshots', 'truth-audit');
const fs = require('fs');
fs.mkdirSync(SHOTS, { recursive: true });

(async () => {
    const b = await chromium.launch();
    const ctx = await b.newContext({ viewport: { width: 1440, height: 900 } });
    await ctx.route('**/*', route => {
        const h = route.request().headers();
        h['cache-control'] = 'no-cache, no-store';
        h['pragma'] = 'no-cache';
        delete h['if-none-match'];
        delete h['if-modified-since'];
        route.continue({ headers: h });
    });
    const p = await ctx.newPage();
    await p.goto('http://localhost:8099/wp-login.php');
    await p.fill('#user_login', 'admin');
    await p.fill('#user_pass', 'wchs-admin-dev');
    await Promise.all([p.waitForNavigation(), p.click('#wp-submit')]);

    const FAKE_PATTERNS = [
        'BackToSchool', 'WinterClearance', 'BlackFriday', 'CyberMonday',
        'Spring Sale', 'Summer Sale', 'Holiday Promo', 'Premium Membership',
        'Extended Warranty', 'Gift Wrapping', 'Expedited Shipping',
        'Installation Service', 'X/Twitter', 'StackOverflow',
    ];
    const FAKE_NUMBERS = ['4123.45', '5421.33', '1934.72', '5,974.42', '5974.42'];

    async function probe(label, urlPath, clickFirst) {
        await p.goto('http://localhost:8099/wp-admin/' + urlPath + '&_t=' + Date.now(), { waitUntil: 'networkidle' });
        await p.waitForTimeout(4000);
        if (clickFirst) {
            try {
                const loc = p.locator(clickFirst).first();
                if (await loc.count() > 0) await loc.click({ force: true });
                await p.waitForTimeout(3500);
            } catch {}
        }
        await p.screenshot({ path: path.join(SHOTS, label + '.png'), fullPage: true });
        const text = await p.evaluate(() => document.body.innerText || '');
        const found = {};
        for (const f of FAKE_PATTERNS) {
            const c = (text.match(new RegExp(f, 'gi')) || []).length;
            if (c > 0) found[f] = c;
        }
        for (const f of FAKE_NUMBERS) {
            const re = new RegExp(f.replace(/\./g, '\\.'), 'g');
            const c = (text.match(re) || []).length;
            if (c > 0) found[f] = c;
        }
        const status = Object.keys(found).length === 0 ? 'CLEAN' : 'FAKES: ' + JSON.stringify(found);
        console.log(label, '→', status);
        return found;
    }

    // Use the REAL URLs found via mapping
    await probe('01-dashboard',           'admin.php?page=bwf');
    await probe('02-analytics-overview',  'admin.php?page=bwf&path=/analytics');
    await probe('03-analytics-funnels',   'admin.php?page=bwf&path=/analytics/funnels');
    await probe('04-analytics-orders',    'admin.php?page=bwf&path=/analytics/orders');
    await probe('05-analytics-referrers', 'admin.php?page=bwf&path=/analytics/referrers');
    await probe('06-analytics-cart-upsell','admin.php?page=bwf&path=/analytics/cart-upsell');
    await probe('07-analytics-utm',       'admin.php?page=bwf&path=/analytics/utm-campaigns');
    await probe('08-funnel-1',            'admin.php?page=bwf&path=/funnels/1');
    await probe('09-funnel-1-analytics',  'admin.php?page=bwf&path=/funnels/1/analytics');
    await probe('10-store-checkout',      'admin.php?page=bwf&path=/store-checkout');
    await probe('11-templates',           'admin.php?page=bwf&path=/templates');
    await probe('12-settings',            'admin.php?page=bwf&path=/settings');
    await probe('13-automations',         'admin.php?page=bwf&path=/automations');

    await b.close();
})();
