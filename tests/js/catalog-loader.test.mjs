import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const loaderSource = readFileSync(new URL('../../assets/js/catalog-loader.js', import.meta.url), 'utf8');
const messageFormatUrl = new URL('../../assets/js/message-format.mjs', import.meta.url).href;
let importId = 0;

// Exercise the production loader with Vite's catalog imports replaced by fixtures.
async function loadFixture({ messages, english, sourceMessageCount }) {
    const catalogDefinitions = Object.entries({ fr: messages, en: english }).map(([locale, catalog]) =>
        `${JSON.stringify(`../../src/i18n/locales/${locale}.json`)}: async () => {
            catalogCalls.push(${JSON.stringify(locale)});
            return ${JSON.stringify(catalog)};
        }`
    ).join(',');
    const source = loaderSource
        .replace("import { createTranslator } from './message-format.mjs';", `
            import { createTranslator as makeTranslator } from ${JSON.stringify(messageFormatUrl)};
            export let configuration;
            const createTranslator = value => {
                configuration = value;
                return makeTranslator(value);
            };
            export const catalogCalls = [];
            const window = { I18N: ${JSON.stringify({ sourceMessageCount })} };
        `)
        .replace("import languages from '../../src/i18n/languages.json';", 'const languages = { en: { direction: "ltr" }, fr: { direction: "ltr" } };')
        .replace(/const catalogs = import\.meta\.glob\([^;]+;/, `const catalogs = { ${catalogDefinitions} };`);
    return import(`data:text/javascript,${encodeURIComponent(source)}#fixture-${importId++}`);
}

test('a complete preview catalog does not fetch English', async () => {
    const loader = await loadFixture({ messages: { word: 'Français' }, english: { word: 'English' }, sourceMessageCount: 1 });
    const translator = await loader.loadTranslator('fr');
    assert.equal(translator.t('word'), 'Français');
    assert.equal(translator.direction, 'ltr');
    assert.deepEqual(loader.catalogCalls, ['fr']);
    assert.deepEqual(loader.configuration.fallback, {});
});

test('an incomplete preview catalog keeps only missing English messages', async () => {
    const loader = await loadFixture({
        messages: { word: 'Français' },
        english: { word: 'English', count: '{count, plural, one {# response} other {# responses}}' },
        sourceMessageCount: 2,
    });
    const translator = await loader.loadTranslator('fr');
    assert.equal(translator.t('word'), 'Français');
    assert.equal(translator.t('count', { count: 0 }), '0 responses');
    assert.deepEqual(loader.catalogCalls, ['fr', 'en']);
    assert.deepEqual(loader.configuration.fallback, { count: '{count, plural, one {# response} other {# responses}}' });
});

test('older page configuration without the source count still checks English', async () => {
    const loader = await loadFixture({ messages: { word: 'Français' }, english: { word: 'English' } });
    await loader.loadTranslator('fr');
    assert.deepEqual(loader.catalogCalls, ['fr', 'en']);
    assert.deepEqual(loader.configuration.fallback, {});
});

test('English loads once without a fallback and unsupported locales load nothing', async () => {
    const loader = await loadFixture({ messages: {}, english: { word: 'English' }, sourceMessageCount: 2 });
    const translator = await loader.loadTranslator('en');
    assert.equal(translator.t('word'), 'English');
    assert.deepEqual(loader.catalogCalls, ['en']);
    await assert.rejects(loader.loadTranslator('../../private'), /Unsupported poll language/);
    assert.deepEqual(loader.catalogCalls, ['en']);
});
