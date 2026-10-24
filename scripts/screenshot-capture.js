/** Deterministic homepage assets, captured from a disposable loopback-only app. */
const { chromium } = require('@playwright/test');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const crypto = require('node:crypto');
const { spawn, execFileSync } = require('node:child_process');
const examples = require('./screenshot-examples.json');

const ROOT = path.resolve(__dirname, '..');
const QUESTION_STEMS = [
  'question_single_choice', 'question_approval', 'question_ranking',
  'question_ranking_truncated', 'question_ranking_with_ties', 'question_star',
  'question_grade', 'question_grade_2', 'question_yes_no_abstain',
];
const IMAGE_STEMS = [...QUESTION_STEMS, 'full_poll_container', 'full_results_container'];
const THEMES = ['light', 'dark'];
const digest = value => crypto.createHash('sha256').update(value).digest('hex');

function validateTarget(value) {
  const url = new URL(value);
  if (url.protocol !== 'http:' || !['127.0.0.1', 'localhost', '[::1]'].includes(url.hostname)
      || !url.port || Number(url.port) < 1024 || url.username || url.password
      || url.pathname !== '/' || url.search || url.hash) {
    throw new Error('Screenshot capture requires an explicit high-port loopback HTTP target.');
  }
  return url.origin;
}

function selectedLocales(requested) {
  const languages = JSON.parse(fs.readFileSync(path.join(ROOT, 'src/i18n/languages.json'), 'utf8'));
  const locales = requested ? requested.split(',') : Object.keys(languages);
  const sourceKeys = Object.keys(JSON.parse(fs.readFileSync(path.join(ROOT, 'src/i18n/locales/en.json'), 'utf8')));
  for (const locale of locales) {
    if (!Object.hasOwn(languages, locale)) throw new Error(`Unregistered screenshot locale: ${locale}`);
    if (!Array.isArray(examples[locale]) || examples[locale].length !== 19
        || examples[locale].some(value => typeof value !== 'string' || !value.trim())) {
      throw new Error(`Complete screenshot example translations are required for ${locale}.`);
    }
    const catalog = path.join(ROOT, `src/i18n/locales/${locale}.json`);
    if (!fs.existsSync(catalog)) throw new Error(`Screenshot catalog missing: ${locale}`);
    const messages = JSON.parse(fs.readFileSync(catalog, 'utf8'));
    if (Object.keys(messages).length !== sourceKeys.length || sourceKeys.some(key => !Object.hasOwn(messages, key))) {
      throw new Error(`Complete UI translations are required before capturing ${locale}.`);
    }
  }
  return locales;
}

async function examplePoll(locale) {
  const { createTranslator } = await import('../assets/js/message-format.mjs');
  const messages = JSON.parse(fs.readFileSync(path.join(ROOT, `src/i18n/locales/${locale}.json`), 'utf8'));
  const translator = createTranslator({ locale, messages });
  const text = examples[locale];
  const places = text.slice(11, 15).map(label => ({ label }));
  const activities = text.slice(15, 19).map(label => ({ label }));
  // Custom grades keep the same localized scale in both ballots and results.
  const grades = ['excellent', 'very_good', 'good', 'fair', 'poor', 'reject']
    .map(grade => translator.t(`grade_${grade}`).toLocaleLowerCase(locale));
  const definitions = [
    ['single_choice', places], ['approval', activities, { min: 1, max: 3 }],
    ['ranking', places], ['ranking_truncated', activities, { min: 1, max: 3 }],
    ['ranking_with_ties', activities], ['star', places.slice(0, 3), { starCount: 5 }],
    ['grade', activities.slice(0, 2), { preset: 'custom', grades }],
    ['grade', activities.slice(2, 4), { preset: 'custom', grades: ['++', '+', '0', '−', '−−'] }],
    ['yes_no_abstain', activities.slice(2, 4), { allowAbstain: true }],
  ];
  return {
    title: text[0], description: text[1], locale, status: 'open', visibility: 'full',
    voting_mode: 'open', randomize_options: false, allow_edit_own: true,
    questions: definitions.map(([type, options, settings], index) => ({
      type, options, settings: settings || null, text: text[index + 2], required: false, sort_order: index + 1,
    })),
  };
}

function responseAnswers(questions, voter) {
  const answers = {};
  for (const question of questions) {
    const ids = question.options.map(option => option.id);
    const order = [[0, 1, 2, 3], [0, 2, 1, 3], [1, 0, 3, 2], [2, 0, 1, 3]][voter];
    const ranked = order.filter(index => index < ids.length).map(index => ids[index]);
    switch (question.type) {
      case 'single_choice': answers[question.id] = ids[[0, 0, 1, 2][voter]]; break;
      case 'approval': answers[question.id] = ranked.slice(0, 2); break;
      case 'ranking': answers[question.id] = ranked; break;
      case 'ranking_truncated': answers[question.id] = ranked.slice(0, 3); break;
      case 'ranking_with_ties': answers[question.id] = Object.fromEntries(ranked.map((id, i) => [id, i < 2 ? 1 : i])); break;
      case 'star': answers[question.id] = Object.fromEntries(ids.map((id, i) => [id, 3 + ((voter + i) % 3)])); break;
      case 'grade': answers[question.id] = Object.fromEntries(ids.map((id, i) => [id, question.settings.grades[(voter + i) % 3]])); break;
      case 'yes_no_abstain': answers[question.id] = Object.fromEntries(ids.map((id, i) => [id, ['yes', 'no', 'abstain'][(voter + i) % 3]])); break;
      default: throw new Error(`Unsupported screenshot question: ${question.type}`);
    }
  }
  return answers;
}

function createFixture(baseURL, password) {
  const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'vote-screenshots-'));
  try {
    fs.cpSync(path.join(ROOT, 'src'), path.join(directory, 'src'), { recursive: true });
    fs.cpSync(path.join(ROOT, 'templates'), path.join(directory, 'templates'), { recursive: true });
    fs.copyFileSync(path.join(ROOT, 'index.php'), path.join(directory, 'index.php'));
    for (const name of ['assets', 'lib', 'migrations', 'screenshots']) {
      fs.symlinkSync(path.join(ROOT, name), path.join(directory, name), 'dir');
    }
    fs.mkdirSync(path.join(directory, 'config'));
    fs.mkdirSync(path.join(directory, 'data'));
    fs.mkdirSync(path.join(directory, 'capture-resources'));
    fs.copyFileSync(path.join(__dirname, 'resources/Sortable.min.js'), path.join(directory, 'capture-resources/Sortable.min.js'));
    const layoutPath = path.join(directory, 'templates/layout.php');
    const layout = fs.readFileSync(layoutPath, 'utf8');
    const remoteSortable = 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js';
    if (!layout.includes(remoteSortable)) throw new Error('The production Sortable dependency changed; update the screenshot fixture deliberately.');
    fs.writeFileSync(layoutPath, layout.replace(remoteSortable, '/capture-resources/Sortable.min.js'));
    const phpLiteral = value => "'" + value.replaceAll('\\', '\\\\').replaceAll("'", "\\'") + "'";
    fs.writeFileSync(path.join(directory, 'config/config.php'), `<?php return [
      'database' => ['driver' => 'sqlite', 'sqlite_path' => __DIR__ . '/../data/capture.db', 'auto_migrate' => false],
      'app' => ['name' => 'Pref.Tools Vote', 'url' => ${phpLiteral(baseURL)}, 'debug' => false, 'timezone' => 'UTC'],
      'session' => ['name' => 'screenshot_session', 'lifetime' => 3600],
      'mail' => ['enabled' => false],
    ];`);
    const marker = crypto.randomUUID();
    fs.writeFileSync(path.join(directory, 'capture-fixture.json'), JSON.stringify({ marker }));
    fs.writeFileSync(path.join(directory, 'initialize.php'), `<?php
      require __DIR__ . '/src/bootstrap.php';
      (new App\\Services\\MigrationService())->runPending();
      App\\Auth::getInstance()->register('capture@example.invalid', $argv[1], 'Capture User', 'sysadmin');
      App\\Models\\SiteSetting::setMany(['mail.enabled' => '0', 'moderation.enabled' => '0', 'site.name' => 'Pref.Tools Vote']);
    `);
    fs.writeFileSync(path.join(directory, 'normalize-date.php'), `<?php
      require __DIR__ . '/src/bootstrap.php';
      App\\Database::getInstance()->update('polls', ['created_at' => '2026-01-15 12:00:00'], 'public_id = :id', ['id' => $argv[1]]);
    `);
    execFileSync(process.env.PHP_BIN || 'php', [path.join(directory, 'initialize.php'), password], { stdio: 'pipe', env: { ...process.env, APP_ENV: 'screenshot' } });
    return { directory, marker };
  } catch (error) {
    fs.rmSync(directory, { recursive: true, force: true });
    throw error;
  }
}

async function startFixture(fixture, baseURL) {
  const parsed = new URL(baseURL);
  const server = spawn(process.env.PHP_BIN || 'php', ['-S', `127.0.0.1:${parsed.port}`, '-t', fixture.directory], {
    cwd: fixture.directory, env: { ...process.env, APP_ENV: 'screenshot' }, stdio: ['ignore', 'pipe', 'pipe'],
  });
  let output = '';
  server.stdout.on('data', chunk => { output += chunk; });
  server.stderr.on('data', chunk => { output += chunk; });
  try {
    for (let attempt = 0; attempt < 100; attempt++) {
      if (server.exitCode !== null) throw new Error(`Isolated screenshot server exited: ${output}`);
      const marker = await fetch(`${baseURL}/capture-fixture.json`).then(response => response.json()).catch(() => null);
      if (marker?.marker === fixture.marker) return server;
      if (marker !== null) throw new Error('Capture port belongs to another server; refusing to use it.');
      await new Promise(resolve => setTimeout(resolve, 100));
    }
    throw new Error(`Could not start the isolated screenshot server: ${output}`);
  } catch (error) { server.kill(); throw error; }
}

async function session(context, baseURL) {
  const response = await context.request.get(`${baseURL}/login`);
  const token = (await response.text()).match(/name="csrf-token"\s+content="([^"]+)"/)?.[1];
  if (!response.ok() || !token) throw new Error('Could not initialize the isolated screenshot session.');
  return { 'X-CSRF-TOKEN': token };
}

async function requestJson(responsePromise) {
  const response = await responsePromise;
  const data = await response.json();
  if (!response.ok()) throw new Error(`Capture API ${response.status()}: ${data.error || JSON.stringify(data)}`);
  return data;
}

async function writeImage(locator, locale, theme, stem, fixture, encoder) {
  const png = await locator.screenshot({ animations: 'disabled', caret: 'hide', type: 'png' });
  const temporaryPng = path.join(fixture.directory, 'capture.png');
  const temporaryWebp = path.join(fixture.directory, 'capture.webp');
  fs.writeFileSync(temporaryPng, png);
  execFileSync(encoder, ['-quiet', '-q', '82', '-m', '4', temporaryPng, '-o', temporaryWebp], { stdio: 'pipe' });
  const webp = fs.readFileSync(temporaryWebp);
  if (webp.toString('ascii', 0, 4) !== 'RIFF' || webp.toString('ascii', 8, 12) !== 'WEBP') throw new Error('Encoder did not produce WebP.');
  const fingerprint = digest(webp);
  const relative = `screenshots/localized/${locale}/${stem}-${theme}-${fingerprint.slice(0, 12)}.webp`;
  const target = path.join(ROOT, relative);
  fs.mkdirSync(path.dirname(target), { recursive: true });
  if (!fs.existsSync(target)) fs.writeFileSync(target, webp, { flag: 'wx' });
  return { path: relative, width: png.readUInt32BE(16), height: png.readUInt32BE(20), fingerprint };
}

async function settlePage(page, locale) {
  await page.evaluate(() => document.fonts.ready);
  const actual = await page.locator('html').getAttribute('lang');
  if (actual !== locale) throw new Error(`Screenshot locale mismatch: expected ${locale}, rendered ${actual}`);
  await page.addStyleTag({ content: `
    *, *::before, *::after { animation: none !important; transition: none !important; caret-color: transparent !important; }
    .site-header, .site-footer, .breadcrumbs, .poll-footer, .results-footer, .editing-banner { display: none !important; }
    body, .main-content { background: transparent !important; margin: 0 !important; padding: 0 !important; }
    .poll-container, .results-container { max-width: 800px !important; margin: 0 auto !important; padding: 0 !important; }
    .results-container > .container { padding: 20px !important; }
  ` });
  const text = await page.locator('main').innerText();
  if (/\b(?:page|js|error|validation)_[a-z][a-z0-9_]*\b/.test(text)) throw new Error(`Untranslated message key in ${locale} screenshot.`);
}

async function captureLocale(browser, fixture, baseURL, locale, kind, encoder, manifest) {
  const owner = await browser.newContext({ baseURL, locale, viewport: { width: 800, height: 1000 }, deviceScaleFactor: 1.5 });
  let poll;
  try {
    const headers = await session(owner, baseURL);
    await requestJson(owner.request.post('/api/auth/login', { headers, data: { email: 'capture@example.invalid', password: fixture.password } }));
    ({ poll } = await requestJson(owner.request.post('/api/polls', { headers, data: await examplePoll(locale) })));
    execFileSync(process.env.PHP_BIN || 'php', [path.join(fixture.directory, 'normalize-date.php'), poll.public_id], { stdio: 'pipe', env: { ...process.env, APP_ENV: 'screenshot' } });
    let firstVoterToken;
    for (let voter = 0; voter < 4; voter++) {
      const voterContext = await browser.newContext({ baseURL, locale });
      try {
        const voterHeaders = await session(voterContext, baseURL);
        const result = await requestJson(voterContext.request.post(`/api/polls/${poll.public_id}/responses`, {
          headers: voterHeaders, data: { answers: responseAnswers(poll.questions, voter) },
        }));
        if (voter === 0) firstVoterToken = result.voter_token;
      } finally { await voterContext.close(); }
    }
    const reportEndpoint = `/api/polls/${poll.public_id}/admin/${poll.admin_token}/reports`;
    const initialReports = await requestJson(owner.request.get(reportEndpoint));
    for (const report of initialReports.reports) await requestJson(owner.request.delete(`${reportEndpoint}/${report.id}`, { headers }));
    const reportTypes = ['choice_counts', 'choice_counts', 'borda_scores', 'voting_rule_winner', 'condorcet_winner', 'score_distribution', 'majority_judgment', 'score_distribution', 'yna_counts'];
    for (let index = 0; index < reportTypes.length; index++) {
      await requestJson(owner.request.post(reportEndpoint, { headers, data: {
        question_id: poll.questions[index].id, report_type: reportTypes[index], is_public: true,
        config: index === 3 ? { rule: 'ranked_pairs' } : null,
      } }));
    }
    const entries = manifest.locales[locale] ||= {};
    for (const theme of THEMES) {
      const context = await browser.newContext({ baseURL, locale, colorScheme: theme, reducedMotion: 'reduce', viewport: { width: 800, height: 1000 }, deviceScaleFactor: 1.5 });
      const failures = [];
      try {
        await context.route('**/*', async route => {
          const url = new URL(route.request().url());
          if (url.origin !== baseURL) { failures.push(`External request blocked: ${url.origin}`); await route.abort(); }
          else await route.continue();
        });
        await context.addCookies([
          { name: 'ui_locale', value: locale, url: baseURL },
          { name: `voter_token_${poll.public_id}`, value: firstVoterToken, url: baseURL },
        ]);
        const page = await context.newPage();
        page.on('pageerror', error => failures.push(error.message));
        if (kind !== 'results') {
          await page.goto(`/${poll.public_id}`);
          await page.locator('.question-display').last().waitFor();
          await settlePage(page, locale);
          const questions = page.locator('.question-display');
          if (await questions.count() !== QUESTION_STEMS.length) throw new Error('Unexpected screenshot question count.');
          for (let index = 0; index < QUESTION_STEMS.length; index++) {
            const stem = QUESTION_STEMS[index];
            (entries[stem] ||= {})[theme] = await writeImage(questions.nth(index), locale, theme, stem, fixture, encoder);
          }
          (entries.full_poll_container ||= {})[theme] = await writeImage(page.locator('.poll-container'), locale, theme, 'full_poll_container', fixture, encoder);
        }
        if (kind !== 'questions') {
          await page.goto(`/${poll.public_id}/results`);
          await page.waitForFunction(expected => document.querySelectorAll('.report-card').length === expected
            && !document.querySelector('.report-loading, .loading, .report-error'), reportTypes.length);
          await settlePage(page, locale);
          (entries.full_results_container ||= {})[theme] = await writeImage(page.locator('.results-container'), locale, theme, 'full_results_container', fixture, encoder);
        }
        if (failures.length) throw new Error(`${locale}/${theme}: ${failures.join('; ')}`);
      } finally { await context.close(); }
    }
  } finally {
    if (poll) {
      const cleanupHeaders = await session(owner, baseURL);
      await requestJson(owner.request.delete(`/api/polls/${poll.public_id}/admin/${poll.admin_token}`, { headers: cleanupHeaders }));
    }
    await owner.close();
  }
}

async function capture(options = {}) {
  const locales = selectedLocales(options.locale);
  const kind = options.kind || 'all';
  if (!['all', 'questions', 'results'].includes(kind)) throw new Error('Use --kind=all, questions or results.');
  const baseURL = validateTarget(`http://127.0.0.1:${options.port || 18082}`);
  const encoder = process.env.CWEBP_BIN || 'cwebp';
  execFileSync(encoder, ['-version'], { stdio: 'pipe' });
  const manifestFile = path.join(ROOT, 'screenshots/manifest.json');
  const manifest = fs.existsSync(manifestFile) ? JSON.parse(fs.readFileSync(manifestFile, 'utf8')) : { version: 1, locales: {} };
  if (manifest.version !== 1) throw new Error('Unsupported screenshot manifest version.');
  manifest.generatorFingerprint = digest(fs.readFileSync(__filename));
  const password = crypto.randomUUID();
  const fixture = { ...createFixture(baseURL, password), password };
  let server;
  let browser;
  try {
    server = await startFixture(fixture, baseURL);
    browser = await chromium.launch();
    for (const locale of locales) {
      console.log(`Capturing ${locale}: ${kind}, light + dark`);
      await captureLocale(browser, fixture, baseURL, locale, kind, encoder, manifest);
      const temporaryManifest = manifestFile + '.tmp';
      fs.writeFileSync(temporaryManifest, JSON.stringify(manifest, null, 2) + '\n');
      fs.renameSync(temporaryManifest, manifestFile);
    }
    console.log(`Captured ${locales.length} locale(s); manifest: screenshots/manifest.json`);
  } finally {
    if (browser) await browser.close();
    if (server) {
      server.kill();
      await new Promise(resolve => server.exitCode !== null ? resolve() : server.once('exit', resolve));
    }
    fs.rmSync(fixture.directory, { recursive: true, force: true });
  }
}

function cliOptions(argv) {
  const options = {};
  for (const arg of argv) {
    const match = arg.match(/^--(locale|kind|port)=(.+)$/);
    if (!match) throw new Error(`Unknown capture argument: ${arg}`);
    options[match[1]] = match[2];
  }
  return options;
}

module.exports = { capture, cliOptions, validateTarget, selectedLocales, examplePoll, responseAnswers, QUESTION_STEMS, IMAGE_STEMS, THEMES };
