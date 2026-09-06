const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;

const articleURL = '/quietype-plugin-reading-test/';

test.beforeEach(async ({ page }) => {
  await page.addInitScript(() => localStorage.setItem('quietype-reading-bg', 'paper'));
  await page.route('**/wp-admin/admin-ajax.php', (route) => route.abort());
});

async function openArticle(page) {
  await page.goto(articleURL, { waitUntil: 'networkidle' });
  await expect(page.locator('.article-content')).toBeVisible();
}

async function revealDiagram(page, index) {
  const figure = page.locator('.mermaid-figure').nth(index);
  await figure.scrollIntoViewIfNeeded();
  await expect(figure).toHaveAttribute('data-state', /ready|error/, { timeout: 15000 });
  return figure;
}

async function scrollToHeading(page, index) {
  await page.locator('.article-content h2').nth(index).evaluate((heading) => {
    const header = document.querySelector('.site-header').getBoundingClientRect().bottom;
    window.scrollTo({ top: scrollY + heading.getBoundingClientRect().top - header - 20, behavior: 'instant' });
  });
}

test('real Editor.md code, math and diagrams render without duplicate initializers', async ({ page }) => {
  const errors = [];
  const failed = [];
  page.on('pageerror', (error) => errors.push(error.message));
  page.on('response', (response) => { if (response.status() >= 400) failed.push(response.url()); });
  await openArticle(page);
  for (const index of [0, 1]) {
    const figure = await revealDiagram(page, index);
    await expect(figure).toHaveAttribute('data-state', 'ready');
    await expect(figure.locator('.mermaid-canvas > svg')).toHaveCount(1);
  }
  await expect(page.locator('pre.language-javascript .token.keyword').first()).toBeAttached();
  await expect(page.locator('pre.language-javascript .line-numbers-rows')).toBeAttached();
  expect(await page.locator('.katex .katex-html').count()).toBeGreaterThan(0);
  expect(await page.locator('.katex-display').count()).toBeGreaterThan(0);
  await expect(page.locator('.article-alert')).toHaveCount(3);
  await expect(page.locator('.article-content blockquote:not(.article-alert)')).toHaveCount(1);
  const scripts = await page.locator('script').evaluateAll((nodes) => nodes.map((node) => node.src || node.textContent));
  expect(scripts.some((script) => /wp-editormd.*Mermaid/.test(script))).toBe(false);
  expect(scripts.some((script) => /mermaid\.initialize/.test(script))).toBe(false);
  await expect(page.locator('.article-content script')).toHaveCount(0);
  expect(errors).toEqual([]);
  expect(failed).toEqual([]);
});

test('tables share header/body column widths and only wide tables scroll', async ({ page }) => {
  await openArticle(page);
  const tables = page.locator('.article-table');
  await expect(tables).toHaveCount(2);
  for (const table of await tables.all()) {
    const columns = await table.evaluate((wrapper) => {
      const headings = [...wrapper.querySelectorAll('th')];
      const cells = [...wrapper.querySelectorAll('tbody tr:first-child td')];
      return headings.map((heading, index) => ({
        head: heading.getBoundingClientRect().width,
        body: cells[index].getBoundingClientRect().width,
        offset: heading.getBoundingClientRect().x - cells[index].getBoundingClientRect().x
      }));
    });
    for (const column of columns) {
      expect(Math.abs(column.head - column.body)).toBeLessThan(1);
      expect(Math.abs(column.offset)).toBeLessThan(1);
    }
  }
  await expect(tables.nth(1)).toHaveAttribute('tabindex', '0');
  await tables.nth(1).evaluate((element) => { element.scrollLeft = 100; });
  expect(await tables.nth(1).evaluate((element) => element.scrollLeft)).toBeGreaterThan(0);
  expect(await page.evaluate(() => document.documentElement.scrollWidth - innerWidth)).toBeLessThanOrEqual(1);
});

test('overflowing code wraps on request without changing copied source or line numbers', async ({ page }) => {
  await openArticle(page);
  const pre = page.locator('pre.language-javascript');
  const code = pre.locator('code');
  const original = await code.innerText();
  const wrap = pre.locator('..').getByRole('button', { name: '自动换行代码' });
  await expect(wrap).toBeVisible();
  await wrap.click();
  await expect(wrap).toHaveAttribute('aria-pressed', 'true');
  await expect(pre).toHaveClass(/code-wrap/);
  await expect.poll(() => pre.evaluate((element) => element.scrollWidth - element.clientWidth)).toBeLessThanOrEqual(1);
  expect(await code.innerText()).toBe(original);
  const rows = pre.locator('.line-numbers-rows > span');
  expect(await rows.count()).toBe(4);
  const lineHeight = await pre.evaluate((element) => parseFloat(getComputedStyle(element).lineHeight));
  expect((await rows.first().boundingBox()).height).toBeGreaterThan(lineHeight * 1.5);
  await page.evaluate(() => Object.defineProperty(navigator, 'clipboard', {
    configurable: true,
    value: { writeText: async (text) => { window.copiedCode = text; } }
  }));
  await pre.locator('..').locator('.copy-code').click();
  expect(await page.evaluate(() => window.copiedCode)).toBe(original);
  await wrap.click();
  await expect(wrap).toHaveAttribute('aria-pressed', 'false');
  expect(await code.innerText()).toBe(original);
});

test('desktop TOC truncates visually, exposes full labels and follows the active section', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'desktop-chromium');
  await page.setViewportSize({ width: 1440, height: 600 });
  await openArticle(page);
  const link = page.locator('.article-toc a').nth(5);
  const fullText = await link.innerText();
  expect(await link.evaluate((element) => getComputedStyle(element).whiteSpace)).toBe('nowrap');
  expect(await link.evaluate((element) => element.scrollWidth > element.clientWidth)).toBe(true);
  await link.hover();
  await expect(page.locator('.toc-tooltip')).toHaveText(fullText);
  await expect(page.locator('.toc-tooltip')).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(page.locator('.toc-tooltip')).toBeHidden();
  await link.focus();
  await expect(page.locator('.toc-tooltip')).toBeVisible();
  await scrollToHeading(page, 33);
  await expect(page.locator('.article-toc a.active')).toHaveText(/29、事件回调/);
  const position = await page.locator('.article-toc nav').evaluate((nav) => ({
    top: nav.getBoundingClientRect().top,
    bottom: nav.getBoundingClientRect().bottom,
    activeTop: nav.querySelector('a.active').getBoundingClientRect().top,
    activeBottom: nav.querySelector('a.active').getBoundingClientRect().bottom,
    scroll: nav.scrollTop
  }));
  expect(position.scroll).toBeGreaterThan(0);
  expect(position.activeTop).toBeGreaterThanOrEqual(position.top);
  expect(position.activeBottom).toBeLessThanOrEqual(position.bottom);
  await page.locator('.article-toc a').last().click();
  await expect.poll(() => page.locator('.article-content h2').last().evaluate((heading) => heading.getBoundingClientRect().top)).toBeGreaterThanOrEqual(73);
});

test('compact reading TOC opens after scrolling, preserves position and navigates below the header', async ({ page }, testInfo) => {
  if (testInfo.project.name === 'desktop-chromium') await page.setViewportSize({ width: 1280, height: 768 });
  await openArticle(page);
  const button = page.getByRole('button', { name: '打开文章目录' });
  await expect(button).toBeHidden();
  await scrollToHeading(page, 20);
  await expect(button).toBeVisible();
  const before = await page.evaluate(() => scrollY);
  await button.click();
  const dialog = page.getByRole('dialog', { name: '文章目录' });
  await expect(dialog).toBeVisible();
  await expect(button).toHaveAttribute('aria-expanded', 'true');
  expect(await page.evaluate(() => document.documentElement.classList.contains('reading-dialog-open'))).toBe(true);
  await page.keyboard.press('Escape');
  await expect(dialog).toBeHidden();
  expect(Math.abs(await page.evaluate(() => scrollY) - before)).toBeLessThan(2);
  await button.click();
  const destination = dialog.locator('nav a').nth(7);
  const hash = await destination.getAttribute('href');
  await destination.click();
  await expect(dialog).toBeHidden();
  await expect(page).toHaveURL(new RegExp(`${hash}$`));
  const heading = page.locator(hash);
  const headerBottom = await page.locator('.site-header').evaluate((node) => node.getBoundingClientRect().bottom);
  await expect.poll(() => heading.evaluate((node) => node.getBoundingClientRect().top)).toBeGreaterThanOrEqual(headerBottom + 8);
  expect(await page.evaluate(() => document.documentElement.scrollWidth - innerWidth)).toBeLessThanOrEqual(1);
});

test('diagram zoom stays within viewport, restores the inline graph and follows reading colors', async ({ page }) => {
  await openArticle(page);
  const figure = await revealDiagram(page, 0);
  await expect(figure).toHaveAttribute('data-state', 'ready');
  const firstID = await figure.locator('svg').getAttribute('id');
  await page.evaluate(() => { document.documentElement.dataset.readingBg = 'green'; });
  await expect(figure.locator('svg')).not.toHaveAttribute('id', firstID);
  await expect(figure.locator('.node rect').first()).toHaveCSS('fill', 'rgb(229, 236, 228)');
  await figure.getByRole('button', { name: '放大查看' }).click();
  const dialog = page.getByRole('dialog', { name: '查看图表' });
  await expect(dialog).toBeVisible();
  const svg = dialog.locator('svg');
  const before = (await svg.boundingBox()).width;
  await dialog.getByRole('button', { name: '放大图表' }).click();
  expect((await svg.boundingBox()).width).toBeGreaterThan(before);
  await dialog.getByRole('button', { name: '适应屏幕' }).click();
  const box = await dialog.boundingBox();
  expect(box.x).toBeGreaterThanOrEqual(0);
  expect(box.x + box.width).toBeLessThanOrEqual(page.viewportSize().width);
  if (page.viewportSize().width > 1000) expect(box.width).toBeGreaterThan(900);
  await page.keyboard.press('Escape');
  await expect(dialog).toBeHidden();
  await expect(figure.locator('.mermaid-canvas > svg')).toHaveCount(1);
  expect(await page.evaluate(() => document.documentElement.classList.contains('reading-dialog-open'))).toBe(false);
});

test('invalid diagrams retain source; unsafe HTML and security overrides cannot execute', async ({ page }) => {
  let dialogShown = false;
  page.on('dialog', async (dialog) => { dialogShown = true; await dialog.dismiss(); });
  await openArticle(page);
  const invalid = await revealDiagram(page, 2);
  await expect(invalid).toHaveAttribute('data-state', 'error');
  await expect(invalid.locator('details')).toHaveAttribute('open', '');
  await expect(invalid.locator('pre')).toContainText('INVALID-DIRECTION');
  await expect(invalid.getByRole('button', { name: '放大查看' })).toBeHidden();
  const unsafe = await revealDiagram(page, 3);
  await expect(unsafe).toHaveAttribute('data-state', 'ready');
  await expect(unsafe.locator('img, script, [onerror], [onclick]')).toHaveCount(0);
  expect(await page.evaluate(() => window.mermaid.mermaidAPI.getConfig().securityLevel)).toBe('strict');
  expect(dialogShown).toBe(false);
});

test('renderer download failure leaves readable source and no uncaught exception', async ({ page }) => {
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  await page.route('**/vendor/mermaid/mermaid.min.js*', (route) => route.abort());
  await openArticle(page);
  const figure = await revealDiagram(page, 0);
  await expect(figure).toHaveAttribute('data-state', 'error');
  await expect(figure.locator('pre')).toBeVisible();
  await expect(figure.locator('pre')).toContainText('flowchart LR');
  expect(errors).toEqual([]);
});

test('standard fenced-code HTML renders without requesting a Prism Mermaid grammar', async ({ page }) => {
  const missing = [];
  page.on('response', (response) => { if (response.status() >= 400) missing.push(response.url()); });
  await openArticle(page);
  const figure = await revealDiagram(page, 4);
  await expect(figure).toHaveAttribute('data-state', 'ready');
  await expect(figure.locator('svg')).toContainText('标准围栏输出');
  expect(missing).toEqual([]);
});

test('without JavaScript, diagram source and inline TOC remain accessible', async ({ browser, baseURL }) => {
  const context = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 844 } });
  try {
    const page = await context.newPage();
    await page.goto(new URL(articleURL, baseURL).href);
    await expect(page.locator('pre.mermaid')).toHaveCount(5);
    await expect(page.locator('pre.mermaid').first()).toContainText('flowchart LR');
    await page.locator('.mobile-toc summary').click();
    await expect(page.locator('.mobile-toc nav a').first()).toBeVisible();
    await expect(page.locator('.article-content script')).toHaveCount(0);
  } finally {
    await context.close();
  }
});

test('ordinary pages do not download diagram libraries or unused plugin initializers', async ({ page }) => {
  const requested = [];
  const errors = [];
  page.on('request', (request) => requested.push(request.url()));
  page.on('pageerror', (error) => errors.push(error.message));
  await page.goto('/', { waitUntil: 'networkidle' });
  expect(requested.some((url) => /mermaid|prism|katex/i.test(url))).toBe(false);
  expect(errors).toEqual([]);
});

test('real technical content and diagram dialog have no serious accessibility violations', async ({ page }) => {
  await openArticle(page);
  const figure = await revealDiagram(page, 0);
  const check = async () => {
    const result = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze();
    expect(result.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact))).toEqual([]);
  };
  await check();
  await figure.getByRole('button', { name: '放大查看' }).click();
  await check();
});
