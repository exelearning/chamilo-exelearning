import { test, expect } from '@playwright/test';
import { login, plugin, contentUrl } from './helpers.js';
import path from 'node:path';

const SAMPLE = path.resolve('tests/fixtures/sample.elpx');
const TITLE = `E2E package ${Date.now()}`;

test.describe.serial('package lifecycle', () => {
  let id;
  let firstContent;

  test('teacher uploads a package', async ({ page }) => {
    await login(page, 'teacher');
    await page.goto(plugin('start.php'));
    const form = page.getByTestId('exelearning-upload-form');
    await form.locator('input[type=file]').setInputFiles(SAMPLE);
    await form.locator('input[name=title]').fill(TITLE);
    await form.locator('button[type=submit]').click();
    const row = page.locator('tr[data-package-id]', { hasText: TITLE });
    await expect(row).toBeVisible();
    id = await row.getAttribute('data-package-id');
  });

  test('student sees and views it, but cannot manage it', async ({ page }) => {
    await login(page, 'student');
    await page.goto(plugin('start.php'));
    await expect(page.getByTestId('exelearning-upload-form')).toHaveCount(0);
    await expect(page.locator(`tr[data-package-id="${id}"] .exelearning-actions`)).toHaveCount(0);
    await page.getByRole('link', { name: TITLE }).click();
    await expect(page.frameLocator('iframe.exelearning-frame').locator('body')).toContainText('Ejemplo');
    firstContent = await page.locator('iframe.exelearning-frame').getAttribute('src');

    const response = await page.request.get(firstContent);
    expect(response.status()).toBe(200);
    // The web server may add its own nosniff on top of the plugin's.
    expect(response.headers()['x-content-type-options']).toContain('nosniff');
    expect(response.headers()['content-security-policy']).toContain("frame-ancestors 'self'");
  });

  test('teacher edits in the embedded editor and saves; the content changes', async ({ page }) => {
    await login(page, 'teacher');
    const before = await contentUrl(page, id);
    await page.goto(plugin('edit.php', `&id=${id}`));
    const root = page.locator('[data-exelearning-editor]');
    await expect(root).toHaveAttribute('data-loaded', 'true', { timeout: 120_000 });

    const title = page.frameLocator('iframe.exelearning-editor-frame').getByRole('textbox').first();
    await title.fill('Edited by E2E');
    await title.press('Tab');
    await expect(root).toHaveAttribute('data-dirty', 'true');

    const saved = page.waitForResponse((r) => r.url().includes('/save.php'));
    await page.locator('[data-exelearning-save]').click();
    const response = await saved;
    expect(response.status(), await response.text()).toBe(200);
    await expect(root).toHaveAttribute('data-dirty', 'false');

    const after = await contentUrl(page, id);
    expect(after).not.toBe(before);
    await expect(page.frameLocator('iframe.exelearning-frame').locator('body')).toContainText('Edited by E2E');
    // The previous version is gone from storage.
    expect((await page.request.get(before)).status()).toBe(404);
  });

  test('teacher deletes it; the stored content is gone', async ({ page }) => {
    await login(page, 'teacher');
    const url = await contentUrl(page, id);
    await page.goto(plugin('start.php'));
    page.once('dialog', (dialog) => dialog.accept());
    const row = page.locator(`tr[data-package-id="${id}"]`);
    await row.locator('form:has(input[name=action][value=delete]) button').click();
    await expect(row).toHaveCount(0);
    expect((await page.request.get(url)).status()).toBe(404);
  });
});
