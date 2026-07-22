const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;

const routes = ['/', '/quietype-reading-test/', '/archive/', '/?s=Quietype', '/quietype-missing-page/'];

for (const path of routes) {
  test(`${path} has no serious WCAG A/AA violations`, async ({ page }) => {
    await page.goto(path, { waitUntil: 'networkidle' });
    const results = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'])
      .analyze();
    const violations = results.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact));
    expect(violations).toEqual([]);
  });
}
