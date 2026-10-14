const { test, expect } = require('@playwright/test');
const fs = require('node:fs');
const path = require('node:path');
const AxeBuilder = require('@axe-core/playwright').default;
const source = fs.readFileSync(path.join(__dirname, '../fixtures/markdown.md'), 'utf8');

test('saved Markdown renders code, formulas, figures and diagrams without Editor.md', async ({ page }) => {
  const errors = [];
  const failed = [];
  page.on('pageerror', (error) => errors.push(error.message));
  page.on('response', (response) => { if (response.status() >= 400) failed.push(response.url()); });
  await page.goto('/quietype-markdown-test/', { waitUntil: 'networkidle' });
  await expect(page.locator('pre code.language-python .token.keyword').first()).toBeAttached();
  await expect(page.locator('.line-numbers-rows')).toHaveCount(1);
  await expect(page.locator('.quietype-math .katex-html')).toHaveCount(5);
  await expect(page.locator('.katex-display')).toHaveCount(2);
  await expect(page.locator('figcaption')).toHaveText('图 1：图与说明一起预览');
  await expect(page.locator('caption')).toHaveText('表 1：内容保存方式');
  await page.locator('.mermaid-figure').scrollIntoViewIfNeeded();
  await expect(page.locator('.mermaid-figure')).toHaveAttribute('data-state', 'ready', { timeout: 15000 });
  await expect(page.locator('.mermaid-figure svg')).toHaveCount(1);
  await expect(page.locator('.footnote-ref')).toHaveAttribute('href', '#fn-note');
  await expect(page.locator('#fn-note')).toBeAttached();
  expect(await page.locator('script[src*="wp-editormd"], link[href*="wp-editormd"]').count()).toBe(0);
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
  expect(errors).toEqual([]);
  expect(failed).toEqual([]);
  const accessibility = await new AxeBuilder({ page }).include('.article-content').analyze();
  expect(accessibility.violations.filter((item) => ['serious', 'critical'].includes(item.impact))).toEqual([]);
});

test('editor uses the server preview and persists the exact Markdown source', async ({ page }, testInfo) => {
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  const mobile = testInfo.project.name.includes('mobile');
  await page.goto('/wp-login.php');
  await page.locator('#user_login').fill(mobile ? 'markdown-mobile' : 'markdown-desktop');
  await page.locator('#user_pass').fill('password');
  await Promise.all([page.waitForURL(/wp-admin/), page.locator('#wp-submit').click()]);
  await page.goto('/wp-admin/post-new.php', { waitUntil: 'networkidle' });
  await expect(page.locator('.quietype-markdown-editor')).toBeVisible();
  await page.locator('#title').fill(`Markdown ${testInfo.project.name} draft`);
  // A real old math article may have no code at all: only KaTeX should load.
  await page.evaluate(() => document.querySelector('.CodeMirror').CodeMirror.setValue('## 只有公式\n\n$P(H|E)$\n\n$$0$$\n\n\\(x_1\\)'));
  await page.locator('[data-md-view="split"]').click();
  const preview = page.frameLocator('.quietype-markdown-editor__preview');
  await expect(preview.locator('.quietype-math .katex-html')).toHaveCount(3);
  expect(await preview.locator('annotation').allTextContents()).toEqual(['P(H|E)', '0', 'x_1']);
  await expect(preview.locator('script[src*="vendor/prism"]')).toHaveCount(0);
  await expect(preview.locator('.katex-error')).toHaveCount(0);
  await page.evaluate((text) => document.querySelector('.CodeMirror').CodeMirror.setValue(text), source);
  await expect(page.locator('#content')).toHaveValue(source);
  await expect(preview.locator('h2').first()).toContainText('一份可以继续编辑的原文');
  await expect(preview.locator('.quietype-math .katex-html')).toHaveCount(5);
  await expect(preview.locator('pre code.language-python .token.keyword').first()).toBeAttached();
  await expect(preview.locator('figcaption')).toHaveText('图 1：图与说明一起预览');
  await preview.locator('.mermaid-figure').scrollIntoViewIfNeeded();
  await expect(preview.locator('.mermaid-figure')).toHaveAttribute('data-state', 'ready', { timeout: 15000 });
  await page.screenshot({ path: `artifacts/markdown-editor-${mobile ? 'mobile' : 'desktop'}.png`, fullPage: true });
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);

  const rejected = await page.evaluate(async () => {
    const settings = window.quietypeMarkdownEditor;
    const response = await fetch(settings.url, { method: 'POST', body: new URLSearchParams({ action: 'quietype_markdown_preview', post_id: settings.postId, quietype_markdown_nonce: 'invalid', content: '**test**' }) });
    return response.status;
  });
  expect(rejected).toBe(403);
  await page.locator('[data-md-view="write"]').click();
  await Promise.all([page.waitForURL(/post\.php\?post=\d+&action=edit/), page.locator('#save-post').click()]);
  await expect(page.locator('#content')).toHaveValue(source);
  await expect(page.locator('.quietype-markdown-editor')).toBeVisible();
  await page.reload();
  await expect(page.locator('#content')).toHaveValue(source);
  const [fullPreview] = await Promise.all([
    page.waitForEvent('popup'),
    page.locator('#post-preview').click()
  ]);
  await fullPreview.waitForLoadState('domcontentloaded');
  await expect(fullPreview.locator('.quietype-math .katex-html')).toHaveCount(5);
  await expect(fullPreview.locator('code.language-python .token.keyword').first()).toBeAttached();
  await expect(fullPreview.locator('figcaption')).toHaveText('图 1：图与说明一起预览');
  await fullPreview.close();

  // The browser's emergency local backup must restore the visible editor too.
  const recovered = source + '\n\n**来自本地自动备份**';
  await page.evaluate((text) => {
    wp.autosave.server.suspend();
    wp.autosave.local.save({ post_title: document.querySelector('#title').value, content: text, excerpt: '' });
  }, recovered);
  await page.reload();
  await page.locator('.restore-backup').click();
  await expect(page.locator('#content')).toHaveValue(recovered);
  expect(await page.evaluate(() => document.querySelector('.CodeMirror').CodeMirror.getValue())).toBe(recovered);
  expect(errors).toEqual([]);
});

test('enabling the module adds no Markdown resources to the home page', async ({ page }) => {
  const requested = [];
  page.on('request', (request) => requested.push(request.url()));
  await page.goto('/', { waitUntil: 'networkidle' });
  expect(requested.some((url) => /markdown-(editor|render)|vendor\/(prism|katex)/.test(url))).toBe(false);
});
