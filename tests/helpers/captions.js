const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const { HtmlValidate } = require('html-validate');
const path = require('node:path');

const articleURL = '/quietype-captions-test/';
const photo = path.join(__dirname, '../fixtures/photos/mountain-lake.jpg');
const imageSelector = '.article-content img[src*="/captions/"]';
const validator = new HtmlValidate({ rules: {
  'close-order': 'error',
  'element-permitted-content': 'error',
  'element-permitted-parent': 'error',
  'no-implicit-close': 'error',
  'void-content': 'error'
} });

async function routeImages(page) {
  await page.route('https://images.example.test/**', route => route.fulfill({
    status: 200, contentType: 'image/jpeg', path: photo
  }));
  await page.route('**/wp-admin/admin-ajax.php', route => route.abort());
}

module.exports = function captionTests() {
  test.beforeEach(async ({ page }) => {
    await routeImages(page);
  });

  test('explicit captions render semantically without replacing native captions or alt text', async ({ page }) => {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const response = await page.goto(articleURL, { waitUntil: 'networkidle' });
    const markup = await validator.validateString(await response.text());
    expect(markup.results.flatMap(result => result.messages)).toEqual([]);
    await expect(page.locator('.article-figure')).toHaveCount(3);
    await expect(page.locator('.article-figure > figcaption').first()).toContainText('图 1：山野之间');
    await expect(page.locator('.article-figure > figcaption strong').first()).toHaveText('山野之间');
    await expect(page.locator('.article-figure > figcaption code').first()).toHaveText('alt');
    await expect(page.locator('.article-figure > figcaption a').first()).toHaveAttribute('href', 'https://example.com/source');
    await expect(page.locator(`${imageSelector}[src$="lead.jpg"]`)).toHaveAttribute('alt', '起伏的山坡与远山');
    await expect(page.locator('.article-content table > caption')).toHaveCount(2);
    await expect(page.locator('.article-content table > caption').first()).toHaveText('表 1：阅读功能对比');
    await expect(page.locator('.article-content table > caption').first()).toHaveCSS('caption-side', 'top');
    await expect(page.locator('.article-content figure:not(.article-figure):not(.wp-caption) > figcaption')).toHaveText('原生图片题注继续保留。');
    await expect(page.locator('.article-content .wp-caption-text')).toHaveText('WordPress 原生图片说明。');
    expect(await page.locator(`${imageSelector}[src$="plain.jpg"]`).evaluate(image => image.closest('figure'))).toBeNull();
    await expect(page.locator('.article-content pre')).toContainText('[!CAPTION] 代码示例保持原样。');
    await expect(page.locator('.article-content p').filter({ hasText: /^\[!CAPTION\]/ })).toHaveCount(0);
    expect(errors).toEqual([]);
  });

  test('offscreen article images defer requests and load normally on approach', async ({ page }) => {
    const requests = [];
    page.on('request', request => { if (request.url().includes('/captions/')) requests.push(request.url()); });
    await page.goto(articleURL, { waitUntil: 'networkidle' });
    const first = page.locator(`${imageSelector}[src$="lead.jpg"]`);
    const distant = page.locator(`${imageSelector}[src$="distant.jpg"]`);
    await expect(first).not.toHaveAttribute('loading', 'lazy');
    await expect(first).toHaveAttribute('width', '960');
    await expect(first).toHaveAttribute('height', '593');
    await expect(distant).toHaveAttribute('loading', 'lazy');
    await expect(distant).toHaveAttribute('decoding', 'async');
    expect(requests.some(url => url.endsWith('/distant.jpg'))).toBe(false);
    expect(await distant.evaluate(image => image.naturalWidth)).toBe(0);
    await distant.scrollIntoViewIfNeeded();
    await expect.poll(() => distant.evaluate(image => image.naturalWidth)).toBeGreaterThan(0);
    expect(requests.filter(url => url.endsWith('/distant.jpg'))).toHaveLength(1);
    expect(await page.evaluate(() => document.documentElement.scrollWidth - innerWidth)).toBeLessThanOrEqual(1);
  });

  test('lightbox captions follow new, native and uncaptioned images including unloaded slides', async ({ page }) => {
    await page.goto(articleURL, { waitUntil: 'networkidle' });
    const distant = page.locator(`${imageSelector}[src$="distant.jpg"]`);
    expect(await distant.evaluate(image => image.naturalWidth)).toBe(0);
    await page.locator(`${imageSelector}[src$="lead.jpg"]`).click();
    const lightbox = page.locator('.pswp');
    const caption = lightbox.locator('.pswp__quietype-caption');
    await expect(caption).toContainText('图 1：山野之间');
    await expect.poll(() => page.evaluate(() => Boolean(window.pswp?.opener?.isOpen))).toBe(true);
    // Inline links remain in the article; the overlay deliberately uses text.
    await expect(caption.locator('p a')).toHaveCount(0);
    const next = lightbox.locator('.pswp__button--arrow--next');
    await next.click();
    await expect(caption).toContainText('原生图片题注继续保留。');
    await next.click();
    await expect(caption).toContainText('WordPress 原生图片说明。');
    await next.click();
    await expect(caption).toBeHidden();
    await next.click();
    await expect(caption).toContainText('这是一段较长的说明');
    await expect.poll(() => lightbox.locator('.pswp__img[src$="full.jpg"]').evaluateAll(images => images.some(image => image.naturalWidth > 0))).toBe(true);
    await next.click();
    await expect(caption).toContainText('图 2：滚动接近后正常显示');
    await expect.poll(() => lightbox.locator('.pswp__img[src$="distant.jpg"]').evaluateAll(images => images.some(image => image.naturalWidth > 0))).toBe(true);
    await lightbox.locator('.pswp__button--close').click();
    await expect(lightbox).toHaveCount(0);
  });

  test('captions and real images remain readable without JavaScript and in print', async ({ browser }, testInfo) => {
    const context = await browser.newContext({ javaScriptEnabled: false, viewport: testInfo.project.use.viewport });
    const page = await context.newPage();
    try {
      await routeImages(page);
      await page.goto(articleURL, { waitUntil: 'networkidle' });
      await expect(page.locator('.article-figure > figcaption').first()).toBeVisible();
      await expect(page.locator('.article-content table > caption').first()).toBeVisible();
      await expect.poll(() => page.locator(`${imageSelector}[src$="distant.jpg"]`).evaluate(image => image.naturalWidth)).toBeGreaterThan(0);
      await page.emulateMedia({ media: 'print' });
      await expect(page.locator('.article-figure > figcaption').first()).toBeVisible();
      await expect(page.locator('.article-content table > caption').first()).toBeVisible();
    } finally {
      await context.close();
    }
  });

  test('caption spacing, long links and wide tables stay readable across reading backgrounds', async ({ page }) => {
    await page.goto(articleURL, { waitUntil: 'networkidle' });
    const figure = page.locator('.article-figure').nth(1);
    await figure.scrollIntoViewIfNeeded();
    for (const background of ['paper', 'warm', 'green']) {
      await page.evaluate(value => { document.documentElement.dataset.readingBg = value; }, background);
      expect(await page.evaluate(() => document.documentElement.scrollWidth - innerWidth)).toBeLessThanOrEqual(1);
      const metrics = await figure.evaluate(element => {
        const image = element.querySelector('img').getBoundingClientRect();
        const caption = element.querySelector('figcaption').getBoundingClientRect();
        const style = getComputedStyle(element.querySelector('figcaption'));
        return { gap: caption.top - image.bottom, width: caption.width, container: element.clientWidth, font: parseFloat(style.fontSize) };
      });
      expect(metrics.gap).toBeGreaterThanOrEqual(9);
      expect(metrics.gap).toBeLessThanOrEqual(11);
      expect(metrics.width).toBeLessThanOrEqual(metrics.container + 1);
      expect(metrics.font).toBeGreaterThanOrEqual(14);
    }
    const wide = page.locator('#caption-wide-table').locator('..');
    await wide.scrollIntoViewIfNeeded();
    await expect(wide).toHaveClass('article-table');
    await expect(wide).toHaveAttribute('tabindex', '0');
    await wide.evaluate(element => { element.scrollLeft = 100; });
    expect(await wide.evaluate(element => element.scrollLeft)).toBeGreaterThan(0);
  });

  test('captioned article has no serious accessibility violations', async ({ page }) => {
    await page.goto(articleURL, { waitUntil: 'networkidle' });
    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    expect(results.violations.filter(violation => ['serious', 'critical'].includes(violation.impact))).toEqual([]);
  });
};
