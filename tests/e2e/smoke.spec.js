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

test('photo archive groups external images and keeps details in the lightbox', async ({ page }) => {
  const originalRequests = [];
  const deferredRequests = [];
  page.on('request', (request) => {
    if (request.url().includes('photo-1-original.jpg')) originalRequests.push(request.url());
    if (/photo-[4-6]\.jpg/.test(request.url())) deferredRequests.push(request.url());
  });
  await page.route('https://images.example.test/**', async (route) => {
    await route.fulfill({ status: 200, contentType: 'image/gif', body: Buffer.from('R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=', 'base64') });
  });
  await page.goto('/photos/');
  await expect(page.locator('.photos-hero h1')).toHaveText('万物静观');
  await expect(page.locator('.photos-hero .eyebrow')).toHaveText('PHOTOS');
  await expect(page.locator('.photos-hero__meta')).toHaveCount(0);
  await expect(page.locator('.photo-card')).toHaveCount(6);
  await expect(page.locator('.photo-year')).toHaveCount(3);
  await expect(page.locator('.photo-year__heading h2')).toHaveText(['2026', '2025', '2024']);
  await expect(page.locator('.photo-year-index')).toHaveClass(/photo-year-index--count-3/);
  await expect(page.locator('.photo-year').nth(0)).toHaveAttribute('data-expanded', 'true');
  await expect(page.locator('.photo-year').nth(1)).toHaveAttribute('data-expanded', 'false');
  await expect(page.locator('.photo-year').nth(2)).toHaveAttribute('data-expanded', 'false');
  await expect(page.locator('.photo-grid').first()).toBeVisible();
  await expect(page.locator('.photo-grid').nth(1)).toBeHidden();
  await expect(page.locator('.photo-year').nth(1).locator('img').first()).not.toHaveAttribute('src');
  await expect(page.locator('.photo-year').nth(1).locator('img').first()).toHaveAttribute('data-src', 'https://images.example.test/photo-4.jpg');
  expect(deferredRequests).toEqual([]);
  await expect(page.locator('.photo-frame').first()).toHaveAttribute('data-photo-exif', '35mm · f/4 · 1/320s · ISO 160');
  await expect(page.locator('.photo-frame').first()).toHaveAttribute('data-photo-device', 'Xiaomi');
  await expect(page.locator('.photo-frame').first()).toHaveAttribute('data-photo-meta', '安徽 · 宏村 · 2026年7月');
  await expect(page.locator('.photo-caption small').first()).toHaveText('安徽 · 宏村 · 2026年7月');
  await expect(page.locator('.photo-frame').first()).toHaveAttribute('data-photo-original', 'https://images.example.test/photo-1-original.jpg');
  await expect(page.locator('.photo-frame img').first()).toHaveAttribute('referrerpolicy', 'no-referrer');
  expect(originalRequests).toEqual([]);

  await page.locator('.photo-year').nth(1).locator('.photo-year__toggle').click();
  await expect(page.locator('.photo-year').nth(1)).toHaveAttribute('data-expanded', 'true');
  await expect(page.locator('.photo-grid').nth(1)).toBeVisible();
  await expect(page.locator('.photo-year').nth(1).locator('img').first()).toHaveAttribute('src', 'https://images.example.test/photo-4.jpg');
  await expect(page.locator('.photo-year').nth(1).locator('img').first()).toHaveAttribute('loading', 'eager');
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
  await expect(targetYear.locator('img').first()).toHaveAttribute('src', 'https://images.example.test/photo-6.jpg');
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
  const activeImage = page.locator('.pswp__item[aria-hidden="false"] .pswp__img:not(.pswp__img--placeholder)');
  await expect(activeImage).toHaveAttribute('src', 'https://images.example.test/photo-4.jpg');
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
  await expect(page.locator('.books-hero__meta')).toHaveCount(0);
  await expect(page.locator('.book-item')).toHaveCount(8);
  await expect(page.locator('.book-year-shelf')).toHaveCount(4);
  await expect(page.locator('.book-year-heading h2')).toHaveText(['2026', '2025', '2024', '2023']);
  await expect(page.locator('.book-year-index')).toHaveClass(/book-year-index--count-4/);
  await expect(page.locator('.book-title-row h3 a').first()).toHaveAttribute('href', /^https:\/\/book\.douban\.com\/subject\/[0-9]+\/$/);
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
  await expect(page.locator('.book-status')).toContainText(['已读', '在读', '待读', '已读', '已读', '已读', '已读', '已读']);
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
