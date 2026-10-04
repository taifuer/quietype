const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;

test('projects preserve the approved card hierarchy and optional metadata', async ({ page }) => {
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  await page.goto('/projects/');
  await expect(page.locator('h1')).toHaveText('格物致用');
  await expect(page.locator('.project-count')).toHaveText('3 个项目');
  await expect(page.locator('.project-card')).toHaveCount(3);
  const cards = page.locator('.project-card');
  await expect(cards.nth(0).locator('h2')).toHaveText('公开数据小站');
  await expect(cards.nth(0).locator('.project-kind')).toHaveText('数据看板');
  await expect(cards.nth(0).locator('.project-meta')).toHaveText('开源 PHP / JavaScript');
  await expect(cards.nth(1).locator('.project-kind')).toHaveText('个人项目');
  await expect(cards.nth(1).locator('.project-source')).toHaveCount(0);
  await expect(cards.nth(1).getByRole('link', { name: 'GitHub', exact: true })).toBeVisible();
  await expect(cards.nth(2).locator('.project-source')).toHaveText('未开源');
  await expect(cards.nth(2).locator('a')).toHaveCount(0);
  await expect(cards.nth(2).locator('.project-preview')).toHaveCount(0);
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
  const edges = await page.evaluate(() => ({ count: document.querySelector('.project-count').getBoundingClientRect().right, grid: document.querySelector('.project-grid').getBoundingClientRect().right }));
  expect(Math.abs(edges.count - edges.grid)).toBeLessThanOrEqual(1);
  expect(errors).toEqual([]);
});

test('project screenshots reuse the lazy PhotoSwipe viewer with captions and keyboard close', async ({ page }) => {
  await page.goto('/projects/');
  expect(await page.evaluate(() => performance.getEntriesByType('resource').some((entry) => /vendor\/photoswipe\//.test(entry.name)))).toBe(false);
  const screenshot = page.locator('.project-preview').first();
  await screenshot.focus();
  await page.keyboard.press('Enter');
  await expect(page.locator('.pswp')).toBeVisible();
  await expect(page.locator('.pswp__quietype-caption')).toContainText('公开数据小站');
  await expect.poll(() => page.locator('.pswp__img').evaluateAll((images) => images.some((image) => image.complete && image.naturalWidth > 0))).toBe(true);
  await page.keyboard.press('Escape');
  await expect(page.locator('.pswp')).toHaveCount(0);
  await expect(screenshot).toBeFocused();
});

test('external project previews correct unknown image dimensions after loading', async ({ page }) => {
  await page.goto('/projects/');
  await page.locator('.project-preview').first().evaluate((link) => {
    link.dataset.pswpWidth = '100';
    link.dataset.pswpHeight = '100';
  });
  await page.locator('.project-preview').first().click();
  await expect(page.locator('.pswp')).toBeVisible();
  await expect.poll(async () => page.locator('.pswp__img').evaluateAll((images) => images.some((image) => {
    if (!image.complete || !image.naturalWidth || !image.width || !image.height) return false;
    return Math.abs(image.width / image.height - image.naturalWidth / image.naturalHeight) < 0.01;
  }))).toBe(true);
  await page.keyboard.press('Escape');
});

test('project images fail gently and cards fit narrow screens and all reading palettes', async ({ page }) => {
  await page.route('**/tests/fixtures/photos/forest-road.jpg', (route) => route.fulfill({ status: 404, body: '' }));
  await page.goto('/projects/');
  await expect(page.locator('.project-preview').first()).toHaveClass(/is-unavailable/);
  for (const width of [320, 360, 390, 768, 1280]) {
    await page.setViewportSize({ width, height: 844 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
  }
  for (const palette of ['paper', 'warm', 'green']) {
    await page.evaluate((background) => { document.documentElement.dataset.readingBg = background; }, palette);
    const results = await new AxeBuilder({ page }).include('.projects-page').withTags(['wcag2a', 'wcag2aa']).analyze();
    expect(results.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact))).toEqual([]);
  }
});

test('project content remains readable without JavaScript and singular links redirect to the archive', async ({ browser, request }) => {
  const context = await browser.newContext({ javaScriptEnabled: false });
  const page = await context.newPage();
  await page.goto(`${test.info().project.use.baseURL}/projects/`);
  await expect(page.locator('.project-card')).toHaveCount(3);
  await expect(page.locator('.project-preview').first()).toHaveAttribute('href', /forest-road\.jpg$/);
  const redirect = await request.get('/projects/quietype-project-1/', { maxRedirects: 0 });
  expect(redirect.status()).toBe(301);
  expect(redirect.headers().location).toMatch(/\/projects\/#project-\d+$/);
  const search = await request.get('/?s=公开数据小站');
  expect(await search.text()).not.toContain('class="post-row"');
  const sitemap = await request.get('/wp-sitemap-quietypearchives-1.xml');
  expect(await sitemap.text()).toContain('/projects/');
  await context.close();
});

test('project editor saves optional data with one consistent field width', async ({ page }) => {
  test.skip(page.viewportSize().width < 700, 'Administration fields are viewport-independent.');
  await page.goto('/wp-login.php');
  await page.locator('#user_login').fill('projects-admin');
  await page.locator('#user_pass').fill('password');
  await Promise.all([page.waitForURL(/\/wp-admin\//), page.locator('#wp-submit').click()]);
  await page.goto('/wp-admin/post-new.php?post_type=project');
  await expect(page.locator('#quietype_project_type')).toHaveValue('0');
  await expect(page.locator('#quietype_project_source_state')).toHaveValue('');
  const widths = await page.locator('.quietype-project-editor input, .quietype-project-editor select').evaluateAll((fields) => [...new Set(fields.map((field) => Math.round(field.getBoundingClientRect().width)))]);
  expect(widths).toHaveLength(1);
  await page.locator('#title').fill('项目编辑回归草稿');
  await page.locator('#quietype_project_url').fill('https://example.com/demo');
  await page.locator('#quietype_project_source_url').fill('https://github.com/example/demo');
  await page.locator('#quietype_project_source_state').selectOption('open');
  await page.locator('#quietype_project_languages').fill('C++, PHP, PHP');
  await page.locator('#menu_order').fill('12');
  await page.locator('#excerpt').fill('这是一条后台回归检查，不发布到项目页。');
  await Promise.all([page.waitForURL(/post\.php\?post=\d+&action=edit/), page.locator('#save-post').click()]);
  await expect(page.locator('#quietype_project_languages')).toHaveValue('C++, PHP');
  await expect(page.locator('#quietype_project_source_state')).toHaveValue('open');
  await expect(page.locator('#menu_order')).toHaveValue('12');
  await expect(page.locator('#quietype_project_image_url')).toHaveValue('');
  await expect(page.locator('#sample-permalink')).toHaveCount(0);
  await expect(page.locator('#post-preview')).toHaveAttribute('href', /\/projects\/#project-\d+$/);
});
