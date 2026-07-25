const { test, expect } = require('@playwright/test');

test.beforeEach(async ({ page }) => {
  await page.addInitScript(() => localStorage.setItem('quietype-reading-bg', 'paper'));
  await page.route('**/wp-admin/admin-ajax.php', (route) => route.abort());
});

for (const [name, path] of [
  ['home', '/'],
  ['article', '/quietype-reading-test/'],
  ['reading', '/reading/'],
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
