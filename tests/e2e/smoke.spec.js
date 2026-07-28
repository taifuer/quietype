const { test, expect } = require('@playwright/test');

const routes = [
  ['首页', '/'],
  ['文章', '/quietype-reading-test/'],
  ['书籍', '/books/'],
  ['照片', '/photos/'],
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
      await page.route('https://images.example.test/**', async (route) => {
        await route.fulfill({ status: 200, contentType: 'image/gif', body: Buffer.from('R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=', 'base64') });
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

test('core sitemap is canonical and includes the public book and photo archives', async ({ request }) => {
  const legacy = await request.get('/sitemap.xml', { maxRedirects: 0 });
  expect(legacy.status()).toBe(301);
  expect(legacy.headers().location).toMatch(/\/wp-sitemap\.xml$/);

  const index = await request.get('/wp-sitemap.xml');
  expect(index.status()).toBe(200);
  expect(await index.text()).toContain('/wp-sitemap-quietypearchives-1.xml');

  const archives = await request.get('/wp-sitemap-quietypearchives-1.xml');
  expect(archives.status()).toBe(200);
  const xml = await archives.text();
  expect(xml).toContain('<loc>http://localhost:8888/books/</loc>');
  expect(xml).toContain('<loc>http://localhost:8888/photos/</loc>');
  expect(xml).not.toContain('/quietype-book-');
  expect(xml).not.toContain('/quietype-photo-');
});

test('photo archive groups external images and keeps details in the lightbox', async ({ page }) => {
  const originalRequests = [];
  const deferredRequests = [];
  page.on('request', (request) => {
    if (request.url().includes('photo-1-original.jpg')) originalRequests.push(request.url());
    if (/photo-[4-6]\.webp/.test(request.url())) deferredRequests.push(request.url());
  });
  await page.route('https://images.example.test/**', async (route) => {
    await route.fulfill({ status: 200, contentType: 'image/gif', body: Buffer.from('R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=', 'base64') });
  });
  const pageResponse = await page.goto('/photos/');
  const pageHtml = await pageResponse.text();
  expect(pageHtml).not.toContain('loading="lazy"');
  expect(pageHtml).not.toContain('<noscript><img');
  await expect(page.locator('.photos-hero h1')).toHaveText('万物静观');
  await expect(page.locator('.photos-hero .eyebrow')).toHaveText('PHOTOS');
  await expect(page.locator('.photos-hero__meta .collection-intro')).toHaveCount(0);
  await expect(page.locator('.photos-hero__meta .collection-stats')).toHaveText('2024—2026 · 6 张');
  await expect(page.locator('.photo-card')).toHaveCount(6);
  await expect(page.locator('.photo-year')).toHaveCount(3);
  await expect(page.locator('.photo-year__heading h2')).toHaveText(['2026', '2025', '2024']);
  await expect(page.locator('.photo-year-index')).toHaveClass(/photo-year-index--count-3/);
  await expect(page.locator('.photo-year').nth(0)).toHaveAttribute('data-expanded', 'true');
  await expect(page.locator('.photo-year').nth(1)).toHaveAttribute('data-expanded', 'false');
  await expect(page.locator('.photo-year').nth(2)).toHaveAttribute('data-expanded', 'false');
  await expect(page.locator('.photo-grid').first()).toBeVisible();
  await expect(page.locator('.photo-grid').nth(1)).toBeHidden();
  await expect(page.locator('.photo-year').first().locator('img').first()).toHaveAttribute('src', 'https://images.example.test/photos/thumbs/2026/photo-1.webp');
  await expect(page.locator('.photo-year').first().locator('img').first()).toHaveAttribute('data-photo-fallback', 'https://images.example.test/photos/2026/photo-1.jpg');
  await expect(page.locator('.photo-year').first().locator('img').first()).not.toHaveAttribute('data-src');
  await expect(page.locator('.photo-year').nth(1).locator('img').first()).not.toHaveAttribute('src');
  await expect(page.locator('.photo-year').nth(1).locator('img').first()).toHaveAttribute('data-src', 'https://images.example.test/photos/thumbs/2025/photo-4.webp');
  await expect(page.locator('.photo-year').nth(1).locator('img').first()).not.toHaveAttribute('loading');
  expect(deferredRequests).toEqual([]);
  await expect(page.locator('.photo-frame').first()).toHaveAttribute('data-photo-exif', '35mm · f/4 · 1/320s · ISO 160');
  await expect(page.locator('.photo-frame').first()).toHaveAttribute('data-photo-device', 'Xiaomi');
  await expect(page.locator('.photo-frame').first()).toHaveAttribute('data-photo-meta', '安徽 · 宏村 · 2026年7月');
  await expect(page.locator('.photo-caption small').first()).toHaveText('安徽 · 宏村 · 2026年7月');
  await expect(page.locator('.photo-frame').first()).toHaveAttribute('data-photo-original', 'https://images.example.test/photo-1-original.jpg');
  await expect(page.locator('.photo-frame').first()).toHaveAttribute('data-pswp-src', 'https://images.example.test/photos/2026/photo-1.jpg');
  await expect(page.locator('.photo-frame img').first()).toHaveAttribute('referrerpolicy', 'no-referrer');
  expect(originalRequests).toEqual([]);

  await page.locator('.photo-year').nth(1).locator('.photo-year__toggle').click();
  await expect(page.locator('.photo-year').nth(1)).toHaveAttribute('data-expanded', 'true');
  await expect(page.locator('.photo-grid').nth(1)).toBeVisible();
  await expect(page.locator('.photo-year').nth(1).locator('img').first()).toHaveAttribute('src', 'https://images.example.test/photos/thumbs/2025/photo-4.webp');
  await expect(page.locator('.photo-year').nth(1).locator('img').first()).not.toHaveAttribute('loading');
  await expect.poll(() => deferredRequests.length).toBe(2);

  await page.locator('.photo-frame img').first().click();
  await expect(page.locator('.pswp')).toBeVisible();
  await expect(page.locator('.pswp__button--quietype-zoom')).toHaveCount(2);
  await expect(page.locator('.pswp__button--quietype-zoom').first()).toHaveCSS('color', 'rgb(255, 255, 255)');
  await expect(page.locator('.pswp__top-bar')).toHaveCSS('background-image', /linear-gradient/);
  await expect(page.locator('.pswp__quietype-caption')).toContainText('雨后屋檐');
  await expect(page.locator('.pswp__quietype-caption')).toContainText('35mm · f/4 · 1/320s · ISO 160');
  await expect(page.locator('.pswp__quietype-caption p')).toBeVisible();
  await expect(page.locator('.pswp__quietype-caption small')).toBeVisible();
  await expect(page.locator('.pswp__quietype-caption a')).toHaveAttribute('href', 'https://images.example.test/photo-1-original.jpg');
  expect(originalRequests).toEqual([]);
});

test('photo grid retries a transient CDN failure and preserves its lightbox details', async ({ page }) => {
  const retryRequests = [];
  await page.route('https://images.example.test/**', async (route) => {
    const url = route.request().url();
    if (url.includes('photo-4.webp') && !url.includes('quietype_grid_retry=')) {
      await route.fulfill({ status: 503, contentType: 'text/plain', body: 'temporary image failure' });
      return;
    }
    if (url.includes('photo-4.webp') && url.includes('quietype_grid_retry=')) retryRequests.push(url);
    await route.fulfill({ status: 200, contentType: 'image/gif', body: Buffer.from('R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=', 'base64') });
  });
  await page.goto('/photos/');
  const archivedYear = page.locator('.photo-year').nth(1);
  await archivedYear.locator('.photo-year__toggle').click();
  const retriedImage = archivedYear.locator('.photo-frame img').first();
  await expect.poll(() => retryRequests.length).toBe(1);
  await expect(retriedImage).toHaveAttribute('src', /photo-4\.webp\?quietype_grid_retry=[0-9]+-1$/);
  await expect.poll(() => retriedImage.evaluate((image) => image.complete && image.naturalWidth > 0)).toBe(true);
  await expect(archivedYear.locator('.photo-frame').first()).not.toHaveClass(/is-photo-error/);

  await archivedYear.locator('.photo-frame').first().click();
  await expect(page.locator('.pswp__quietype-caption strong')).toHaveText('暮色归舟');
  await expect(page.locator('.pswp__quietype-caption span')).toHaveText('湖南 · 洞庭湖 · 2025年11月');
  await expect(page.locator('.pswp__quietype-caption p')).not.toBeEmpty();
  await expect(page.locator('.pswp__quietype-caption small')).not.toBeEmpty();
});

test('photo grid falls back to the display image when a generated thumbnail is absent', async ({ page }) => {
  const fallbackRequests = [];
  await page.route('https://images.example.test/**', async (route) => {
    const url = route.request().url();
    if (url.includes('photo-4.webp')) {
      await route.fulfill({ status: 404, contentType: 'text/plain', body: 'missing thumbnail' });
      return;
    }
    if (url.includes('/2025/photo-4.jpg')) fallbackRequests.push(url);
    await route.fulfill({ status: 200, contentType: 'image/gif', body: Buffer.from('R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=', 'base64') });
  });
  await page.goto('/photos/');
  const archivedYear = page.locator('.photo-year').nth(1);
  await archivedYear.locator('.photo-year__toggle').click();
  const image = archivedYear.locator('.photo-frame img').first();
  await expect(image).toHaveAttribute('src', 'https://images.example.test/photos/2025/photo-4.jpg');
  await expect.poll(() => fallbackRequests.length).toBe(1);
  await expect(archivedYear.locator('.photo-frame').first()).not.toHaveClass(/is-photo-error/);
});

test('standalone photo records redirect to the archive', async ({ request }) => {
  const response = await request.get('/photos/quietype-photo-1/', { maxRedirects: 0 });
  expect(response.status()).toBe(301);
  expect(response.headers().location).toMatch(/\/photos\/?#photo-[0-9]+$/);
});

test('photo year deep links expand and hydrate archived images', async ({ page }) => {
  await page.route('https://images.example.test/**', async (route) => {
    await route.fulfill({ status: 200, contentType: 'image/gif', body: Buffer.from('R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=', 'base64') });
  });
  await page.goto('/photos/#photo-year-2024');
  const targetYear = page.locator('#photo-year-2024');
  await expect(targetYear).toHaveAttribute('data-expanded', 'true');
  await expect(targetYear.locator('.photo-grid')).toBeVisible();
  await expect(targetYear.locator('img').first()).toHaveAttribute('src', 'https://images.example.test/photos/thumbs/2024/photo-6.webp');
});

test('photo lightbox loads deferred slides and browser Back closes it in place', async ({ page }) => {
  await page.route('https://images.example.test/**', async (route) => {
    await route.fulfill({ status: 200, contentType: 'image/gif', body: Buffer.from('R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=', 'base64') });
  });
  await page.goto('/photos/');

  const archivedImage = page.locator('.photo-year').nth(1).locator('img').first();
  await expect(archivedImage).not.toHaveAttribute('src');
  await page.locator('.photo-frame img').first().click();
  await expect(page.locator('.pswp')).toBeVisible();
  await expect.poll(() => page.evaluate(() => Boolean(history.state?.quietypeLightbox))).toBe(true);

  await page.evaluate(() => window.pswp.goTo(3));
  await expect(page.locator('.pswp__quietype-caption strong')).toHaveText('暮色归舟');
  await expect(page.locator('.pswp__quietype-caption span')).toHaveText('湖南 · 洞庭湖 · 2025年11月');
  await expect(page.locator('.pswp__quietype-caption p')).not.toBeEmpty();
  await expect(page.locator('.pswp__quietype-caption small')).not.toBeEmpty();
  const activeImage = page.locator('.pswp__item[aria-hidden="false"] .pswp__img:not(.pswp__img--placeholder)');
  await expect(activeImage).toHaveAttribute('src', 'https://images.example.test/photos/2025/photo-4.jpg');
  await expect.poll(() => activeImage.evaluate((image) => image.complete && image.naturalWidth > 0)).toBe(true);
  await expect(archivedImage).not.toHaveAttribute('src');

  await page.evaluate(() => history.back());
  await expect(page.locator('.pswp')).toBeHidden();
  await expect(page).toHaveURL(/\/photos\/$/);
  await expect.poll(() => page.evaluate(() => Boolean(history.state?.quietypeLightbox))).toBe(false);

  await page.locator('.photo-frame img').first().click();
  await expect(page.locator('.pswp')).toBeVisible();
  await expect.poll(() => page.evaluate(() => Boolean(window.pswp?.opener?.isOpen))).toBe(true);
  await page.keyboard.press('Escape');
  await expect(page.locator('.pswp')).toBeHidden();
  await expect(page).toHaveURL(/\/photos\/$/);
  await expect.poll(() => page.evaluate(() => Boolean(history.state?.quietypeLightbox))).toBe(false);
});

test('photo lightbox retries one failed archive image without reusing its cache entry', async ({ page }) => {
  const retryRequests = [];
  await page.route('https://images.example.test/**', async (route) => {
    const url = route.request().url();
    if (url.includes('photo-4.jpg') && !url.includes('quietype_retry=')) {
      await route.fulfill({ status: 503, contentType: 'text/plain', body: 'temporary image failure' });
      return;
    }
    if (url.includes('photo-4.jpg') && url.includes('quietype_retry=')) retryRequests.push(url);
    await route.fulfill({ status: 200, contentType: 'image/gif', body: Buffer.from('R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=', 'base64') });
  });
  await page.goto('/photos/');
  await page.locator('.photo-frame img').first().click();
  await expect(page.locator('.pswp')).toBeVisible();
  await page.evaluate(() => window.pswp.goTo(3));
  await expect(page.locator('.pswp__quietype-caption strong')).toHaveText('暮色归舟');
  await expect.poll(() => retryRequests.length).toBe(1);
  const activeImage = page.locator('.pswp__item[aria-hidden="false"] .pswp__img:not(.pswp__img--placeholder)');
  await expect(activeImage).toHaveAttribute('src', /photo-4\.jpg\?quietype_retry=[0-9]+$/);
  await expect.poll(() => activeImage.evaluate((image) => image.complete && image.naturalWidth > 0)).toBe(true);
});

test('mobile lightbox arrows share the standard control visibility state', async ({ page }) => {
  test.skip(page.viewportSize().width > 720, 'Mobile-only lightbox controls.');
  await page.route('https://images.example.test/**', async (route) => {
    await route.fulfill({ status: 200, contentType: 'image/gif', body: Buffer.from('R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=', 'base64') });
  });
  await page.goto('/photos/');
  await page.locator('.photo-frame img').first().click();
  await expect(page.locator('.pswp')).toBeVisible();
  await expect.poll(() => page.evaluate(() => Boolean(window.pswp?.opener?.isOpen))).toBe(true);
  const previous = page.locator('.pswp__button--arrow--prev');
  const next = page.locator('.pswp__button--arrow--next');
  await expect(previous).toBeVisible();
  await expect(next).toBeVisible();
  await expect(previous).toHaveCSS('width', '48px');
  await expect(next).toHaveCSS('width', '48px');
  await expect(previous).toHaveCSS('opacity', '0.82');
  await expect(next).toHaveCSS('opacity', '0.82');
  const arrowAlignment = await page.evaluate(() => {
    const measurements = ['prev', 'next'].map((direction) => {
      const button = document.querySelector(`.pswp__button--arrow--${direction}`).getBoundingClientRect();
      const glyphSelector = direction === 'prev' ? 'path' : 'use:not(.pswp__icn-shadow)';
      const glyph = document.querySelector(`.pswp__button--arrow--${direction} ${glyphSelector}`).getBoundingClientRect();
      return {
        x: (glyph.left + glyph.right) / 2 - (button.left + button.right) / 2,
        y: (glyph.top + glyph.bottom) / 2 - (button.top + button.bottom) / 2,
      };
    });
    return measurements;
  });
  expect(Math.abs(arrowAlignment[0].x)).toBeLessThan(0.5);
  expect(Math.abs(arrowAlignment[0].y)).toBeLessThan(0.5);
  expect(Math.abs(arrowAlignment[1].x)).toBeLessThan(0.5);
  expect(Math.abs(arrowAlignment[1].y)).toBeLessThan(0.5);
  await expect.poll(() => page.evaluate(() => {
    const image = document.querySelector('.pswp__item[aria-hidden="false"] .pswp__img:not(.pswp__img--placeholder)');
    const box = image.getBoundingClientRect();
    return Math.abs((box.top + box.bottom) / 2 - window.innerHeight / 2);
  })).toBeLessThan(0.5);
  const captionAlignment = await page.evaluate(() => {
    const image = document.querySelector('.pswp__item[aria-hidden="false"] .pswp__img:not(.pswp__img--placeholder)').getBoundingClientRect();
    const caption = document.querySelector('.pswp__quietype-caption').getBoundingClientRect();
    return {
      viewportBottomGap: window.innerHeight - caption.bottom,
      imageGap: caption.top - image.bottom,
    };
  });
  expect(Math.abs(captionAlignment.viewportBottomGap - 14)).toBeLessThan(0.5);
  expect(captionAlignment.imageGap).toBeGreaterThan(100);
  await next.tap();
  await expect(page.locator('.pswp__quietype-caption strong')).toHaveText('窗外');
  await expect.poll(() => page.evaluate(() => !window.pswp?.mainScroll?.isShifted())).toBe(true);
  await expect.poll(() => page.evaluate(() => window.pswp?.animations?.activeAnimations?.length || 0)).toBe(0);

  const viewport = page.viewportSize();
  await page.touchscreen.tap(viewport.width / 2, viewport.height / 2);
  await expect(page.locator('.pswp')).not.toHaveClass(/pswp--ui-visible/);
  await expect(previous).toHaveCSS('opacity', '0');
  await expect(next).toHaveCSS('opacity', '0');
  await expect(previous).toHaveCSS('pointer-events', 'none');
  await expect(next).toHaveCSS('pointer-events', 'none');
  await page.touchscreen.tap(viewport.width / 2, viewport.height / 2);
  await expect(page.locator('.pswp')).toHaveClass(/pswp--ui-visible/);
  await expect(previous).toHaveCSS('opacity', '0.82');
  await expect(next).toHaveCSS('opacity', '0.82');
});

test('full-resolution photo remains an explicit lightbox action', async ({ page }) => {
  const originalUrl = 'https://images.example.test/original-opt-in.jpg';
  const originalRequests = [];
  page.on('request', (request) => {
    if (request.url() === originalUrl) originalRequests.push(request.url());
  });
  await page.route('https://images.example.test/**', async (route) => {
    await route.fulfill({ status: 200, contentType: 'image/gif', body: Buffer.from('R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=', 'base64') });
  });
  await page.goto('/photos/');
  const frame = page.locator('.photo-frame').first();
  await frame.evaluate((element, original) => {
    element.dataset.pswpSrc = 'https://images.example.test/display.jpg';
    element.dataset.pswpWidth = '1';
    element.dataset.pswpHeight = '1';
    element.dataset.photoOriginal = original;
  }, originalUrl);

  await frame.locator('img').click();
  await expect(page.locator('.pswp')).toBeVisible();
  await expect(page.locator('.pswp__quietype-caption a')).toHaveAttribute('href', originalUrl);
  await expect(page.locator('.pswp__quietype-caption a')).toBeVisible();
  expect(originalRequests).toEqual([]);
});

test('book archive groups compact reading records by year', async ({ page }) => {
  await page.goto('/books/');
  await expect(page.locator('.books-hero h1')).toHaveText('万卷古今');
  await expect(page.locator('.books-hero .eyebrow')).toHaveText('BOOKS');
  await expect(page.locator('.books-hero__meta .collection-intro')).toHaveCount(0);
  await expect(page.locator('.books-hero__meta .collection-stats')).toHaveText('2023—2026 · 8 本');
  await expect(page.locator('.book-item')).toHaveCount(8);
  await expect(page.locator('.book-year-shelf')).toHaveCount(4);
  await expect(page.locator('.book-year-heading h2')).toHaveText(['2026', '2025', '2024', '2023']);
  await expect(page.locator('.book-year-index')).toHaveClass(/book-year-index--count-4/);
  await expect(page.locator('.book-year-shelf').nth(0)).toHaveAttribute('data-expanded', 'true');
  await expect(page.locator('.book-year-shelf').nth(1)).toHaveAttribute('data-expanded', 'true');
  await expect(page.locator('.book-year-shelf').nth(2)).toHaveAttribute('data-expanded', 'false');
  await expect(page.locator('.book-year-shelf').nth(3)).toHaveAttribute('data-expanded', 'false');
  await expect(page.locator('.book-grid').nth(1)).toBeVisible();
  await expect(page.locator('.book-grid').nth(2)).toBeHidden();
  await expect(page.locator('.book-year-index a').nth(2)).toHaveAttribute('aria-expanded', 'false');
  await expect(page.locator('.book-title-row h3 a').first()).toHaveAttribute('href', 'https://example.com/books/programming-pearls');
  await expect(page.locator('.book-item').filter({ hasText: '小王子' }).locator('.book-title-row h3 a')).toHaveCount(0);
  await expect(page.locator('a.book-cover')).toHaveCount(0);
  await expect(page.locator('.book-cover').first()).toHaveJSProperty('tagName', 'DIV');
  const localCover = page.locator('.book-cover img.attachment-quietype-book-cover');
  await expect(localCover).toHaveCount(1);
  await expect(localCover).toHaveAttribute('src', /quietype-test-book-cover-252x372\.jpg$/);
  await expect(localCover).toHaveAttribute('sizes', /(?:auto, )?\(max-width: 720px\) 76px, 84px/);
  await expect(localCover).toHaveAttribute('width', '252');
  await expect(localCover).toHaveAttribute('height', '372');
  await expect(page.locator('.book-terms .post-category').first()).toBeVisible();
  await expect(page.locator('.book-terms .post-tag').first()).toContainText('#');
  await expect(page.locator('.book-cover__fallback').first()).toBeVisible();
  await expect(page.locator('.book-status')).toContainText(['读完', '在读', '待读', '读过', '读完', '读完', '读完', '读完']);
  await expect(page.locator('.personal-rating').first()).not.toContainText('评分');
  await expect(page.locator('.personal-rating').first()).toHaveAttribute('aria-label', /^个人评分 .*满分 5 星$/);
  await expect(page.locator('.book-read-date').first()).toHaveText(/^20[0-9]{2}\.[0-9]{2}$/);
  await expect(page.locator('.book-evaluation__summary > .douban-rating').first()).toBeVisible();
  const firstEvaluation = page.locator('.book-evaluation').first();
  const firstNote = page.locator('.book-note').first();
  expect(await firstEvaluation.evaluate((element) => element.compareDocumentPosition(document.querySelector('.book-note')) & Node.DOCUMENT_POSITION_FOLLOWING)).toBeTruthy();
  expect((await firstEvaluation.boundingBox()).y).toBeLessThan((await firstNote.boundingBox()).y);

  if (page.viewportSize().width <= 720) {
    const yearBoxes = await page.locator('.book-year-index a').evaluateAll((links) => links.map((link) => {
      const box = link.getBoundingClientRect();
      return { x: Math.round(box.x), y: Math.round(box.y), width: Math.round(box.width) };
    }));
    expect(new Set(yearBoxes.map((box) => box.width)).size).toBe(1);
    expect(yearBoxes[0].x).toBe(yearBoxes[2].x);
    expect(yearBoxes[1].x).toBe(yearBoxes[3].x);
    expect(yearBoxes[2].y).toBeGreaterThan(yearBoxes[0].y);
  }
});

test('book year controls and record hashes reveal collapsed shelves', async ({ page }) => {
  await page.goto('/books/');
  const archivedYear = page.locator('.book-year-shelf').nth(2);
  await archivedYear.locator('.book-year-toggle').click();
  await expect(archivedYear).toHaveAttribute('data-expanded', 'true');
  await expect(archivedYear.locator('.book-grid')).toBeVisible();
  await expect(page.locator('.book-year-index a').nth(2)).toHaveAttribute('aria-expanded', 'true');

  const recordId = await page.locator('.book-year-shelf').nth(3).locator('.book-item').first().getAttribute('id');
  expect(recordId).toBeTruthy();
  await page.goto(`/books/#${recordId}`);
  const recordYear = page.locator('.book-year-shelf').nth(3);
  await expect(recordYear).toHaveAttribute('data-expanded', 'true');
  await expect(recordYear.locator('.book-grid')).toBeVisible();
  await expect(page.locator(`#${recordId}`)).toBeVisible();
});

test('an incomplete mobile year row occupies only its real cells', async ({ page }) => {
  test.skip(page.viewportSize().width > 720, 'Mobile-only year-grid assertion.');
  await page.goto('/books/');
  const layout = await page.locator('.book-year-index').evaluate((navigation) => {
    const template = navigation.querySelector('a');
    while (navigation.children.length < 7) navigation.append(template.cloneNode(true));
    navigation.className = 'book-year-index book-year-index--count-7 book-year-index--remainder-1';
    const links = [...navigation.querySelectorAll('a')];
    const first = links[0].getBoundingClientRect();
    const last = links[6].getBoundingClientRect();
    const emptyTarget = document.elementFromPoint(last.right + last.width / 2, last.top + last.height / 2);
    return {
      first: { x: Math.round(first.x), width: Math.round(first.width) },
      last: { x: Math.round(last.x), width: Math.round(last.width) },
      lastBorderBottom: getComputedStyle(links[6]).borderBottomWidth,
      navigationBorderBottom: getComputedStyle(navigation).borderBottomWidth,
      emptyCellIsLink: Boolean(emptyTarget?.closest('a'))
    };
  });

  expect(layout.last).toEqual(layout.first);
  expect(layout.lastBorderBottom).toBe('1px');
  expect(layout.navigationBorderBottom).toBe('0px');
  expect(layout.emptyCellIsLink).toBe(false);
});

test('standalone book routes return to the matching archive record', async ({ request }) => {
  const response = await request.get('/books/programming-pearls/', { maxRedirects: 0 });
  expect(response.status()).toBe(301);
  expect(response.headers().location).toMatch(/\/books\/?#book-[0-9]+$/);
});

test('former reading routes redirect permanently', async ({ request }) => {
  const archive = await request.get('/reading/', { maxRedirects: 0 });
  expect(archive.status()).toBe(301);
  expect(archive.headers().location).toMatch(/\/books\/$/);

  const book = await request.get('/reading/programming-pearls/', { maxRedirects: 0 });
  expect(book.status()).toBe(301);
  expect(book.headers().location).toMatch(/\/books\/?#book-[0-9]+$/);
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

test('mobile menu keeps the sticky header visible after scrolling', async ({ page }) => {
  test.skip(page.viewportSize().width > 500, 'Mobile-only sticky header assertion.');
  await page.goto('/quietype-reading-test/');
  await page.evaluate(() => window.scrollTo(0, 480));
  await expect.poll(() => page.evaluate(() => window.scrollY)).toBeGreaterThan(300);
  const scrollPosition = await page.evaluate(() => window.scrollY);

  await page.locator('.nav-toggle').click();
  await expect(page.locator('html')).toHaveClass(/nav-open/);
  await expect(page.locator('body')).toHaveClass(/nav-open/);
  await expect(page.locator('html')).toHaveCSS('overflow-y', 'hidden');
  await expect.poll(() => page.locator('.site-header').evaluate((header) => Math.abs(header.getBoundingClientRect().top))).toBeLessThan(0.5);
  await expect.poll(() => page.evaluate(() => window.scrollY)).toBe(scrollPosition);
  await expect.poll(() => page.evaluate(() => Boolean(document.elementFromPoint(window.innerWidth / 2, 32)?.closest('.site-header')))).toBe(true);

  await page.locator('.nav-toggle').click();
  await expect(page.locator('html')).not.toHaveClass(/nav-open/);
  await expect(page.locator('body')).not.toHaveClass(/nav-open/);
  await expect.poll(() => page.evaluate(() => window.scrollY)).toBe(scrollPosition);
});
