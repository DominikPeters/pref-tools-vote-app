const { test, expect } = require('./fixtures');

test.describe('Internationalization infrastructure', () => {
  test.use({ locale: 'fr-CH' });

  test('negotiates a language, persists an explicit preference and protects the change with CSRF', async ({ freshPage }) => {
    await freshPage.goto('/create');
    await expect(freshPage.locator('html')).toHaveAttribute('lang', 'fr');
    await expect(freshPage.locator('#interfaceLocale')).toHaveValue('fr');
    await expect(freshPage.locator('#pollLocale')).toHaveValue('fr');

    await freshPage.selectOption('#interfaceLocale', 'en');
    await Promise.all([
      freshPage.waitForResponse(r => r.url().endsWith('/language') && r.request().method() === 'POST'),
      freshPage.click('.language-picker button[type="submit"]'),
    ]);
    await expect(freshPage.locator('html')).toHaveAttribute('lang', 'en');
    await freshPage.goto('/login');
    await expect(freshPage.locator('#interfaceLocale')).toHaveValue('en');
    const cookie = (await freshPage.context().cookies()).find(c => c.name === 'ui_locale');
    expect(cookie.value).toBe('en');
    expect(cookie.httpOnly).toBe(true);
    expect(cookie.sameSite).toBe('Lax');

    const denied = await freshPage.request.post('/language', { form: { locale: 'de', return_to: '/create' } });
    expect(denied.status()).toBe(403);
    expect((await freshPage.context().cookies()).find(c => c.name === 'ui_locale').value).toBe('en');
  });

  test('changing the poll preview language does not change the builder interface', async ({ freshPage }) => {
    await freshPage.goto('/create');
    const uiLabel = await freshPage.locator('.type-btn[data-type="single_choice"]').textContent();
    await freshPage.click('#addQuestionBtn');
    await freshPage.click('.type-btn[data-type="text_single"]');
    await freshPage.locator('.question-title-input').fill('A short answer');
    await freshPage.click('h1');
    const preview = freshPage.locator('.question-display');
    await expect(preview.locator('input')).toHaveAttribute('placeholder', 'Réponse courte');
    await freshPage.selectOption('#pollLocale', 'de');
    await expect(preview).toHaveAttribute('lang', 'de');
    await expect(preview.locator('input')).toHaveAttribute('placeholder', 'Kurzantwort');
    await expect(freshPage.locator('html')).toHaveAttribute('lang', 'fr');
    await expect(freshPage.locator('#interfaceLocale')).toHaveValue('fr');
    await expect(freshPage.locator('.type-btn[data-type="single_choice"]')).toHaveText(uiLabel);
    expect(await freshPage.evaluate(() => window.I18N.locale)).toBe('fr');
  });

  test('voter language and thank-you text remain independent of the interface preference', async ({ freshPage, api }) => {
    const created = await api.post('/api/polls', { data: {
      title: 'French voting flow', locale: 'fr', status: 'open',
      questions: [{ type: 'text_single', text: 'Votre avis ?' }],
    } });
    const { poll } = await created.json();
    await freshPage.context().addCookies([{ name: 'ui_locale', value: 'de', url: 'http://localhost:18080' }]);
    await freshPage.goto(`/${poll.public_id}`);
    await expect(freshPage.locator('html')).toHaveAttribute('lang', 'fr');
    await expect(freshPage.locator('.site-header')).toHaveAttribute('lang', 'de');
    await expect(freshPage.locator('#interfaceLocale')).toHaveValue('de');
    await freshPage.locator('#pollForm input[type="text"]').fill('Très bien');
    await freshPage.getByRole('button', { name: 'Soumettre le vote', exact: true }).click();
    await expect(freshPage.getByRole('heading', { name: 'Merci !', exact: true })).toBeVisible();
    await expect(freshPage.getByText('Votre vote a été enregistré.', { exact: true })).toBeVisible();
  });

  test('previously copied classic embed URLs load the current localized bundle', async ({ freshPage, api }) => {
    const created = await api.post('/api/polls', { data: {
      title: 'Legacy French embed', locale: 'fr', status: 'open',
      questions: [{ type: 'single_choice', text: 'Votre choix ?', options: [{ label: 'A' }, { label: 'B' }] }],
    } });
    const { poll } = await created.json();
    await api.put(`/api/polls/${poll.public_id}/admin/${poll.admin_token}`, { data: { allow_embedding: true } });
    const token = await api.post(`/api/polls/${poll.public_id}/admin/${poll.admin_token}/embed-token`);
    const { embed_url } = await token.json();
    await freshPage.goto('/privacy');
    await freshPage.addScriptTag({ url: '/assets/js/embed/vote-poll.js' });
    await freshPage.evaluate(src => {
      const poll = document.createElement('vote-poll');
      poll.setAttribute('src', src);
      document.body.appendChild(poll);
    }, embed_url);
    const component = freshPage.locator('vote-poll');
    await expect(component).toHaveAttribute('lang', 'fr');
    await component.getByRole('radio', { name: 'A', exact: true }).check();
    await component.getByRole('button', { name: 'Soumettre le vote', exact: true }).click();
    await expect(component.getByRole('heading', { name: 'Merci !', exact: true })).toBeVisible();
  });

  test('API errors are displayed as text rather than interpreted as markup', async ({ page }) => {
    await page.goto('/create');
    await page.route('**/api/polls', route => route.fulfill({
      status: 400, contentType: 'application/json',
      body: JSON.stringify({ error: '<img src=x onerror="window.toastInjected=true">', code: 'EXAMPLE_ERROR' }),
    }));
    await page.click('#saveBtn');
    await expect(page.locator('.toast-error')).toContainText('<img src=x');
    await expect(page.locator('.toast-error img')).toHaveCount(0);
    expect(await page.evaluate(() => window.toastInjected)).toBeUndefined();
  });
});
