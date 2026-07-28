const { test, expect } = require('@playwright/test');

async function logIn(page) {
  await page.goto('/wp-login.php');
  await page.locator('#user_login').fill('admin');
  await page.locator('#user_pass').fill('password');
  await Promise.all([
    page.waitForURL(/\/wp-admin\//),
    page.locator('#wp-submit').click()
  ]);
}

test('photo lookup previews metadata before manual confirmation', async ({ page }) => {
  test.skip(page.viewportSize().width < 700, 'The administration flow is viewport-independent.');
  await logIn(page);
  await page.route('https://pic.taifua.com/**', async (route) => {
    await route.fulfill({ status: 200, contentType: 'image/gif', body: Buffer.from('R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=', 'base64') });
  });
  await page.route('**/wp-admin/admin-ajax.php', async (route) => {
    if (route.request().postData()?.includes('quietype_lookup_photo')) {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: {
            url: 'https://pic.taifua.com/photos/changsha.jpg',
            width: '6000',
            height: '4000',
            captured_date: '2026-07',
            focal_length: '35mm',
            aperture: 'f/2.8',
            shutter_speed: '1/250s',
            iso: '100',
            camera: 'FUJIFILM X-T5',
            lens: 'XF 23mm F2 R WR',
            file_size: 1843200,
            is_oversized: false
          }
        })
      });
      return;
    }
    await route.continue();
  });

  await page.goto('/wp-admin/post-new.php?post_type=photo', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('#postexcerpt .hndle')).toContainText('照片说明');
  await expect(page.locator('#quietype_photo_captured_date')).toHaveAttribute('type', 'month');
  await expect(page.locator('#quietype_photo_image_url')).toHaveAttribute('placeholder', /pic\.taifua\.com/);

  await page.locator('#quietype_photo_lookup_url').fill('https://pic.taifua.com/photos/changsha.jpg');
  await page.locator('#quietype-photo-lookup').click();
  await expect(page.locator('#quietype-photo-preview')).toBeVisible();
  await expect(page.locator('#quietype-photo-preview-size')).toHaveText('6000 × 4000 · 1.8 MB');
  await expect(page.locator('#quietype-photo-preview-exif')).toHaveText('35mm · f/2.8 · 1/250s · ISO 100');
  await expect(page.locator('#quietype_photo_image_url')).toHaveValue('');
  await expect(page.locator('#quietype_photo_original_url')).toHaveValue('');
  await expect(page.locator('#quietype_photo_width')).toHaveValue('');

  await page.locator('#quietype-photo-confirm').click();
  await expect(page.locator('#quietype_photo_image_url')).toHaveValue('https://pic.taifua.com/photos/changsha.jpg');
  await expect(page.locator('#quietype_photo_width')).toHaveValue('6000');
  await expect(page.locator('#quietype_photo_captured_date')).toHaveValue('2026-07');
  await expect(page.locator('#quietype_photo_aperture')).toHaveValue('f/2.8');
  await expect(page.locator('#quietype_photo_camera')).toHaveValue('FUJIFILM X-T5');
  await expect(page.locator('#quietype-photo-lookup-status')).toContainText('保存后生效');
});

test('photo list exposes only useful operational metadata', async ({ page }) => {
  test.skip(page.viewportSize().width < 700, 'The administration table is viewport-independent.');
  await logIn(page);
  await page.goto('/wp-admin/edit.php?post_type=photo', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('th#quietype_photo_record')).toHaveText('拍摄记录');
  await expect(page.locator('th#date')).toHaveCount(0);
  const photo = page.locator('#the-list tr').filter({ hasText: '雨后屋檐' });
  await expect(photo.locator('.column-quietype_photo_record')).toContainText('2026.07 · 安徽 · 宏村');
  await expect(photo.locator('.column-quietype_photo_record')).toContainText('1800×1200');
  const archiveLink = photo.locator('.row-actions .view a');
  await expect(archiveLink).toHaveText('在图库中查看');
  await expect(archiveLink).toHaveAttribute('href', /\/photos\/?#photo-[0-9]+$/);
  await photo.locator('.row-title').click();
  await expect(page.locator('#sample-permalink')).toHaveCount(0);
  await expect(page.locator('#slugdiv')).toHaveCount(0);
});

test('photo thumbnail convention is configurable without per-photo fields', async ({ page }) => {
  test.skip(page.viewportSize().width < 700, 'The settings screen is viewport-independent.');
  await logIn(page);
  await page.goto('/wp-admin/themes.php?page=quietype-settings', { waitUntil: 'domcontentloaded' });

  await expect(page.locator('#quietype_photo_thumbnail_base_url')).toHaveValue('https://images.example.test/photos');
  await expect(page.locator('#quietype_photo_thumbnail_base_url + .description')).toContainText('thumbs/年份/同名文件.webp');
});
