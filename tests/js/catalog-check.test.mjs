import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawnSync } from 'node:child_process';

const script = new URL('../../scripts/check-i18n.mjs', import.meta.url);
function check(change) {
    const root = mkdtempSync(join(tmpdir(), 'vote-i18n-'));
    const write = (name, value) => writeFileSync(join(root, name), JSON.stringify(value, null, 2) + '\n');
    for (const dir of ['src/i18n/locales', 'templates', 'assets/js']) mkdirSync(join(root, dir), { recursive: true });
    write('src/i18n/languages.json', { en: { name: 'English', direction: 'ltr' }, fr: { name: 'Français', direction: 'ltr' } });
    write('src/i18n/locales/en.json', { greeting: 'Hello {name}' });
    write('src/i18n/locales/fr.json', { greeting: 'Bonjour {name}' });
    write('src/i18n/coverage-baseline.json', { fr: [] });
    write('src/i18n/message-contexts.json', { greeting: 'Greeting to the named user.' });
    try {
        change?.(write, root);
        return spawnSync(process.execPath, [script.pathname], { cwd: root, encoding: 'utf8' });
    } finally {
        rmSync(root, { recursive: true });
    }
}

test('valid catalogs pass', () => assert.equal(check().status, 0));
test('placeholder mismatches fail CI', () => {
    const result = check(write => write('src/i18n/locales/fr.json', { greeting: 'Bonjour {person}' }));
    assert.equal(result.status, 1);
    assert.match(result.stderr, /arguments differ/);
});
test('invalid ICU syntax fails CI', () => {
    const result = check(write => write('src/i18n/locales/fr.json', { greeting: 'Bonjour {name' }));
    assert.equal(result.status, 1);
    assert.match(result.stderr, /invalid ICU/);
});
test('new translation gaps fail CI', () => {
    const result = check(write => write('src/i18n/locales/fr.json', {}));
    assert.equal(result.status, 1);
    assert.match(result.stderr, /new untranslated/);
});
test('HTML in catalogs fails CI', () => {
    const result = check(write => write('src/i18n/locales/fr.json', { greeting: '<b>{name}</b>' }));
    assert.equal(result.status, 1);
    assert.match(result.stderr, /keep HTML out/);
});
test('duplicate JSON keys fail CI', () => {
    const result = check((write, root) => writeFileSync(join(root, 'src/i18n/locales/fr.json'), '{"greeting":"first","greeting":"second"}\n'));
    assert.equal(result.status, 1);
    assert.match(result.stderr, /duplicate keys/);
});
test('invalid catalog structures fail with a useful diagnostic', () => {
    const result = check(write => write('src/i18n/locales/fr.json', null));
    assert.equal(result.status, 1);
    assert.match(result.stderr, /expected a JSON object/);
});
test('language registrations require a native name and direction', () => {
    const result = check(write => write('src/i18n/languages.json', { en: { name: 'English', direction: 'ltr' }, fr: null }));
    assert.equal(result.status, 1);
    assert.match(result.stderr, /invalid language metadata/);
});
test('translation contexts cannot refer to nonexistent messages', () => {
    const result = check(write => write('src/i18n/message-contexts.json', { unknown: 'Wrong key' }));
    assert.equal(result.status, 1);
    assert.match(result.stderr, /invalid translation context/);
});
test('escaped UI HTML helpers and explicit PHP contexts check their source keys', () => {
    const result = check((write, root) => {
        writeFileSync(join(root, 'assets/js/example.js'), "tUiHtml('unknown_client');");
        writeFileSync(join(root, 'src/example.php'), "<?php $emailT('unknown_email'); $this->t('unknown_error'); Translator::translateForLocale($locale, 'unknown_locale');");
    });
    assert.equal(result.status, 1);
    for (const key of ['unknown_client', 'unknown_email', 'unknown_error', 'unknown_locale']) assert.match(result.stderr, new RegExp(`unknown message key ${key}`));
});
test('literal separators are plain text, while legacy argument syntax fails', () => {
    assert.equal(check(write => {
        write('src/i18n/locales/en.json', { greeting: 'Hello {name} | Welcome' });
        write('src/i18n/locales/fr.json', { greeting: 'Bonjour {name} | Bienvenue' });
    }).status, 0);
    assert.match(check(write => write('src/i18n/locales/fr.json', { greeting: 'Bonjour :name' })).stderr, /legacy syntax/);
});
