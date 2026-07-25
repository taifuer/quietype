const { test, expect } = require('@playwright/test');

test('Douban lookup previews data before manual confirmation', async ({ page }) => {
  test.skip(page.viewportSize().width < 700, 'The administration flow is viewport-independent.');
	await page.goto('/wp-login.php');
  await page.locator('#user_login').fill('admin');
  await page.locator('#user_pass').fill('password');
  await page.locator('#wp-submit').click();

  await page.route('**/wp-admin/admin-ajax.php', async (route) => {
    const request = route.request();
    if (request.postData()?.includes('action=quietype_lookup_book')) {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: {
            title: '佩索阿：最后的时光',
            authors: '[法] 尼古拉·巴拉尔 著绘',
            publisher: '湖南文艺出版社',
            publication_year: '2026',
            isbn: '9787572629068',
            douban_rating: '8.9',
            douban_id: '38380879',
            douban_url: 'https://book.douban.com/subject/38380879/',
            cover_url: ''
          }
        })
      });
      return;
    }
    await route.continue();
  });

  await page.goto('/wp-admin/post-new.php?post_type=book', { waitUntil: 'domcontentloaded' });
  const fieldWidths = await page.locator([
    '#quietype_book_douban_input',
    '#quietype_book_authors',
    '#quietype_book_publisher',
    '#quietype_book_publication_year',
    '#quietype_book_isbn',
    '#quietype_book_read_date',
    '#quietype_book_rating',
    '#quietype_book_douban_rating',
    '#quietype_book_douban_url'
  ].join(',')).evaluateAll((fields) => [...new Set(fields.map((field) => Math.round(field.getBoundingClientRect().width)))]);
  expect(fieldWidths).toEqual([420]);

  await page.locator('#quietype_book_douban_input').fill('38380879');
  await page.locator('#quietype-book-lookup').click();

  await expect(page.locator('#quietype-book-preview')).toBeVisible();
  await expect(page.locator('#quietype-book-preview-title')).toHaveText('佩索阿：最后的时光');
  await expect(page.locator('#quietype_book_authors')).toHaveValue('');
  await expect(page.locator('#quietype_book_douban_url')).toHaveValue('');

  await page.locator('#quietype-book-confirm').click();
  await expect(page.locator('#quietype_book_authors')).toHaveValue('[法] 尼古拉·巴拉尔 著绘');
  await expect(page.locator('#quietype_book_publisher')).toHaveValue('湖南文艺出版社');
  await expect(page.locator('#quietype_book_douban_url')).toHaveValue('https://book.douban.com/subject/38380879/');
  await expect(page.locator('#quietype-book-lookup-status')).toContainText('资料已填入表单');
});
