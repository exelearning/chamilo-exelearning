import { test, expect } from '@playwright/test';
import { login, plugin, contentUrl, ORIGIN } from './helpers.js';

// The seed's sample package is #1 in the demo course.
const ID = '1';

test('a student cannot reach the editor, the download or the save endpoint', async ({ page }) => {
  await login(page, 'student');
  for (const script of ['edit.php', 'editor.php', 'download.php']) {
    const response = await page.request.get(plugin(script, `&id=${ID}`), { maxRedirects: 0 });
    expect(response.status(), script).not.toBe(200);
  }
  const save = await page.request.post(plugin('save.php'), { multipart: { id: ID }, headers: { Origin: ORIGIN } });
  expect(save.status()).toBe(403);
});

test('a student POST to manage packages is refused and changes nothing', async ({ page }) => {
  await login(page, 'student');
  const response = await page.request.post(plugin('start.php'), { form: { action: 'delete', id: ID }, maxRedirects: 0 });
  expect(response.status()).not.toBe(200);
  await login(page, 'teacher');
  await page.goto(plugin('start.php'));
  await expect(page.locator(`tr[data-package-id="${ID}"]`)).toHaveCount(1);
});

test('a user not enrolled in the course is denied, even with a valid content URL', async ({ page }) => {
  await login(page, 'teacher');
  const url = await contentUrl(page, ID);

  await login(page, 'outsider');
  const list = await page.request.get(plugin('start.php'), { maxRedirects: 0 });
  expect(list.status()).not.toBe(200);
  const view = await page.request.get(plugin('view.php', `&id=${ID}`), { maxRedirects: 0 });
  expect(view.status()).not.toBe(200);
  expect((await page.request.get(url)).status()).toBe(403);
});

test('content requests reject traversal and anonymous users', async ({ page, request }) => {
  await login(page, 'teacher');
  const url = await contentUrl(page, ID);
  const traversal = url.replace(/index\.html$/, '..%2F..%2Fx');
  expect((await page.request.get(traversal)).status()).toBe(404);
  expect((await request.get(url)).status()).toBe(403);
});
