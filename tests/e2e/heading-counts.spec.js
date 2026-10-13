const { test, expect } = require('@playwright/test');

function luminance(rgb) {
  const channels = rgb.match(/[\d.]+/g).slice(0, 3).map((channel) => {
    const value = Number(channel) / 255;
    return value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
  });
  return channels[0] * 0.2126 + channels[1] * 0.7152 + channels[2] * 0.0722;
}

async function expectAdjacent(heading, count) {
  const titleBox = await heading.boundingBox();
  const countBox = await count.boundingBox();
  expect(countBox.x - titleBox.x - titleBox.width).toBeCloseTo(10, 0);
  expect(countBox.y).toBeLessThan(titleBox.y + titleBox.height);
  expect(countBox.y + countBox.height).toBeGreaterThan(titleBox.y);
}

test('archive tag total stays next to its heading', async ({ page }) => {
  await page.goto('/archive/');
  const heading = page.locator('.archive-tags__heading h2');
  const count = page.locator('.archive-tags__heading > span');
  await expect(count).toHaveText(`${await page.locator('.archive-tags__list a').count()} 个标签`);
  await expectAdjacent(heading, count);
});

test('friend-link counts are adjacent, readable badges in every reading palette', async ({ page }) => {
  await page.goto('/links/');
  const groups = page.locator('.link-group');
  expect(await groups.count()).toBeGreaterThan(0);
  for (const palette of ['paper', 'warm', 'green']) {
    await page.evaluate((value) => document.documentElement.dataset.readingBg = value, palette);
    for (const group of await groups.all()) {
      const count = group.locator('.link-group__count');
      await expect(count).toHaveText(`${await group.locator('.link-card').count()} 个友链`);
      await expectAdjacent(group.locator('h2'), count);
      const styles = await count.evaluate((element) => {
        const css = getComputedStyle(element);
        return { color: css.color, background: css.backgroundColor, height: element.clientHeight };
      });
      expect(styles.height).toBe(24);
      expect(styles.background).not.toBe('rgba(0, 0, 0, 0)');
      const light = Math.max(luminance(styles.color), luminance(styles.background));
      const dark = Math.min(luminance(styles.color), luminance(styles.background));
      expect((light + 0.05) / (dark + 0.05)).toBeGreaterThanOrEqual(4.5);
    }
  }
});
