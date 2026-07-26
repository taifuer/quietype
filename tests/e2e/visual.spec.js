const { test, expect } = require('@playwright/test');

test.beforeEach(async ({ page }) => {
  await page.addInitScript(() => localStorage.setItem('quietype-reading-bg', 'paper'));
  await page.route('**/wp-admin/admin-ajax.php', (route) => route.abort());
  await page.route('https://images.example.test/**', async (route) => {
    await route.fulfill({ status: 200, contentType: 'image/gif', body: Buffer.from('R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=', 'base64') });
  });
});

for (const [name, path] of [
  ['home', '/'],
  ['article', '/quietype-reading-test/'],
  ['reading', '/books/'],
  ['photos', '/photos/'],
  ['archive', '/archive/']
]) {
  test(`${name} matches the approved viewport`, async ({ page }) => {
    await page.goto(path, { waitUntil: 'networkidle' });
    await expect(page).toHaveScreenshot(`${name}.png`, {
      fullPage: false,
      caret: 'hide'
    });
  });
}
