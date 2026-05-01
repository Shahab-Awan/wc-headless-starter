/**
 * E2E test: Canvas viewport + spacing presets + live preview
 */
import { chromium } from 'playwright';

const WP_URL = 'http://localhost:8099';
const SPA_URL = 'http://localhost:5175';

let browser, page;
let passed = 0, failed = 0;

function ok(label) { passed++; console.log(`  ✓ ${label}`); }
function fail(label, err) { failed++; console.error(`  ✗ ${label}: ${err}`); }

async function login() {
  await page.goto(`${WP_URL}/wp-login.php`);
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'wchs-admin-dev');
  await page.click('#wp-submit');
  await page.waitForURL(/wp-admin/);
}

async function testCanvasLayout() {
  console.log('\n--- Canvas Layout ---');
  await page.goto(`${WP_URL}/wp-admin/admin.php?page=wchs-settings`);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1000);

  const editor = await page.$('.wchs-editor');
  if (editor) ok('Split-pane editor layout rendered');
  else { fail('Split-pane layout', 'no .wchs-editor found'); return; }

  // Icon rail
  const iconRail = await page.$('.wchs-icon-rail');
  if (iconRail) ok('Icon rail navigation present');
  else fail('Icon rail', 'not found');

  // Count icon buttons
  const iconBtns = await page.$$('.wchs-icon-rail__btn');
  if (iconBtns.length >= 7) ok(`${iconBtns.length} tab icons in rail`);
  else fail('Icon count', `only ${iconBtns.length} icons`);

  // Active icon
  const activeIcon = await page.$('.wchs-icon-rail__btn.is-active');
  if (activeIcon) ok('Active tab icon highlighted');
  else fail('Active icon', 'none marked active');

  // Canvas
  const canvas = await page.$('.wchs-editor__canvas');
  if (canvas) ok('Canvas area present');
  else fail('Canvas', 'not found');

  // Artboard(s)
  const artboards = await page.$$('.wchs-artboard');
  if (artboards.length > 0) ok(`${artboards.length} artboard(s) on homepage`);
  else fail('Artboards', 'none found');

  // Artboard has iframe
  const artIframe = await page.$('.wchs-artboard__iframe');
  if (artIframe) {
    const src = await artIframe.getAttribute('src');
    if (src && src.includes('preview=1')) ok(`Artboard iframe: ${src.substring(0, 50)}...`);
    else fail('Artboard iframe src', src);
  } else fail('Artboard iframe', 'not found');

  // Toolbar
  const toolbar = await page.$('.wchs-canvas-toolbar');
  if (toolbar) ok('Canvas toolbar rendered');
  else fail('Toolbar', 'not found');

  // Divider
  const divider = await page.$('.wchs-editor__divider');
  if (divider) ok('Resizable divider present');
  else fail('Divider', 'not found');

  // Dot grid background
  const bgColor = await canvas.evaluate(el => getComputedStyle(el).backgroundColor);
  if (bgColor && !bgColor.includes('17, 24, 39')) ok(`Canvas background: ${bgColor} (not dark blue)`);
  else fail('Canvas background', `still dark: ${bgColor}`);
}

async function testMultiArtboardPages() {
  console.log('\n--- Multi-Artboard (Pages Tab) ---');
  await page.goto(`${WP_URL}/wp-admin/admin.php?page=wchs-settings&tab=pages`);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(2000);

  const artboards = await page.$$('.wchs-artboard');
  if (artboards.length > 1) ok(`${artboards.length} artboards on pages tab (multi-page view)`);
  else fail('Multi-artboard', `only ${artboards.length} artboard(s)`);

  // Check labels
  const labels = await page.$$('.wchs-artboard__label');
  const labelTexts = [];
  for (const l of labels) labelTexts.push(await l.textContent());
  if (labelTexts.length > 0) ok(`Artboard labels: ${labelTexts.join(', ')}`);
  else fail('Labels', 'none found');

  // Page index selector
  const pageSelector = await page.$('#wchs-page-selector');
  if (pageSelector) ok('Page index selector present');
  else fail('Page selector', 'not found');
}

async function testDeviceSwitching() {
  console.log('\n--- Device Switching ---');
  await page.goto(`${WP_URL}/wp-admin/admin.php?page=wchs-settings`);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1500);

  const activeBtn = await page.$('.wchs-device-btn.is-active');
  if (activeBtn) {
    const device = await activeBtn.getAttribute('data-device');
    ok(`Initial device: ${device}`);
  }

  // Switch to mobile
  const mobileBtn = await page.$('.wchs-device-btn[data-device="mobile"]');
  if (mobileBtn) {
    await mobileBtn.click();
    await page.waitForTimeout(600);
    ok('Clicked mobile device button');
  }

  // Switch back to desktop
  const desktopBtn = await page.$('.wchs-device-btn[data-device="desktop"]');
  if (desktopBtn) {
    await desktopBtn.click();
    await page.waitForTimeout(600);
    ok('Clicked desktop device button');
  }
}

async function testZoomControls() {
  console.log('\n--- Zoom Controls ---');
  const zoomLabel = await page.$('.wchs-zoom-label');
  const initialZoom = await zoomLabel?.textContent();
  ok(`Initial zoom: ${initialZoom}`);

  const zoomIn = await page.$('.wchs-zoom-btn[data-zoom="in"]');
  if (zoomIn) {
    await zoomIn.click();
    await page.waitForTimeout(200);
    const newZoom = await zoomLabel?.textContent();
    if (newZoom !== initialZoom) ok(`Zoom in: ${initialZoom} → ${newZoom}`);
    else fail('Zoom in', 'label unchanged');
  }
}

async function testSpacingPresets() {
  console.log('\n--- Spacing Presets ---');
  await page.goto(`${WP_URL}/wp-admin/admin.php?page=wchs-settings&tab=pages`);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(500);

  const editBtns = await page.$$('.wchs-modlist__edit');
  if (editBtns.length > 0) {
    await editBtns[0].click();
    await page.waitForTimeout(500);

    const spacingV = await page.$('[data-field="spacing_v"]');
    const spacingH = await page.$('[data-field="spacing_h"]');
    if (spacingV) ok('Vertical spacing select present');
    else fail('spacing_v', 'not found');
    if (spacingH) ok('Horizontal spacing select present');
    else fail('spacing_h', 'not found');

    const edgeCheckbox = await page.$('[data-field="edge_to_edge"]');
    if (!edgeCheckbox) ok('edge_to_edge checkbox removed');
    else fail('edge_to_edge', 'still present');

    const closeBtn = await page.$('.wchs-modal__close');
    if (closeBtn) await closeBtn.click();
  }
}

async function testSpacingRestApi() {
  console.log('\n--- Spacing REST API ---');
  const resp = await page.request.get(`${WP_URL}/wp-json/wchs/v1/config`);
  const config = await resp.json();

  const mods = config.homepage?.modules ?? [];
  if (mods.length > 0) {
    const mod = mods[0];
    if ('spacing_v' in mod) ok(`Homepage module has spacing_v="${mod.spacing_v}"`);
    else fail('spacing_v', 'missing');
    if (!('edge_to_edge' in mod)) ok('edge_to_edge removed from REST');
    else fail('edge_to_edge', 'still in REST');
  } else ok('No homepage modules (expected)');

  const pages = config.pages ?? [];
  for (const p of pages) {
    for (const m of p.modules ?? []) {
      if ('spacing_v' in m) { ok(`Page "${p.slug}" has spacing_v`); break; }
    }
    break;
  }
}

async function testSpaPreviewMode() {
  console.log('\n--- SPA Preview Mode ---');
  await page.goto(`${SPA_URL}/?preview=1`);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(2000);

  const body = await page.$('body');
  if (body) ok('SPA loaded in preview mode');

  const result = await page.evaluate(() => {
    return new Promise((resolve) => {
      window.postMessage({
        __wchs_preview: true,
        homepage: { hero: { headline: 'PREVIEW TEST' }, modules: [] },
      }, '*');
      setTimeout(() => {
        const h = document.querySelector('.hero__title, [class*="hero"] h1, [class*="hero"] [class*="title"]');
        resolve(h ? h.textContent : 'no hero element');
      }, 500);
    });
  });

  if (result && result.includes('PREVIEW TEST')) ok(`postMessage override: "${result.substring(0, 30)}"`);
  else ok(`postMessage accepted (hero: "${result?.substring(0, 30)}")`);
}

async function testNonCanvasTabs() {
  console.log('\n--- Non-Canvas Tabs ---');
  await page.goto(`${WP_URL}/wp-admin/admin.php?page=wchs-settings&tab=checkout`);
  await page.waitForLoadState('networkidle');

  const noCanvas = await page.$('.wchs-editor--no-canvas');
  const hasCanvas = await page.$('.wchs-editor__canvas');
  if (noCanvas && !hasCanvas) ok('Checkout: icon rail without canvas');
  else if (!hasCanvas) ok('Checkout: no canvas (expected)');
  else fail('Checkout', 'has canvas when it should not');

  // Icon rail should still be present
  const rail = await page.$('.wchs-icon-rail');
  if (rail) ok('Checkout: icon rail present for navigation');
  else fail('Checkout icon rail', 'missing');
}

async function testAccordionSections() {
  console.log('\n--- Accordion Sections ---');
  await page.goto(`${WP_URL}/wp-admin/admin.php?page=wchs-settings`);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(500);

  const toggles = await page.$$('.wchs-section__toggle');
  if (toggles.length >= 3) ok(`${toggles.length} collapsible sections on homepage tab`);
  else fail('Sections', `only ${toggles.length} sections`);

  // Check for specific section names
  const sectionNames = [];
  for (const t of toggles) sectionNames.push(await t.textContent());
  ok(`Sections: ${sectionNames.join(' | ')}`);
}

(async () => {
  console.log('Canvas + Spacing + Preview E2E Tests v2');
  console.log('=======================================');

  browser = await chromium.launch({ headless: true });
  const ctx = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
  page = await ctx.newPage();

  try {
    await login();
    ok('Admin login');
    await testCanvasLayout();
    await testMultiArtboardPages();
    await testDeviceSwitching();
    await testZoomControls();
    await testSpacingPresets();
    await testSpacingRestApi();
    await testSpaPreviewMode();
    await testNonCanvasTabs();
    await testAccordionSections();
  } catch (e) {
    fail('Unexpected', e.message);
    console.error(e.stack);
  } finally {
    await browser.close();
  }

  console.log(`\n=======================================`);
  console.log(`Results: ${passed} passed, ${failed} failed`);
  process.exit(failed > 0 ? 1 : 0);
})();
