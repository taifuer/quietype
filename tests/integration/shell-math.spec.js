const { test, expect } = require('@playwright/test');

const shell = `ubuntu@EXP:~$ gcc main.c -o main
ubuntu@EXP:~$ ./main
printf '%s\\n' "$HOME" "\${USER}" "$(pwd)" "$$"
awk '{print $1, $2}' input.txt
printf '%s\\n' '$x$' '$$x^2$$' '<em>$literal</em>'
`;

test('Editor.md preserves Shell dollars and copied code while rendering prose math', async ({ page }) => {
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  await page.route('**/wp-admin/admin-ajax.php', (route) => route.abort());
  const response = await page.goto('/quietype-shell-math-test/', { waitUntil: 'networkidle' });
  expect(response.status()).toBe(200);
  const markup = await response.text();
  const serverCode = await page.evaluate((html) => {
    const document = new DOMParser().parseFromString(html, 'text/html');
    return {
      text: document.querySelector('.article-content pre code').textContent,
      mathInCode: document.querySelectorAll('code .katex, pre .katex').length
    };
  }, markup);
  expect(serverCode).toEqual({ text: shell, mathInCode: 0 });
  const code = page.locator('.article-content pre code');
  expect(await code.textContent()).toBe(shell);
  await expect(code.locator('.katex, .math')).toHaveCount(0);
  await expect(code.locator('.token').first()).toBeAttached();
  await expect(page.locator('.article-content p > code')).toHaveText([
    'echo "$HOME" "$USER"', '$x^2$', '$$x^2$$'
  ]);
  await expect(page.locator('.article-content .katex-html')).toHaveCount(4);
  await expect(page.locator('.article-content .katex-display')).toHaveCount(2);
  await expect(page.locator('.article-content .katex-error, .article-content .err')).toHaveCount(0);
  await page.evaluate(() => Object.defineProperty(navigator, 'clipboard', {
    configurable: true,
    value: { writeText: async (text) => { window.copiedCode = text; } }
  }));
  await page.locator('.copy-code').click();
  expect(await page.evaluate(() => window.copiedCode)).toBe(shell);
  expect(await page.evaluate(() => document.documentElement.scrollWidth - innerWidth)).toBeLessThanOrEqual(1);
  expect(errors).toEqual([]);
});
