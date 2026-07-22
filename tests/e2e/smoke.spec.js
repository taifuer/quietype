const { test, expect } = require('@playwright/test');

const routes = [
  ['首页', '/'],
  ['文章', '/quietype-reading-test/'],
  ['归档', '/archive/'],
  ['友链', '/links/'],
  ['关于', '/about/'],
  ['搜索', '/?s=Quietype'],
  ['404', '/quietype-missing-page/']
];

test.describe('public pages', () => {
  for (const [label, path] of routes) {
    test(`${label} renders without browser errors`, async ({ page }) => {
      const browserErrors = [];
      page.on('pageerror', (error) => browserErrors.push(error.message));
      page.on('console', (message) => {
        const expectedMissingDocument = label === '404' && message.text().includes('status of 404');
        if (message.type() === 'error' && !expectedMissingDocument) browserErrors.push(message.text());
      });

      const response = await page.goto(path, { waitUntil: 'networkidle' });
      expect(response, `${label} should return a response`).not.toBeNull();
      expect(response.status()).toBe(label === '404' ? 404 : 200);
      await expect(page.locator('header.site-header')).toBeVisible();
      await expect(page.locator('footer.site-footer')).toBeVisible();
      expect(browserErrors).toEqual([]);
    });
  }
});

test('article table of contents clears the sticky header', async ({ page }) => {
  await page.goto('/quietype-reading-test/');
  const toc = page.viewportSize().width <= 1120 ? page.locator('.mobile-toc') : page.locator('.article-toc');
  if (await toc.evaluate((element) => element instanceof HTMLDetailsElement)) {
    await toc.locator('summary').click();
  }
  await toc.locator('a').first().click();
  const heading = page.locator('.article-content h2').first();
  const header = page.locator('.site-header');
  const headingBox = await heading.boundingBox();
  const headerBox = await header.boundingBox();
  expect(headingBox.y).toBeGreaterThanOrEqual(headerBox.height);
});

test('reading background persists after navigation', async ({ page }) => {
  await page.goto('/');
  await page.locator('.reading-background__toggle').click();
  await page.locator('[data-reading-bg="warm"]').click();
  await expect(page.locator('html')).toHaveAttribute('data-reading-bg', 'warm');
  await page.reload();
  await expect(page.locator('html')).toHaveAttribute('data-reading-bg', 'warm');
});

test('mobile actions keep search before the edge-aligned menu', async ({ page }) => {
  test.skip(page.viewportSize().width > 500, 'Mobile-only header assertion.');
  await page.goto('/');
  const searchBox = await page.locator('.search-toggle--mobile').boundingBox();
  const menuBox = await page.locator('.nav-toggle').boundingBox();
  expect(searchBox.x).toBeLessThan(menuBox.x);

  await page.locator('.nav-toggle').click();
  await expect(page.locator('.site-nav')).toHaveClass(/open/);
  await page.locator('.nav-toggle').click();
  await expect(page.locator('.site-nav')).not.toHaveClass(/open/);
  await expect(page.locator('.nav-toggle')).toHaveAttribute('aria-expanded', 'false');
});
