const { test, expect } = require('@playwright/test');

test('code action labels stay compact and separated in every copy/wrap state', async ({ page }) => {
  await page.route('**/wp-admin/admin-ajax.php', (route) => route.abort());
  await page.route('**/avatar/**', (route) => route.fulfill({
    contentType: 'image/gif',
    body: Buffer.from('R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=', 'base64')
  }));
  await page.addInitScript(() => Object.defineProperty(navigator, 'clipboard', {
    configurable: true,
    value: { writeText: async () => { if (window.failCodeCopy) throw new Error('Clipboard unavailable'); } }
  }));
  await page.goto('/quietype-reading-test/', { waitUntil: 'networkidle' });
  const toolbar = page.locator('.code-toolbar').filter({ has: page.locator('.wrap-code') }).first();
  // Keep wrapping available at desktop widths as well as narrow mobile widths.
  await toolbar.locator('pre > code').evaluate((code) => {
    code.append(document.createTextNode('\n// ' + 'copy-state-layout-regression '.repeat(12)));
  });
  const wrap = toolbar.locator('.wrap-code');
  const copy = toolbar.locator('.copy-code');
  const assertControls = async () => {
    const left = await wrap.boundingBox();
    const right = await copy.boundingBox();
    const bounds = await toolbar.boundingBox();
    expect(right.x - left.x - left.width).toBeCloseTo(8, 1);
    expect(right.y).toBeCloseTo(left.y, 1);
    expect(left.height).toBeGreaterThanOrEqual(24);
    expect(right.height).toBeGreaterThanOrEqual(24);
    expect(right.x + right.width).toBeLessThanOrEqual(bounds.x + bounds.width);
    await expect(wrap).toHaveCSS('font-size', '12px');
    await expect(copy).toHaveCSS('font-size', '12px');
  };

  await expect(wrap).toBeVisible();
  await toolbar.scrollIntoViewIfNeeded();
  await assertControls();
  for (const wrapped of [false, true]) {
    if (wrapped) await wrap.click();
    await expect(wrap).toHaveText(wrapped ? '不换行' : '换行');
    for (const failed of [false, true]) {
      await page.evaluate((value) => { window.failCodeCopy = value; }, failed);
      await copy.click();
      await expect(copy).toHaveText(failed ? '复制失败' : '已复制');
      await assertControls();
      await expect(copy).toHaveText('复制');
      await assertControls();
    }
  }
});
