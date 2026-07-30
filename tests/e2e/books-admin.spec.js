const { test, expect } = require('@playwright/test');

async function logIn(page) {
  await page.goto('/wp-login.php');
  await page.locator('#user_login').fill('books-admin');
  await page.locator('#user_pass').fill('password');
  await Promise.all([
    page.waitForURL(/\/wp-admin\//),
    page.locator('#wp-submit').click()
  ]);
}

test('Douban lookup previews data before manual confirmation', async ({ page }) => {
  test.skip(page.viewportSize().width < 700, 'The administration flow is viewport-independent.');
	await logIn(page);

  await page.route('https://img9.doubanio.com/**', async (route) => {
    await route.fulfill({ status: 404, body: '' });
  });
  await page.route('**/wp-admin/admin-ajax.php', async (route) => {
    const request = route.request();
    if (request.postData()?.includes('quietype_lookup_book')) {
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
            cover_url: 'https://img9.doubanio.com/view/subject/s/public/s-invalid.jpg'
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
		'#quietype_book_cover_url',
    '#quietype_book_read_date',
    '#quietype_book_status',
    '#quietype_book_rating',
    '#quietype_book_douban_rating',
    '#quietype_book_url'
  ].join(',')).evaluateAll((fields) => [...new Set(fields.map((field) => Math.round(field.getBoundingClientRect().width)))]);
  expect(fieldWidths).toEqual([420]);
  await expect(page.locator('#quietype_book_read_date')).toHaveAttribute('type', 'month');
  await expect(page.locator('#quietype_book_status')).toHaveValue('read');
  await expect(page.locator('#quietype_book_status option')).toHaveText(['读完', '在读', '读过', '待读']);
  await expect(page.locator('#quietype_book_status + .description')).toContainText('读了一部分');
	await expect(page.locator('#quietype_book_cover_url')).toHaveAttribute('placeholder', 'https://example.com/images/book-cover.jpg');
	await expect(page.locator('#quietype_book_cover_url + .description')).toContainText('优先于特色图');
	await expect(page.locator('label[for="quietype_book_url"]')).toHaveText('链接');
	await expect(page.locator('#quietype_book_url + .description')).toContainText('留空则不添加链接');
  await expect(page.locator('#postexcerpt .hndle')).toContainText('点评');
  await expect(page.locator('#postexcerpt #excerpt')).toBeVisible();
  await expect(page.locator('#postexcerpt .description')).toContainText('详细的阅读总结');

  await page.locator('#quietype_book_douban_input').fill('38380879');
  await page.locator('#quietype-book-lookup').click();

  await expect(page.locator('#quietype-book-preview')).toBeVisible();
  await expect(page.locator('#quietype-book-preview-title')).toHaveText('佩索阿：最后的时光');
  await expect(page.locator('#quietype-book-preview-cover')).toBeHidden();
  await expect(page.locator('#quietype-book-lookup-status')).toContainText('服务器尝试导入');
  await expect(page.locator('#quietype_book_authors')).toHaveValue('');
  await expect(page.locator('#quietype_book_url')).toHaveValue('');

  await page.locator('#quietype-book-confirm').click();
  const classicTitle = page.locator('#title');
  if (await classicTitle.count()) {
    await expect(classicTitle).toHaveValue('佩索阿：最后的时光');
    await expect(page.locator('#title-prompt-text')).toBeHidden();
  } else {
    await expect(page.locator('.editor-post-title__input')).toContainText('佩索阿：最后的时光');
  }
  await expect(page.locator('#quietype_book_authors')).toHaveValue('[法] 尼古拉·巴拉尔 著绘');
  await expect(page.locator('#quietype_book_publisher')).toHaveValue('湖南文艺出版社');
  await expect(page.locator('#quietype_book_url')).toHaveValue('https://book.douban.com/subject/38380879/');
  await expect(page.locator('#quietype_book_cover_url')).toHaveValue('');
  await expect(page.locator('#quietype_book_import_source_url')).toHaveValue('https://img9.doubanio.com/view/subject/s/public/s-invalid.jpg');
  await expect(page.locator('#quietype-book-import')).toBeVisible();
  await expect(page.locator('#quietype_book_import_cover')).toBeChecked();
  await expect(page.locator('#quietype-book-cover-preview')).toBeHidden();
  await expect(page.locator('#quietype-book-lookup-status')).toContainText('资料已填入表单');
});

test('book list keeps only compact operational reading metadata', async ({ page }) => {
  test.skip(page.viewportSize().width < 700, 'The administration table is viewport-independent.');
  await logIn(page);
  await page.goto('/wp-admin/edit.php?post_type=book', { waitUntil: 'domcontentloaded' });

  await expect(page.locator('th#quietype_book_reading')).toHaveText('阅读记录');
  await expect(page.locator('th#date')).toHaveCount(0);
  const programmingPearls = page.locator('#the-list tr').filter({ hasText: '编程珠玑' });
  await expect(programmingPearls.locator('.column-quietype_book_reading')).toContainText('读完 · 2026.06');
  const archiveLink = programmingPearls.locator('.row-actions .view a');
  await expect(archiveLink).toHaveText('在书架中查看');
  await expect(archiveLink).toHaveAttribute('href', /\/books\/?#book-[0-9]+$/);
  await programmingPearls.locator('.row-title').click();
  await expect(page.locator('#sample-permalink')).toHaveCount(0);
  await expect(page.locator('#slugdiv')).toHaveCount(0);
});

test('revision controls describe posts, pages, and books', async ({ page }) => {
  test.skip(page.viewportSize().width < 700, 'The settings screen is viewport-independent.');
  await logIn(page);
  await page.goto('/wp-admin/themes.php?page=quietype-settings', { waitUntil: 'domcontentloaded' });

  await expect(page.getByText('停止为文章、页面和书籍保存新的历史版本')).toBeVisible();
  await expect(page.locator('#quietype-section-revision-cleanup')).toContainText('文章、页面或书籍历史版本');
});

test('site policies remain configurable without site-specific defaults', async ({ page }) => {
  test.skip(page.viewportSize().width < 700, 'The settings screen is viewport-independent.');
  await logIn(page);
  await page.goto('/wp-admin/themes.php?page=quietype-settings', { waitUntil: 'domcontentloaded' });

  await expect(page.locator('#quietype_contact_email')).toHaveAttribute('placeholder', 'hello@example.com');
  await expect(page.locator('#quietype_github_url')).toHaveAttribute('placeholder', 'https://github.com/example');
  await expect(page.locator('#quietype_article_license')).toHaveValue('cc-by-nc-sa');
  await expect(page.locator('#quietype_article_author_url')).toHaveAttribute('placeholder', 'https://example.com/about/');
  await expect(page.locator('#quietype_schema_publisher_type')).toHaveValue('person');
  await expect(page.locator('#quietype-section-access h2')).toHaveText('访问优化');
  await expect(page.locator('#quietype_gravatar_base_url')).toHaveAttribute('placeholder', 'https://avatar.example.com/avatar/');
});

test('book and photo archive headings are configurable together', async ({ page }) => {
  test.skip(page.viewportSize().width < 700, 'The settings screen is viewport-independent.');
  await logIn(page);
  await page.goto('/wp-admin/themes.php?page=quietype-settings', { waitUntil: 'domcontentloaded' });

  await expect(page.locator('#quietype_books_page_title')).toHaveValue('万卷古今');
  await expect(page.locator('#quietype_books_page_eyebrow')).toHaveValue('BOOKS');
  await expect(page.locator('#quietype_books_page_intro')).toHaveAttribute('placeholder', '留空不显示');
  await expect(page.locator('#quietype_books_default_expanded_years')).toHaveValue('2');
  await expect(page.locator('#quietype_books_default_expanded_years')).toHaveAttribute('min', '0');
  await expect(page.locator('#quietype_books_default_expanded_years + .description')).toContainText('0 时全部展开');
  await expect(page.locator('#quietype_photos_page_title')).toHaveValue('万物静观');
  await expect(page.locator('#quietype_photos_page_eyebrow')).toHaveValue('PHOTOS');
  await expect(page.locator('#quietype_photos_page_intro')).toHaveAttribute('placeholder', '留空不显示');
  await expect(page.locator('#quietype_photos_default_expanded_years')).toHaveValue('1');
  await expect(page.locator('#quietype_photos_default_expanded_years')).toHaveAttribute('min', '0');
  await expect(page.locator('#quietype_photos_default_expanded_years + .description')).toContainText('0 时全部展开');
});
