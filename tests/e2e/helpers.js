import { expect } from '@playwright/test';

export const ORIGIN = new URL(process.env.CHAMILO_URL || 'http://localhost:8080').origin;
export const COURSE = process.env.E2E_COURSE_ID || '3';
export const ctx = `cid=${COURSE}&sid=0`;
export const plugin = (script, extra = '') => `/plugin/ExeLearning/${script}?${ctx}${extra}`;

/** Log in through Chamilo's JSON login; the page context keeps the cookie. */
export async function login(page, user) {
  await page.context().clearCookies();
  const response = await page.request.post('/login_json', {
    data: { username: user, password: user },
    headers: { Origin: ORIGIN },
  });
  expect(response.ok(), `login as ${user}`).toBeTruthy();
}

/** The content URL of the package iframe on the viewer page. */
export async function contentUrl(page, id) {
  await page.goto(plugin('view.php', `&id=${id}`));
  return page.locator('iframe.exelearning-frame').getAttribute('src');
}
