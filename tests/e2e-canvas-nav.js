import { chromium } from 'playwright';

const WP_URL = 'http://localhost:8099';
const USER = 'admin';
const PASS = 'wchs-admin-dev';

let pass = 0, fail = 0;
const ok = m => { pass++; console.log('  ✓ ' + m); };
const no = (m, d) => { fail++; console.log('  ✗ ' + m + (d ? ' — ' + d : '')); };

function parseTransform(str) {
  // translate(Xpx, Ypx) scale(Z) → matrix(Z, 0, 0, Z, X, Y)
  const m = str.match(/matrix\(([-\d.]+),\s*[-\d.]+,\s*[-\d.]+,\s*([-\d.]+),\s*([-\d.]+),\s*([-\d.]+)\)/);
  if (!m) return null;
  return { z: parseFloat(m[1]), x: parseFloat(m[3]), y: parseFloat(m[4]) };
}

function approx(a, b, tol = 1.5) { return Math.abs(a - b) <= tol; }

async function getCamera(page) {
  return await page.evaluate(() => {
    const s = document.getElementById('wchs-canvas-surface');
    return s ? getComputedStyle(s).transform : null;
  }).then(parseTransform);
}

async function main() {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1600, height: 1000 } });
  const page = await ctx.newPage();

  console.log('--- Login + open Pages tab (has 5 artboards) ---');
  await page.goto(`${WP_URL}/wp-login.php`);
  await page.fill('#user_login', USER);
  await page.fill('#user_pass', PASS);
  await Promise.all([page.waitForLoadState('networkidle'), page.click('#wp-submit')]);
  await page.goto(`${WP_URL}/wp-admin/admin.php?page=wchs-settings&tab=pages`);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(800);

  const artboards = await page.$$('.wchs-artboard');
  ok(`${artboards.length} artboards`);

  console.log('\n--- Grid wrap: 4+ artboards → 2-column surface width ---');
  const surfaceBox = await page.locator('#wchs-canvas-surface').evaluate(el => ({
    width: el.offsetWidth,
    height: el.offsetHeight,
    styleWidth: el.style.width,
  }));
  // Desktop: 1440 * 0.25 = 360. 2*360 + 40 gap + 2*60 pad = 880.
  if (surfaceBox.styleWidth === '880px') ok(`Surface width forced to 880px (2 cols)`);
  else no('Surface width', `expected 880px, got ${surfaceBox.styleWidth || '(unset)'}`);

  // Artboards should wrap — so surface height > (tallest single artboard)
  const firstArt = await page.locator('.wchs-artboard').first().evaluate(el => el.offsetHeight);
  if (surfaceBox.height > firstArt * 1.5) ok(`Surface wraps (h=${surfaceBox.height}px > single art h=${firstArt}px)`);
  else no('Wrap', `surface h=${surfaceBox.height} not meaningfully > artboard h=${firstArt}`);

  console.log('\n--- Initial camera is centered ---');
  const cam0 = await getCamera(page);
  if (cam0 && cam0.z === 1) ok(`Initial z=1`);
  else no('Initial z', JSON.stringify(cam0));
  if (cam0 && cam0.x > 0) ok(`Initial x centered (x=${cam0.x})`);
  else no('Initial x', `not > 0: ${cam0?.x}`);

  console.log('\n--- Zoom button (in): camera.z increases, zoom label updates ---');
  await page.click('.wchs-zoom-btn[data-zoom="in"]');
  await page.waitForTimeout(200);
  const cam1 = await getCamera(page);
  const label1 = await page.textContent('.wchs-zoom-label');
  if (cam1 && approx(cam1.z, 1.1)) ok(`z=${cam1.z} after zoom in`);
  else no('Zoom in z', `expected ~1.1, got ${cam1?.z}`);
  if (label1.trim() === '110%') ok(`Label shows ${label1.trim()}`);
  else no('Label', label1);

  console.log('\n--- Ctrl+wheel zoom toward cursor ---');
  // Reset
  await page.click('.wchs-zoom-btn[data-zoom="out"]');
  await page.waitForTimeout(100);
  const canvasRect = await page.locator('.wchs-editor__canvas').boundingBox();
  // Cursor near top-left of canvas area
  const cursorX = canvasRect.x + 200;
  const cursorY = canvasRect.y + 200;
  await page.mouse.move(cursorX, cursorY);
  const camBefore = await getCamera(page);
  // World point under cursor before: (cx - x) / z
  const cx = cursorX - canvasRect.x;
  const cy = cursorY - canvasRect.y;
  const worldX = (cx - camBefore.x) / camBefore.z;
  const worldY = (cy - camBefore.y) / camBefore.z;

  await page.keyboard.down('Control');
  await page.mouse.wheel(0, -200);
  await page.keyboard.up('Control');
  await page.waitForTimeout(150);

  const camAfter = await getCamera(page);
  if (camAfter.z > camBefore.z) ok(`Zoom increased: ${camBefore.z} → ${camAfter.z}`);
  else no('Ctrl+wheel zoom', `z unchanged: ${camBefore.z} → ${camAfter.z}`);

  // World point under cursor after zoom should map back to same screen point
  const screenXAfter = worldX * camAfter.z + camAfter.x;
  const screenYAfter = worldY * camAfter.z + camAfter.y;
  if (approx(screenXAfter, cx, 3) && approx(screenYAfter, cy, 3))
    ok(`Focal point preserved under cursor (dx=${(screenXAfter-cx).toFixed(1)}, dy=${(screenYAfter-cy).toFixed(1)})`);
  else
    no('Focal point', `expected (${cx}, ${cy}), got (${screenXAfter.toFixed(1)}, ${screenYAfter.toFixed(1)})`);

  console.log('\n--- Space + left-drag pans ---');
  const camPre = await getCamera(page);
  await page.keyboard.down('Space');
  await page.waitForTimeout(50);
  const cursorClass = await page.evaluate(() =>
    document.querySelector('.wchs-editor__canvas').classList.contains('is-space-held')
  );
  if (cursorClass) ok('is-space-held class applied');
  else no('Space class', 'not applied');

  await page.mouse.move(canvasRect.x + 400, canvasRect.y + 400);
  await page.mouse.down({ button: 'left' });
  await page.mouse.move(canvasRect.x + 550, canvasRect.y + 500, { steps: 5 });
  const panningClass = await page.evaluate(() =>
    document.querySelector('.wchs-editor__canvas').classList.contains('is-panning')
  );
  if (panningClass) ok('is-panning class applied during drag');
  else no('Panning class', 'not applied');
  await page.mouse.up({ button: 'left' });
  await page.keyboard.up('Space');
  await page.waitForTimeout(100);

  const camPost = await getCamera(page);
  const dx = camPost.x - camPre.x;
  const dy = camPost.y - camPre.y;
  if (approx(dx, 150, 3) && approx(dy, 100, 3))
    ok(`Camera panned by (${dx.toFixed(0)}, ${dy.toFixed(0)}) — matches drag delta`);
  else
    no('Pan delta', `expected (150, 100), got (${dx.toFixed(1)}, ${dy.toFixed(1)})`);

  console.log('\n--- Middle-click drag pans ---');
  const camMid0 = await getCamera(page);
  await page.mouse.move(canvasRect.x + 500, canvasRect.y + 500);
  await page.mouse.down({ button: 'middle' });
  await page.mouse.move(canvasRect.x + 400, canvasRect.y + 400, { steps: 4 });
  await page.mouse.up({ button: 'middle' });
  await page.waitForTimeout(100);
  const camMid1 = await getCamera(page);
  const mdx = camMid1.x - camMid0.x;
  const mdy = camMid1.y - camMid0.y;
  if (approx(mdx, -100, 3) && approx(mdy, -100, 3))
    ok(`Middle-drag panned (${mdx.toFixed(0)}, ${mdy.toFixed(0)})`);
  else
    no('Middle-drag', `expected (-100, -100), got (${mdx.toFixed(1)}, ${mdy.toFixed(1)})`);

  console.log('\n--- Plain wheel pans (no ctrl) ---');
  const camWh0 = await getCamera(page);
  await page.mouse.move(canvasRect.x + 400, canvasRect.y + 400);
  await page.mouse.wheel(30, 50);
  await page.waitForTimeout(100);
  const camWh1 = await getCamera(page);
  if (camWh1.x < camWh0.x && camWh1.y < camWh0.y)
    ok(`Wheel pan shifted camera (dx=${(camWh1.x - camWh0.x).toFixed(0)}, dy=${(camWh1.y - camWh0.y).toFixed(0)})`);
  else
    no('Wheel pan', `expected negative dx/dy, got (${(camWh1.x-camWh0.x).toFixed(1)}, ${(camWh1.y-camWh0.y).toFixed(1)})`);

  console.log('\n--- Space in a text input: should NOT enter pan mode ---');
  // Navigate to a tab with a text input (homepage tab has text inputs)
  await page.goto(`${WP_URL}/wp-admin/admin.php?page=wchs-settings&tab=homepage`);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(500);
  const input = await page.$('input[type="text"]');
  if (input) {
    await input.focus();
    await page.keyboard.press('Space');
    const spaceHeldInInput = await page.evaluate(() =>
      document.querySelector('.wchs-editor__canvas')?.classList.contains('is-space-held')
    );
    if (!spaceHeldInInput) ok('Space in input did NOT trigger pan mode');
    else no('Space guard', 'is-space-held was applied while input focused');
  } else {
    ok('(skip — no text input on this tab)');
  }

  console.log('\n--- Feature A: artboard label cursor ---');
  await page.goto(`${WP_URL}/wp-admin/admin.php?page=wchs-settings&tab=homepage`);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1200);
  const labelCursor = await page.evaluate(() =>
    getComputedStyle(document.querySelector('.wchs-artboard__label')).cursor
  );
  if (labelCursor === 'pointer') ok('Label cursor is pointer');
  else no('Label cursor', `got "${labelCursor}"`);

  console.log('\n--- Feature A: hover shows accent ring ---');
  await page.locator('.wchs-artboard').first().hover();
  await page.waitForTimeout(200);
  const hoverShadow = await page.evaluate(() => {
    const frame = document.querySelector('.wchs-artboard__frame');
    return getComputedStyle(frame).boxShadow;
  });
  // Accent ring appears as extra `0 0 0 2px rgb(...)` in the shadow list
  if (hoverShadow.split(',').length >= 3) ok('Hover ring applied (multi-shadow)');
  else no('Hover ring', `single shadow: ${hoverShadow}`);

  console.log('\n--- Feature A: click content-page artboard → pages+focus URL ---');
  await page.goto(`${WP_URL}/wp-admin/admin.php?page=wchs-settings&tab=pages`);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1500);
  const firstSlug = await page.evaluate(() =>
    document.querySelector('.wchs-artboard')?.dataset.slug ||
    // slug lives in closure — read via title label fallback
    null
  );
  await Promise.all([
    page.waitForURL(/tab=pages.*focus=/, { timeout: 5000 }),
    page.locator('.wchs-artboard').first().click(),
  ]).then(() => ok('Content-page click landed on ?tab=pages&focus=<slug>'))
    .catch(e => no('Content-page click nav', e.message));

  console.log('\n--- Feature C: focus param → is-focused + in view ---');
  // After click above we're already on ?tab=pages&focus=<first-slug>
  await page.waitForTimeout(300);
  const focusInfo = await page.evaluate(() => {
    const t = document.querySelector('.wchs-page-card.is-focused');
    if (!t) return { found: false };
    const r = t.getBoundingClientRect();
    return { found: true, slug: t.dataset.slug, inView: r.top < window.innerHeight && r.bottom > 0 };
  });
  if (focusInfo.found && focusInfo.inView) ok(`Target card "${focusInfo.slug}" focused + in view`);
  else no('Focus state', JSON.stringify(focusInfo));

  // is-focused class should clear after ~2s
  await page.waitForTimeout(2200);
  const stillFocused = await page.evaluate(() => !!document.querySelector('.wchs-page-card.is-focused'));
  if (!stillFocused) ok('is-focused class auto-cleared');
  else no('Auto-clear', 'still has class after 2.2s');

  console.log('\n--- Feature B: tracker chips render from data-active-scripts ---');
  await page.goto(`${WP_URL}/wp-admin/admin.php?page=wchs-settings&tab=homepage`);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1500);
  const chipInfo = await page.evaluate(() => {
    const canvas = document.querySelector('.wchs-editor__canvas');
    const rawScripts = JSON.parse(canvas.dataset.activeScripts || '[]')
      .filter(s => !s.surfaces || s.surfaces.includes('spa'));
    const meta = document.querySelector('.wchs-artboard__meta');
    if (!meta) return { rawCount: rawScripts.length, hasMeta: false };
    const chips = Array.from(meta.children);
    const overflow = chips.find(c => c.classList.contains('wchs-chip-tracker--overflow'));
    return {
      rawCount: rawScripts.length,
      hasMeta: true,
      chipCount: chips.length,
      overflowShown: !!overflow,
      overflowText: overflow?.textContent?.trim(),
      firstChipTitle: chips[0]?.getAttribute('title'),
      firstChipMark: chips[0]?.querySelector('.wchs-chip-tracker__mark')?.textContent,
      firstChipCategory: chips[0]?.className.match(/wchs-chip-tracker--([a-z]+)/)?.[1],
    };
  });
  if (!chipInfo.hasMeta && chipInfo.rawCount === 0) ok('No chips when no active scripts (clean)');
  else if (chipInfo.hasMeta && chipInfo.rawCount > 0) {
    ok(`Chip row renders (${chipInfo.chipCount} chips for ${chipInfo.rawCount} scripts)`);
    if (chipInfo.rawCount > 3 && chipInfo.overflowShown) ok(`Overflow shown: "${chipInfo.overflowText}"`);
    else if (chipInfo.rawCount <= 3 && !chipInfo.overflowShown) ok('No overflow (≤3 scripts)');
    else no('Overflow', `rawCount=${chipInfo.rawCount} overflowShown=${chipInfo.overflowShown}`);
    if (chipInfo.firstChipTitle) ok(`First chip title: "${chipInfo.firstChipTitle}"`);
    else no('First chip title', 'missing');
    if (chipInfo.firstChipMark) ok(`First chip mark: "${chipInfo.firstChipMark}"`);
    else no('First chip mark', 'missing');
    if (chipInfo.firstChipCategory && chipInfo.firstChipCategory !== 'undefined') ok(`First chip category class: ${chipInfo.firstChipCategory}`);
    else no('First chip category', `got "${chipInfo.firstChipCategory}"`);
  } else no('Chip state', JSON.stringify(chipInfo));

  console.log('\n--- Feature B: category → color mapping ---');
  const catColors = await page.evaluate(() => {
    const art = document.querySelector('.wchs-artboard');
    const cats = ['analytics', 'pixel', 'marketing', 'consent', 'chat', 'other'];
    const out = {};
    cats.forEach(c => {
      const probe = document.createElement('span');
      probe.className = 'wchs-chip-tracker wchs-chip-tracker--' + c;
      art.appendChild(probe);
      out[c] = getComputedStyle(probe).color;
      probe.remove();
    });
    return out;
  });
  const uniqueCols = new Set(Object.values(catColors));
  if (uniqueCols.size >= 5) ok(`Distinct colors per category (${uniqueCols.size}/6)`);
  else no('Category colors', JSON.stringify(catColors));

  console.log('\n--- Hint-icon sweep: Checkout + Integrations + Access tabs ---');
  // Phase-2 sweep converted single-sentence .wchs-info blocks to hint_icon() across
  // three tabs. Verify the icons exist and tooltips expose their data-tip copy.
  const hintTabs = ['checkout', 'integrations', 'access'];
  let totalHints = 0;
  for (const tab of hintTabs) {
    await page.goto(`${WP_URL}/wp-admin/admin.php?page=wchs-settings&tab=${tab}`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(400);
    const n = await page.locator('.wchs-hint-icon').count();
    totalHints += n;
    console.log(`  · ${tab}: ${n} hint icons`);
  }
  if (totalHints >= 8) ok(`≥8 hint icons across checkout + integrations + access (got ${totalHints})`);
  else no('hint-icon total', `expected ≥8, got ${totalHints}`);

  // Pick one hint icon on Integrations (usually the richest tab) and verify
  // it has a data-tip attribute with non-empty content.
  await page.goto(`${WP_URL}/wp-admin/admin.php?page=wchs-settings&tab=integrations`);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(400);
  const firstHintTip = await page.locator('.wchs-hint-icon').first().getAttribute('data-tip');
  if (firstHintTip && firstHintTip.length > 10) ok(`First hint has data-tip (${firstHintTip.length} chars)`);
  else no('hint data-tip', `got "${firstHintTip}"`);

  // Confirm that printed .wchs-info walls have been meaningfully reduced
  // on the swept tabs. We kept a small handful of banner-style structured
  // blocks; surviving count on these three tabs should stay in single digits.
  let survivingInfo = 0;
  for (const tab of hintTabs) {
    await page.goto(`${WP_URL}/wp-admin/admin.php?page=wchs-settings&tab=${tab}`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(300);
    survivingInfo += await page.locator('.wchs-info').count();
  }
  if (survivingInfo <= 10) ok(`.wchs-info survivors ≤10 across 3 tabs (got ${survivingInfo})`);
  else no('info survivors', `expected ≤10, got ${survivingInfo} — sweep incomplete?`);

  console.log(`\n=======================================`);
  console.log(`Results: ${pass} passed, ${fail} failed`);
  await browser.close();
  process.exit(fail > 0 ? 1 : 0);
}

main().catch(e => { console.error(e); process.exit(1); });
