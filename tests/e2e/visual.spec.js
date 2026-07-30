const { test, expect } = require('@playwright/test');
const fs = require('node:fs');
const path = require('node:path');

const photoFixtures = [
  'mountain-lake.jpg',
  'autumn-road.jpg',
  'alpine-lake.jpg',
  'mountain-lake.jpg',
  'forest-road.jpg',
  'alpine-lake.jpg'
].map((filename) => path.join(__dirname, '..', 'fixtures', 'photos', filename));

async function routeDemoPhotos(page) {
  await page.route('https://images.example.test/**', async (route) => {
    const match = route.request().url().match(/photo-(\d+)/);
    const index = match ? Math.max(0, Number(match[1]) - 1) : 0;
    await route.fulfill({
      status: 200,
      contentType: 'image/jpeg',
      body: fs.readFileSync(photoFixtures[index % photoFixtures.length])
    });
  });
}

test.beforeEach(async ({ page }) => {
  await page.addInitScript(() => localStorage.setItem('quietype-reading-bg', 'paper'));
  await page.route('**/wp-admin/admin-ajax.php', (route) => route.abort());
  await routeDemoPhotos(page);
});

for (const [name, path] of [
  ['home', '/'],
  ['article', '/quietype-reading-test/'],
  ['reading', '/books/'],
  ['photos', '/photos/'],
  ['archive', '/archive/'],
  ['links', '/links/'],
  ['about', '/about/']
]) {
  test(`${name} matches the approved viewport`, async ({ page }) => {
    await page.goto(path, { waitUntil: 'networkidle' });
    await expect(page).toHaveScreenshot(`${name}.png`, {
      fullPage: false,
      caret: 'hide'
    });
  });
}

test('complete home page includes its footer', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'desktop-chromium', 'README uses the compact mobile viewport baseline');
  await page.goto('/', { waitUntil: 'networkidle' });
  await expect(page).toHaveScreenshot('home-full.png', {
    fullPage: true,
    caret: 'hide'
  });
});

test('article code and formulas match the approved viewport', async ({ page }) => {
  await page.goto('/quietype-reading-test/', { waitUntil: 'networkidle' });
  const section = page.locator('.article-content h2').filter({ hasText: '代码与公式' }).first();
  await section.evaluate((heading) => {
    window.scrollTo({ top: heading.getBoundingClientRect().top + window.scrollY - 104 });
  });
  await expect(page).toHaveScreenshot('article-code.png', {
    fullPage: false,
    caret: 'hide'
  });
});

test('pre-footer navigation and footer match the approved viewport', async ({ page }) => {
  await page.goto('/about/', { waitUntil: 'networkidle' });
  const clip = await page.evaluate(() => {
    const navigation = document.querySelector('.prefooter-nav');
    const footer = document.querySelector('.site-footer');
    const y = Math.max(0, navigation.offsetTop - 64);
    return {
      x: 0,
      y,
      width: document.documentElement.clientWidth,
      height: footer.offsetTop + footer.offsetHeight - y
    };
  });
  const screenshot = await page.screenshot({ animations: 'disabled', caret: 'hide', clip });
  expect(screenshot).toMatchSnapshot('footer.png');
});
