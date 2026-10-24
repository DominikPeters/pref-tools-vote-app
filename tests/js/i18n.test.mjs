import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { formatMessage, createTranslator } from '../../assets/js/message-format.mjs';

const cases = JSON.parse(readFileSync(new URL('../fixtures/i18n-messages.json', import.meta.url)));
for (const sample of cases) {
    test(sample.name, () => assert.equal(formatMessage(sample.pattern, sample.params, sample.locale), sample.expected));
}

test('English fallback messages keep English plural rules', () => {
    const french = createTranslator({
        locale: 'fr', messages: { local: '{count, plural, one {# vote} other {# votes}}' },
        fallback: { fallback: '{count, plural, one {# response} other {# responses}}' },
    });
    assert.equal(french.t('local', { count: 0 }), '0 vote');
    assert.equal(french.t('fallback', { count: 0 }), '0 responses');
    assert.equal(french.t('unknown'), 'unknown');
});

test('plain text and escaped HTML messages have explicit separate contracts', () => {
    const messages = createTranslator({ messages: { greeting: 'Hello {name}, {name}' } });
    const name = '<img src=x onerror=alert(1)>';
    assert.equal(messages.t('greeting', { name }), `Hello ${name}, ${name}`);
    assert.equal(messages.html('greeting', { name }), 'Hello &lt;img src=x onerror=alert(1)&gt;, &lt;img src=x onerror=alert(1)&gt;');
    assert.equal(messages.html('greeting', { name: '"Ada\'s"' }), 'Hello &quot;Ada&#39;s&quot;, &quot;Ada&#39;s&quot;');
});

test('translator instances remain independent', () => {
    const english = createTranslator({ locale: 'en', messages: { word: 'English' } });
    const french = createTranslator({ locale: 'fr', messages: { word: 'Français' } });
    assert.equal(french.t('word'), 'Français');
    assert.equal(english.locale, 'en');
    assert.equal(english.t('word'), 'English');
});

test('message values cannot introduce rich-text objects', () => {
    assert.throws(() => formatMessage('Hello {name}', { name: ['unexpected'] }), /scalar/);
});
