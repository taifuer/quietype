const { test, expect } = require('@playwright/test');

// Generate a tiny original test clip in Chromium. No external media/network,
// downloaded binary, codec toolchain or licensed footage is needed by CI.
async function createClip(browser) {
  const page = await browser.newPage();
  try {
    const bytes = await page.evaluate(async () => {
      const canvas = document.createElement('canvas');
      canvas.width = 732;
      canvas.height = 208;
      const context = canvas.getContext('2d');
      const stream = canvas.captureStream(10);
      const recorder = new MediaRecorder(stream, { mimeType: 'video/webm;codecs=vp8' });
      const chunks = [];
      recorder.ondataavailable = event => chunks.push(event.data);
      const finished = new Promise(resolve => { recorder.onstop = resolve; });
      recorder.start();
      for (let frame = 0; frame < 20; frame++) {
        context.fillStyle = '#fdfdfb';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.fillStyle = '#795844';
        context.fillRect(20 + frame * 20, 60, 60, 60);
        await new Promise(resolve => setTimeout(resolve, 100));
      }
      recorder.stop();
      await finished;
      stream.getTracks().forEach(track => track.stop());
      return Array.from(new Uint8Array(await new Blob(chunks).arrayBuffer()));
    });
    return Buffer.from(bytes);
  } finally {
    await page.close();
  }
}

async function routeMedia(page, clip) {
  await page.route('https://media.example.test/**', async route => {
    if (route.request().url().endsWith('/poster.svg')) {
      return route.fulfill({ contentType: 'image/svg+xml', body: '<svg xmlns="http://www.w3.org/2000/svg" width="1464" height="416"><rect width="1464" height="416" fill="#ece8e0"/></svg>' });
    }
    const range = route.request().headers().range?.match(/bytes=(\d+)-(\d*)/);
    const start = range ? Number(range[1]) : 0;
    const end = range?.[2] ? Math.min(Number(range[2]), clip.length - 1) : clip.length - 1;
    await route.fulfill({
      status: range ? 206 : 200,
      contentType: 'video/webm', body: clip.subarray(start, end + 1),
      headers: { 'Accept-Ranges': 'bytes', ...(range ? { 'Content-Range': `bytes ${start}-${end}/${clip.length}` } : {}) }
    });
  });
}

function videoTests() {
  let clip;
  test.beforeAll(async ({ browser }) => { clip = await createClip(browser); });
  test.beforeEach(async ({ page }) => { await routeMedia(page, clip); });

  test('videos fit the article and defer media requests without an extra player', async ({ page }) => {
    const requests = [];
    const errors = [];
    page.on('request', request => requests.push(request.url()));
    page.on('pageerror', error => errors.push(error.message));
    await page.goto('/quietype-video-test/', { waitUntil: 'networkidle' });
    await expect(page.locator('.article-content video')).toHaveCount(3);
    await expect(page.locator('video.quietype-video')).toHaveCount(2);
    const videos = page.locator('.article-content video');
    for (const video of await videos.all()) {
      await expect(video).toHaveAttribute('controls', '');
      await expect(video).toHaveAttribute('preload', 'none');
      expect(await video.evaluate(element => {
        const rect = element.getBoundingClientRect();
        const article = element.closest('.article-content').getBoundingClientRect();
        return rect.left >= article.left - 1 && rect.right <= article.right + 1;
      })).toBe(true);
    }
    expect(requests.filter(url => /media\.example\.test\/.*\.webm/.test(url))).toEqual([]);
    expect(requests.filter(url => /plyr|mediaelement/i.test(url))).toEqual([]);
    await expect(page.locator('.article-content pre')).toContainText('[video src="https://media.example.test/example.webm"]');
    expect(await page.evaluate(() => document.documentElement.scrollWidth - innerWidth)).toBeLessThanOrEqual(1);
    expect(errors).toEqual([]);
  });

  test('a native video plays and keeps its real aspect ratio after loading', async ({ page }) => {
    await page.goto('/quietype-video-test/');
    const video = page.locator('video.quietype-video').first();
    await video.scrollIntoViewIfNeeded();
    await video.evaluate(async element => { element.muted = true; await element.play(); });
    await expect.poll(() => video.evaluate(element => element.currentTime)).toBeGreaterThan(0.1);
    await video.evaluate(element => element.pause());
    expect(await video.evaluate(element => element.paused)).toBe(true);
    const sizes = await video.evaluate(element => ({ width: element.clientWidth, height: element.clientHeight, ratio: element.videoWidth / element.videoHeight }));
    expect(sizes.width / sizes.height).toBeCloseTo(sizes.ratio, 1);
    expect(await page.evaluate(() => document.documentElement.scrollWidth - innerWidth)).toBeLessThanOrEqual(1);
  });

  test('native controls remain usable when page JavaScript is disabled', async ({ browser }, testInfo) => {
    const context = await browser.newContext({ javaScriptEnabled: false, viewport: testInfo.project.use.viewport });
    try {
      const page = await context.newPage();
      await routeMedia(page, clip);
      await page.goto('/quietype-video-test/');
      const video = page.locator('video.quietype-video').first();
      await expect(video).toBeVisible();
      await video.evaluate(async element => { element.muted = true; await element.play(); });
      await expect.poll(() => video.evaluate(element => element.currentTime)).toBeGreaterThan(0.1);
    } finally {
      await context.close();
    }
  });
}

module.exports = { createClip, routeMedia, videoTests };
