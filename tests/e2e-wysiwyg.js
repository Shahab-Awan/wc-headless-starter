/**
 * E2E test: WYSIWYG editor + Typography round-trip
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

async function testTextBlockWysiwyg() {
  console.log('\n--- Text Block WYSIWYG ---');

  // Go to Pages tab
  await page.goto(`${WP_URL}/wp-admin/admin.php?page=wchs-settings&tab=pages`);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(500);

  // Find and click Edit on the first text_block row (the type label says "Text Block")
  const editBtns = await page.$$('.wchs-modlist__edit');
  const typeLabels = await page.$$('.wchs-modlist__type');

  let clicked = false;
  for (let i = 0; i < typeLabels.length; i++) {
    const text = await typeLabels[i].textContent();
    if (text.trim() === 'Text Block') {
      await editBtns[i].click();
      clicked = true;
      break;
    }
  }

  if (!clicked) {
    fail('Click text_block Edit', 'no Text Block module found');
    await page.screenshot({ path: 'tests/screenshots/no-textblock.png', fullPage: true });
    return;
  }

  // Wait for modal + TinyMCE init
  await page.waitForSelector('.wchs-modal-backdrop', { timeout: 3000 });
  ok('Modal opened for text_block');

  // TinyMCE is async — give it time to initialize
  await page.waitForTimeout(1500);

  // Check for TinyMCE iframe (its id pattern: wchs-mce-N_ifr)
  const mceIframe = await page.$('iframe[id*="wchs-mce"]');
  const mceToolbar = await page.$('.tox-toolbar, .mce-toolbar');
  const tinyContainer = await page.$('.tox-tinymce, .mce-tinymce');

  if (mceIframe || tinyContainer) {
    ok('TinyMCE initialized in text_block modal');

    // Try typing bold text
    if (mceIframe) {
      const frame = await mceIframe.contentFrame();
      if (frame) {
        await frame.click('body');
        // Select all and delete existing content
        await page.keyboard.press('Control+a');
        await page.keyboard.press('Delete');
        await page.keyboard.type('Normal text ');

        // Click Bold button in TinyMCE toolbar
        const boldBtn = await page.$('.tox-tbtn[title="Bold"], button[aria-label="Bold"]');
        if (boldBtn) {
          await boldBtn.click();
          await page.keyboard.type('BOLD TEXT');
          ok('Typed bold text via TinyMCE toolbar');

          // Click the bold button again to toggle off, type more
          await boldBtn.click();
          await page.keyboard.type(' after bold');
        } else {
          // Try alternate selector
          const altBold = await page.$('[aria-label="Bold"]');
          if (altBold) {
            await altBold.click();
            await page.keyboard.type('BOLD TEXT');
            ok('Typed bold text via TinyMCE toolbar (alt selector)');
          } else {
            fail('Bold button', 'could not find bold toolbar button');
            // Screenshot for debug
            await page.screenshot({ path: 'tests/screenshots/tinymce-no-bold.png' });
          }
        }

        // Save the module
        const saveBtn = await page.$('.wchs-modal__save');
        if (saveBtn) {
          await saveBtn.click();
          await page.waitForTimeout(500);
          ok('Module saved');
        }
      }
    }
  } else {
    fail('TinyMCE init', 'no TinyMCE container/iframe found in modal');

    // Debug: check if tinymce global exists, check textarea
    const debug = await page.evaluate(() => {
      return {
        tinymceExists: typeof tinymce !== 'undefined',
        tinymceEditors: typeof tinymce !== 'undefined' ? tinymce.get().map(e => e.id) : [],
        wysiwygTextareas: document.querySelectorAll('textarea[data-wysiwyg="1"]').length,
        modalVisible: !!document.querySelector('.wchs-modal-backdrop'),
        modalBodyHTML: document.querySelector('.wchs-modal__body')?.innerHTML?.substring(0, 300),
      };
    });
    console.log('  Debug:', JSON.stringify(debug, null, 2));
    await page.screenshot({ path: 'tests/screenshots/tinymce-fail.png', fullPage: true });
  }

  // Close modal if still open
  const closeBtn = await page.$('.wchs-modal__close');
  if (closeBtn) {
    try { await closeBtn.click(); } catch(e) {}
  }
  await page.waitForTimeout(300);
}

async function testAccordionWysiwyg() {
  console.log('\n--- Accordion WYSIWYG ---');

  await page.goto(`${WP_URL}/wp-admin/admin.php?page=wchs-settings&tab=pages`);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(500);

  // Find the accordion module
  const editBtns = await page.$$('.wchs-modlist__edit');
  const typeLabels = await page.$$('.wchs-modlist__type');

  let clicked = false;
  for (let i = 0; i < typeLabels.length; i++) {
    const text = await typeLabels[i].textContent();
    if (text.trim() === 'Accordion') {
      await editBtns[i].click();
      clicked = true;
      break;
    }
  }

  if (!clicked) {
    fail('Click accordion Edit', 'no Accordion module found');
    // Debug what types exist
    const types = [];
    for (const label of typeLabels) {
      types.push(await label.textContent());
    }
    console.log('  Module types found:', types);
    return;
  }

  await page.waitForSelector('.wchs-modal-backdrop', { timeout: 3000 });
  ok('Modal opened for accordion');

  await page.waitForTimeout(1500);

  // Check for TinyMCE instances (should be one per accordion answer)
  const mceIframes = await page.$$('iframe[id*="wchs-mce"]');
  const mceCount = mceIframes.length;

  if (mceCount > 0) {
    ok(`TinyMCE initialized for ${mceCount} accordion answer(s)`);
  } else {
    const debug = await page.evaluate(() => {
      return {
        tinymceExists: typeof tinymce !== 'undefined',
        editors: typeof tinymce !== 'undefined' ? tinymce.get().map(e => e.id) : [],
        wysiwygTA: document.querySelectorAll('textarea[data-wysiwyg="1"]').length,
      };
    });
    fail('Accordion TinyMCE', `no iframes found. Debug: ${JSON.stringify(debug)}`);
    await page.screenshot({ path: 'tests/screenshots/accordion-fail.png', fullPage: true });
  }

  // Close modal
  const closeBtn = await page.$('.wchs-modal__close');
  if (closeBtn) { try { await closeBtn.click(); } catch(e) {} }
  await page.waitForTimeout(300);
}

async function testTypographySave() {
  console.log('\n--- Typography Save ---');

  await page.goto(`${WP_URL}/wp-admin/admin.php?page=wchs-settings&tab=design`);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(500);

  // Expand Typography section if collapsed
  const sectionToggles = await page.$$('h2.wchs-section__toggle');
  for (const toggle of sectionToggles) {
    const text = await toggle.textContent();
    if (text.includes('Typography')) {
      // Check if the section body is visible
      const section = await toggle.evaluateHandle(el => el.closest('.wchs-section'));
      const isCollapsed = await section.evaluate(el => {
        const body = el.querySelector('.wchs-section__body');
        return body && (getComputedStyle(body).display === 'none' || body.offsetHeight === 0);
      });
      if (isCollapsed) {
        await toggle.click();
        await page.waitForTimeout(300);
      }
      ok('Typography section found');
      break;
    }
  }

  // Set non-default values
  await page.selectOption('select[name="typography_heading_font"]', 'playfair');
  await page.selectOption('select[name="typography_heading_weight"]', 'bold');
  await page.selectOption('select[name="typography_body_font"]', 'space_grotesk');
  await page.selectOption('select[name="typography_body_size"]', 'l');

  // Save
  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(500);
  ok('Saved typography (playfair/bold/space_grotesk/l)');

  // Verify via REST
  const resp = await page.request.get(`${WP_URL}/wp-json/wchs/v1/config`);
  const config = await resp.json();
  const t = config.typography;

  if (t?.heading_font === 'playfair' && t?.heading_weight === 'bold' &&
      t?.body_font === 'space_grotesk' && t?.body_size === 'l') {
    ok('Typography REST round-trip verified');
  } else {
    fail('Typography REST', JSON.stringify(t));
  }

  // Reset to defaults
  await page.goto(`${WP_URL}/wp-admin/admin.php?page=wchs-settings&tab=design`);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(500);
  // Expand typography section again
  for (const toggle of await page.$$('h2.wchs-section__toggle')) {
    if ((await toggle.textContent()).includes('Typography')) {
      const sec = await toggle.evaluateHandle(el => el.closest('.wchs-section'));
      const collapsed = await sec.evaluate(el => {
        const b = el.querySelector('.wchs-section__body');
        return b && (getComputedStyle(b).display === 'none' || b.offsetHeight === 0);
      });
      if (collapsed) { await toggle.click(); await page.waitForTimeout(300); }
      break;
    }
  }
  await page.selectOption('select[name="typography_heading_font"]', 'inter');
  await page.selectOption('select[name="typography_body_font"]', 'inter');
  await page.selectOption('select[name="typography_heading_weight"]', 'semibold');
  await page.selectOption('select[name="typography_body_size"]', 'm');
  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle');
  ok('Reset typography to defaults');
}

async function testSpaHtmlRendering() {
  console.log('\n--- SPA HTML Rendering ---');

  await page.goto(`${SPA_URL}/shipping-policy`);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(3000); // SPA hydration + config fetch

  const accordion = await page.$('.accordion');
  if (!accordion) {
    fail('Accordion render', 'no .accordion element on /shipping-policy');
    await page.screenshot({ path: 'tests/screenshots/spa-no-accordion.png', fullPage: true });
    return;
  }
  ok('Accordion rendered on shipping-policy');

  // Expand first item
  const trigger = await page.$('.accordion__trigger');
  if (trigger) await trigger.click();
  await page.waitForTimeout(400);

  // Check for HTML elements in the answer
  const hasStrong = await page.$('.accordion__answer--html strong');
  const hasLink = await page.$('.accordion__answer--html a');
  const hasList = await page.$('.accordion__answer--html ul');

  if (hasStrong) ok('<strong> renders in accordion answer');
  else fail('<strong> render', 'not found');

  if (hasLink) ok('<a> renders in accordion answer');
  else fail('<a> render', 'not found');

  if (hasList) ok('<ul> renders in accordion answer');
  else fail('<ul> render', 'not found');

  await page.screenshot({ path: 'tests/screenshots/spa-accordion.png', fullPage: true });
}

async function testSpaTypographyCssVars() {
  console.log('\n--- SPA Typography CSS Vars ---');

  await page.goto(`${SPA_URL}/shipping-policy`);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(3000);

  const vars = await page.evaluate(() => {
    const cs = getComputedStyle(document.documentElement);
    return {
      fontHeading: cs.getPropertyValue('--font-heading').trim(),
      headingWeight: cs.getPropertyValue('--heading-weight').trim(),
      bodySize: cs.getPropertyValue('--body-size').trim(),
    };
  });

  if (vars.headingWeight) ok(`--heading-weight set: "${vars.headingWeight}"`);
  else fail('--heading-weight', 'not set');

  if (vars.bodySize) ok(`--body-size set: "${vars.bodySize}"`);
  else fail('--body-size', 'not set');

  if (vars.fontHeading) ok(`--font-heading set: "${vars.fontHeading.substring(0, 40)}..."`);
  else fail('--font-heading', 'not set');

  // Check a heading element actually uses the var
  const headingInfo = await page.evaluate(() => {
    const el = document.querySelector('.accordion__title');
    if (!el) return null;
    return { fontFamily: getComputedStyle(el).fontFamily.substring(0, 50) };
  });

  if (headingInfo) ok(`Accordion title font: ${headingInfo.fontFamily}`);
  else fail('Heading font check', 'no .accordion__title found');
}

(async () => {
  console.log('WYSIWYG + Typography E2E Tests');
  console.log('==============================');

  browser = await chromium.launch({ headless: true });
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  page = await ctx.newPage();

  try {
    await login();
    ok('Admin login');
    await testTextBlockWysiwyg();
    await testAccordionWysiwyg();
    await testTypographySave();
    await testSpaHtmlRendering();
    await testSpaTypographyCssVars();
  } catch (e) {
    fail('Unexpected', e.message);
    console.error(e.stack);
    await page.screenshot({ path: 'tests/screenshots/crash.png', fullPage: true });
  } finally {
    await browser.close();
  }

  console.log(`\n==============================`);
  console.log(`Results: ${passed} passed, ${failed} failed`);
  process.exit(failed > 0 ? 1 : 0);
})();
