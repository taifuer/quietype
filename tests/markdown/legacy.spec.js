const { test, expect } = require('@playwright/test');
const formulas = [
  'P(H)', 'P(E|H)', 'P(E)', 'P(H|E)',
  String.raw`P(H|E) = \frac{P(H)P(E|H)}{P(E)}`,
  'x_1', 'x_2', 'f(x) = x^2', 'g(x) = x^3',
  String.raw`a -- b + x'' + \text{a...b}`,
  'a < b', String.raw`\text{\$20}`, 'F(x) = x_1 + x_2'
];

test('legacy HTML retains every formula and its boundaries through typography and wrapping', async ({ page }) => {
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await page.goto('/quietype-legacy-math-test/', { waitUntil: 'networkidle' });
  await expect(page.locator('.quietype-math .katex-html')).toHaveCount(formulas.length);
  expect(await page.locator('.quietype-math annotation').allTextContents()).toEqual(formulas);
  await expect(page.locator('.quietype-math wbr, .katex-error, code .katex')).toHaveCount(0);
  await expect(page.locator('.katex-display')).toHaveCount(3);
  await expect(page.getByText('这些中文说明不能被识别成公式。')).toBeVisible();
  await expect(page.locator('.article-content .quietype-math')).not.toContainText(['这些中文说明']);
  await expect(page.locator('a[title="$attribute$ > keepValue"]')).toHaveAttribute('href', 'https://example.test/$literal$');
  await expect(page.locator('pre code')).toContainText('user:~$ gcc');
  expect(await page.locator('.article-content p').last().locator('wbr').count()).toBeGreaterThan(0);
  expect(await page.evaluate(() => document.documentElement.scrollWidth - innerWidth)).toBeLessThanOrEqual(1);
  await expect(page.locator('script[src*="wp-editormd"], script[src*="auto-render"]')).toHaveCount(0);
  expect(errors).toEqual([]);
});

test('code-only content never loads math libraries or changes Shell tokens', async ({ page }) => {
  const scripts = [];
  page.on('request', request => { if (request.resourceType() === 'script') scripts.push(request.url()); });
  await page.goto('/quietype-shell-only-test/', { waitUntil: 'networkidle' });
  await expect(page.locator('.article-content code').first()).toHaveText('$x$');
  await expect(page.locator('pre code')).toHaveText('echo "$HOME" "$USER" "$$"');
  await expect(page.locator('pre code .token').first()).toBeAttached();
  expect(scripts.some(url => /katex|auto-render|wp-editormd/i.test(url))).toBe(false);
});

test('a failed KaTeX download leaves intact readable TeX without uncaught errors', async ({ page }) => {
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await page.route('**/vendor/katex/katex.min.js*', route => route.abort());
  await page.goto('/quietype-legacy-math-test/', { waitUntil: 'networkidle' });
  expect(await page.locator('.quietype-math').allTextContents()).toEqual(formulas);
  await expect(page.locator('.quietype-math wbr')).toHaveCount(0);
  await expect(page.locator('pre code .token').first()).toBeAttached();
  expect(errors).toEqual([]);
});

test('without JavaScript legacy formulas keep their original notation', async ({ browser }) => {
  const context = await browser.newContext({ javaScriptEnabled: false });
  try {
    const page = await context.newPage();
    await page.goto('/quietype-legacy-math-test/');
    expect(await page.locator('.quietype-math').allTextContents()).toEqual(formulas);
    await expect(page.locator('.quietype-math wbr')).toHaveCount(0);
  } finally { await context.close(); }
});
