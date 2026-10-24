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

test.describe('Internationalization coverage with an English browser', () => {
  test.use({ locale: 'en-US' });

  test('public language URLs keep their language and expose reciprocal search metadata', async ({ freshPage, baseURL }) => {
    test.setTimeout(90000);
    const languages = require('../src/i18n/languages.json');
    await freshPage.context().addCookies([{ name: 'ui_locale', value: 'de', url: baseURL }]);

    for (const locale of Object.keys(languages)) {
      const response = await freshPage.goto(`/${locale}/`);
      expect(response.ok()).toBe(true);
      await expect(freshPage.locator('html')).toHaveAttribute('lang', locale);
      await expect(freshPage.locator('link[rel="canonical"]')).toHaveAttribute('href', `${baseURL}/${locale}/`);
      await expect(freshPage.locator('link[rel="alternate"][hreflang]')).toHaveCount(Object.keys(languages).length + 1);
      for (const alternate of Object.keys(languages)) {
        await expect(freshPage.locator(`link[rel="alternate"][hreflang="${alternate}"]`))
          .toHaveAttribute('href', `${baseURL}/${alternate}/`);
      }
      await expect(freshPage.locator('link[rel="alternate"][hreflang="x-default"]')).toHaveAttribute('href', `${baseURL}/`);
      await expect(freshPage.locator('.localized-screenshot')).toHaveCount(11);
      const imageLocales = await freshPage.locator('.localized-screenshot').evaluateAll(pictures => pictures.map(picture => picture.lang));
      expect(imageLocales).toEqual(Array(11).fill(locale));
    }

    await freshPage.goto('/hu/about');
    await expect(freshPage.locator('html')).toHaveAttribute('lang', 'hu');
    await expect(freshPage.locator('link[rel="canonical"]')).toHaveAttribute('href', `${baseURL}/hu/about`);
    await expect(freshPage.locator('link[rel="alternate"][hreflang="fr"]')).toHaveAttribute('href', `${baseURL}/fr/about`);
    await freshPage.locator('.logo').click();
    await expect(freshPage).toHaveURL(`${baseURL}/hu/`);
    await freshPage.locator('.hero-actions a[href$="/create"]').click();
    await expect(freshPage.locator('html')).toHaveAttribute('lang', 'hu');
    await expect(freshPage.locator('#pollLocale')).toHaveValue('hu');
  });

  test('localized screenshots and the lightbox follow live theme changes', async ({ freshPage }) => {
    await freshPage.emulateMedia({ colorScheme: 'dark' });
    await freshPage.goto('/hu/');
    const card = freshPage.locator('.gallery-item').first();
    const image = card.locator('img');
    await expect(image).toBeVisible();
    const darkUrl = await card.locator('source').getAttribute('srcset');
    const lightUrl = await image.getAttribute('src');
    expect(darkUrl).not.toBe(lightUrl);
    await expect.poll(() => image.evaluate(element => element.complete && element.naturalWidth > 0 && new URL(element.currentSrc).pathname))
      .toBe(darkUrl);
    await card.click();
    const expanded = freshPage.locator('#lightboxImage');
    await expect(expanded).toBeVisible();
    await expect.poll(() => expanded.evaluate(element => new URL(element.currentSrc).pathname)).toBe(darkUrl);

    await freshPage.emulateMedia({ colorScheme: 'light' });
    await expect.poll(() => expanded.evaluate(element => element.complete && element.naturalWidth > 0 && new URL(element.currentSrc).pathname))
      .toBe(lightUrl);
    await expect.poll(() => image.evaluate(element => new URL(element.currentSrc).pathname)).toBe(lightUrl);
  });

  test('switching language on a marketing page navigates to its equivalent language URL', async ({ freshPage, baseURL }) => {
    await freshPage.goto('/hu/about');
    await freshPage.selectOption('#interfaceLocale', 'fr');
    await Promise.all([
      freshPage.waitForResponse(response => response.url().endsWith('/language') && response.request().method() === 'POST'),
      freshPage.locator('.language-picker button[type="submit"]').click(),
    ]);
    await expect(freshPage).toHaveURL(`${baseURL}/fr/about`);
    await expect(freshPage.locator('html')).toHaveAttribute('lang', 'fr');
    await expect(freshPage.locator('#interfaceLocale')).toHaveValue('fr');
  });

  test('an isolated account keeps its saved interface language after its language cookie is cleared', async ({ userAccount }) => {
    const { page, context } = userAccount;
    expect(await page.evaluate(() => navigator.language)).toBe('en-US');
    await expect(page.locator('#interfaceLocale')).toHaveValue('en');

    await page.selectOption('#interfaceLocale', 'fr');
    await Promise.all([
      page.waitForResponse(response => response.url().endsWith('/language') && response.request().method() === 'POST'),
      page.click('.language-picker button[type="submit"]'),
    ]);
    await expect(page.locator('html')).toHaveAttribute('lang', 'fr');

    await context.clearCookies({ name: 'ui_locale' });
    expect((await context.cookies()).some(cookie => cookie.name === 'ui_locale')).toBe(false);
    await page.goto('/create');
    await expect(page.locator('html')).toHaveAttribute('lang', 'fr');
    await expect(page.locator('#interfaceLocale')).toHaveValue('fr');
    await expect(page.locator('#pollLocale')).toHaveValue('fr');
    expect(await page.evaluate(() => navigator.language)).toBe('en-US');
  });

  test('all registered languages render the creator, login and poll admin without leaked message keys', async ({ freshPage, api, baseURL }) => {
    test.setTimeout(90000);
    const languages = require('../src/i18n/languages.json');
    const pageErrors = [];
    freshPage.on('pageerror', error => pageErrors.push(error.message));

    const created = await api.post('/api/polls', { data: {
      title: 'Language coverage poll', locale: 'en', status: 'open',
      questions: [{ type: 'text_single', text: 'A participant-authored question' }],
    } });
    expect(created.ok()).toBe(true);
    const { poll } = await created.json();
    const adminPath = `/${poll.public_id}/admin/${poll.admin_token}`;
    const leakedMessageKey = /\b(?:page|js|error|validation)_[a-z][a-z0-9_]*\b/;

    for (const [locale, metadata] of Object.entries(languages)) {
      await freshPage.context().addCookies([{ name: 'ui_locale', value: locale, url: baseURL }]);
      for (const path of ['/create', '/login', adminPath]) {
        await test.step(`${locale}: ${path === adminPath ? 'poll admin' : path}`, async () => {
          const response = await freshPage.goto(path);
          expect(response.ok()).toBe(true);
          await expect(freshPage.locator('html')).toHaveAttribute('lang', locale);
          await expect(freshPage.locator('html')).toHaveAttribute('dir', metadata.direction);
          await expect(freshPage.locator('.site-header')).toHaveAttribute('lang', locale);
          await expect(freshPage.locator('#interfaceLocale')).toHaveValue(locale);
          await expect(freshPage.locator(`#interfaceLocale option[value="${locale}"]`)).toHaveText(metadata.name);
          if (path === '/create') await expect(freshPage.locator('#pollLocale')).toHaveValue(locale);
          if (path === adminPath) await expect(freshPage.locator('#responsesList .empty-message')).toBeVisible();
          if (path === '/login' && locale === 'fr') {
            await expect(freshPage.getByRole('heading', { name: 'Bon retour', exact: true })).toBeVisible();
          }
          if (path === '/login' && locale === 'en') {
            await expect(freshPage.getByRole('heading', { name: 'Welcome Back', exact: true })).toBeVisible();
          }
          const attributeText = await freshPage.locator('[placeholder], [title], [aria-label]').evaluateAll(elements =>
            elements.flatMap(element => ['placeholder', 'title', 'aria-label'].map(name => element.getAttribute(name) || '')).join('\n')
          );
          expect(await freshPage.locator('body').innerText()).not.toMatch(leakedMessageKey);
          expect(attributeText).not.toMatch(leakedMessageKey);
          expect(pageErrors, `JavaScript errors while rendering ${locale} ${path}`).toEqual([]);
        });
      }
    }
  });

  test('password errors, plural voting validation and API errors use the poll language independently of navigation', async ({ freshPage, api, baseURL }) => {
    const created = await api.post('/api/polls', { data: {
      title: 'French access and validation', locale: 'fr', status: 'open',
      access_mode: 'password', access_password: 'poll-language-password',
      questions: [
        { type: 'approval', text: 'Vos choix ?', required: false, settings: { min: 2 }, options: [{ label: 'Alpha' }, { label: 'Beta' }, { label: 'Gamma' }] },
        { type: 'approval', text: 'Un choix ?', required: false, settings: { min: 1 }, options: [{ label: 'Delta' }, { label: 'Epsilon' }] },
      ],
    } });
    expect(created.ok()).toBe(true);
    const { poll } = await created.json();
    await freshPage.context().addCookies([{ name: 'ui_locale', value: 'de', url: baseURL }]);
    await freshPage.goto(`/${poll.public_id}`);
    await expect(freshPage.locator('html')).toHaveAttribute('lang', 'fr');
    await expect(freshPage.locator('.site-header')).toHaveAttribute('lang', 'de');
    await expect(freshPage.locator('#interfaceLocale')).toHaveValue('de');
    await expect(freshPage.getByRole('heading', { name: 'Mot de passe requis', exact: true })).toBeVisible();

    await freshPage.fill('#access_password', 'incorrect-password');
    await freshPage.locator('.password-card button[type="submit"]').click();
    await expect(freshPage.locator('.password-card .error-message')).toHaveText('Mot de passe incorrect. Veuillez réessayer.');
    await expect(freshPage.locator('html')).toHaveAttribute('lang', 'fr');
    await expect(freshPage.locator('.site-header')).toHaveAttribute('lang', 'de');

    await freshPage.fill('#access_password', 'poll-language-password');
    await freshPage.locator('.password-card button[type="submit"]').click();
    const questions = freshPage.locator('.question-display[data-type="approval"]');
    await expect(questions).toHaveCount(2);
    await questions.nth(0).getByRole('checkbox', { name: 'Alpha', exact: true }).check();
    await freshPage.getByRole('button', { name: 'Soumettre le vote', exact: true }).click();
    await expect(freshPage.locator('.toast-error').last()).toHaveText('Veuillez sélectionner au moins 2 options pour « Vos choix ? »');
    await questions.nth(0).getByRole('checkbox', { name: 'Beta', exact: true }).check();
    await freshPage.getByRole('button', { name: 'Soumettre le vote', exact: true }).click();
    await expect(freshPage.locator('.toast-error').last()).toHaveText('Veuillez sélectionner au moins 1 option pour « Un choix ? »');

    const closed = await api.put(`/api/polls/${poll.public_id}/admin/${poll.admin_token}`, { data: { status: 'closed' } });
    expect(closed.ok()).toBe(true);
    const csrf = await freshPage.locator('meta[name="csrf-token"]').getAttribute('content');
    const denied = await freshPage.request.post(`/api/polls/${poll.public_id}/responses`, {
      headers: { 'X-CSRF-TOKEN': csrf }, data: { answers: {} },
    });
    expect(denied.status()).toBe(400);
    expect((await denied.json()).error).toBe('Ce vote n’est pas ouvert aux réponses');
    expect(await freshPage.evaluate(() => window.I18N.locale)).toBe('fr');
    expect(await freshPage.evaluate(() => window.UI_I18N.locale)).toBe('de');
  });
});
