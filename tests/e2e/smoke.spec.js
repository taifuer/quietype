const { test, expect } = require('@playwright/test');

const routes = [
  ['首页', '/'],
  ['文章', '/quietype-reading-test/'],
  ['书籍', '/books/'],
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

test('book archive groups compact reading records by year', async ({ page }) => {
  await page.goto('/books/');
  await expect(page.locator('.books-hero h1')).toHaveText('但是还有书籍');
  await expect(page.locator('.books-hero .eyebrow')).toHaveText('BOOKS');
  await expect(page.locator('.book-item')).toHaveCount(8);
  await expect(page.locator('.book-year-shelf')).toHaveCount(3);
  await expect(page.locator('.book-year-heading h2')).toHaveText(['2026', '2025', '2024']);
  await expect(page.locator('.book-title-row h3 a').first()).toHaveAttribute('href', /^https:\/\/book\.douban\.com\/subject\/[0-9]+\/$/);
  await expect(page.locator('a.book-cover')).toHaveCount(0);
  await expect(page.locator('.book-cover').first()).toHaveJSProperty('tagName', 'DIV');
  await expect(page.locator('.book-terms .post-category').first()).toBeVisible();
  await expect(page.locator('.book-terms .post-tag').first()).toContainText('#');
  await expect(page.locator('.book-cover__fallback').first()).toBeVisible();
  await expect(page.locator('.book-status')).toContainText(['已读', '在读', '待读', '已读', '已读', '已读', '已读', '已读']);
  await expect(page.locator('.personal-rating').first()).not.toContainText('评分');
  await expect(page.locator('.personal-rating').first()).toHaveAttribute('aria-label', /^个人评分 .*满分 5 星$/);
  await expect(page.locator('.book-read-date').first()).toHaveText(/^20[0-9]{2}\.[0-9]{2}$/);
  await expect(page.locator('.book-evaluation__summary > .douban-rating').first()).toBeVisible();
  const firstEvaluation = page.locator('.book-evaluation').first();
  const firstNote = page.locator('.book-note').first();
  expect(await firstEvaluation.evaluate((element) => element.compareDocumentPosition(document.querySelector('.book-note')) & Node.DOCUMENT_POSITION_FOLLOWING)).toBeTruthy();
  expect((await firstEvaluation.boundingBox()).y).toBeLessThan((await firstNote.boundingBox()).y);
});

test('standalone book routes defer to the confirmed Douban source', async ({ request }) => {
  const response = await request.get('/books/programming-pearls/', { maxRedirects: 0 });
  expect(response.status()).toBe(302);
  expect(response.headers().location).toBe('https://book.douban.com/subject/3227098/');
});

test('former reading routes redirect permanently', async ({ request }) => {
  const archive = await request.get('/reading/', { maxRedirects: 0 });
  expect(archive.status()).toBe(301);
  expect(archive.headers().location).toMatch(/\/books\/$/);

  const book = await request.get('/reading/programming-pearls/', { maxRedirects: 0 });
  expect(book.status()).toBe(301);
  expect(book.headers().location).toBe('https://book.douban.com/subject/3227098/');
});

test('pre-footer navigation stays separate from legal footer content', async ({ page }) => {
  await page.goto('/');
  const navigation = page.locator('nav.prefooter-nav');
  await expect(navigation).toBeVisible();
  await expect(navigation.locator('a')).toHaveText(['书籍', '归档', '友链', '关于']);
  await expect(page.locator('footer.site-footer nav.prefooter-nav')).toHaveCount(0);
  await expect(navigation).toHaveCSS('border-top-width', '1px');
  await expect(page.locator('footer.site-footer')).toHaveCSS('border-top-width', '1px');
});

test('reading tools stay above a visible footer on short pages', async ({ page }) => {
  await page.goto('/about/', { waitUntil: 'networkidle' });
  const toolsBox = await page.locator('.reading-tools').boundingBox();
  const footerBox = await page.locator('.site-footer').boundingBox();
  expect(toolsBox.y + toolsBox.height).toBeLessThan(footerBox.y);
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

test('article headings expose stable permalinks without polluting the table of contents', async ({ page }) => {
  await page.goto('/quietype-reading-test/');
  const heading = page.locator('.article-content h2').first();
  const permalink = heading.locator('.heading-permalink');
  await expect(permalink).toHaveAttribute('href', '#section-1');
  await expect(permalink).toHaveAttribute('aria-label', '复制“排版与长内容”章节链接');
  await expect(page.locator('.article-toc a').first()).toHaveText('排版与长内容');
  await permalink.click();
  await expect(page).toHaveURL(/#section-1$/);
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
