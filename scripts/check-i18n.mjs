import { readFileSync, readdirSync, existsSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { parse, TYPE } from '@formatjs/icu-messageformat-parser';

const errors = [];
function readJson(path) {
    try {
        const source = readFileSync(path, 'utf8');
        const value = JSON.parse(source);
        if (value === null || typeof value !== 'object' || Array.isArray(value)) throw new Error('expected a JSON object');
        // A duplicate key cannot survive this round trip. Keep catalogs formatted
        // consistently so silently overwritten messages fail CI as well.
        if (JSON.stringify(value, null, 2) + '\n' !== source) errors.push(`${path}: use two-space JSON formatting; duplicate keys are not allowed`);
        return value;
    } catch (error) {
        errors.push(`${path}: ${error.message}`);
        return {};
    }
}

const languages = readJson('src/i18n/languages.json');
const catalogs = Object.fromEntries(Object.keys(languages).map(code => [code, readJson(`src/i18n/locales/${code}.json`)]));
const english = catalogs.en || {};
if (!Object.keys(english).length) errors.push('Register a non-empty English source catalog');
const argumentsByKey = {};
function argumentsIn(ast, result = new Set()) {
    for (const item of ast) {
        if ([TYPE.argument, TYPE.number, TYPE.date, TYPE.time, TYPE.select, TYPE.plural].includes(item.type)) result.add(item.value);
        if (item.options) for (const option of Object.values(item.options)) argumentsIn(option.value, result);
    }
    return [...result].sort();
}

for (const [code, catalog] of Object.entries(catalogs)) {
    const metadata = languages[code];
    if (!/^[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/.test(code) || typeof metadata?.name !== 'string' || !metadata.name.trim() || !['ltr', 'rtl'].includes(metadata?.direction)) errors.push(`${code}: invalid language metadata`);
    for (const [key, message] of Object.entries(catalog)) {
        if (!/^[a-z][a-z0-9_]*$/.test(key) || typeof message !== 'string' || message.trim() === '') {
            errors.push(`${code}.${key}: expected a non-empty text message`);
            continue;
        }
        if (!Object.hasOwn(english, key)) errors.push(`${code}.${key}: key is absent from the English source catalog`);
        if (/<\/?[a-z][^>]*>/i.test(message)) errors.push(`${code}.${key}: keep HTML out of messages`);
        if (/:[a-zA-Z_][a-zA-Z_0-9]*/.test(message)) errors.push(`${code}.${key}: use ICU arguments/plurals instead of legacy syntax`);
        try {
            const args = argumentsIn(parse(message, { ignoreTag: true }));
            if (code === 'en') argumentsByKey[key] = args;
        } catch (error) {
            errors.push(`${code}.${key}: invalid ICU message: ${error.message}`);
        }
    }
}
// Metadata order is presentation order, not a promise that English comes first.
for (const [key, message] of Object.entries(english)) {
    try { argumentsByKey[key] = argumentsIn(parse(message, { ignoreTag: true })); } catch {}
}
for (const [code, catalog] of Object.entries(catalogs)) {
    if (code === 'en') continue;
    for (const [key, message] of Object.entries(catalog)) {
        try {
            if (JSON.stringify(argumentsIn(parse(message, { ignoreTag: true }))) !== JSON.stringify(argumentsByKey[key])) errors.push(`${code}.${key}: arguments differ from the English message`);
        } catch {}
    }
}
for (const file of readdirSync('src/i18n/locales')) {
    if (!file.endsWith('.json') || !Object.hasOwn(languages, file.slice(0, -5))) errors.push(`${file}: catalog has no language registration`);
}
for (const [key, context] of Object.entries(readJson('src/i18n/message-contexts.json'))) {
    if (!Object.hasOwn(english, key) || typeof context !== 'string' || !context.trim()) errors.push(`${key}: invalid translation context`);
}

function walk(path) {
    return readdirSync(path, { withFileTypes: true }).flatMap(entry => {
        const child = join(path, entry.name);
        return entry.isDirectory() ? walk(child) : [child];
    });
}
for (const path of [...walk('src'), ...walk('templates'), ...walk('assets/js')]) {
    if (!/\.(php|m?js)$/.test(path) || path.endsWith('marked.esm.js')) continue;
    const source = readFileSync(path, 'utf8');
    const patterns = path.endsWith('.php') ? [
        /(?:__|\$this->t|\$this->errorResult|\$emailT|self::text)\(\s*['"]([a-z][a-z0-9_]*)['"]\s*[,)]/g,
        /Translator::translateForLocale\([^,]+,\s*['"]([a-z][a-z0-9_]*)['"]\s*[,)]/g,
    ] : [/\b(?:t|tHtml|tUi|tUiHtml)\(\s*['"]([a-z][a-z0-9_]*)['"]\s*[,)]/g];
    for (const pattern of patterns) for (const match of source.matchAll(pattern)) {
        if (!Object.hasOwn(english, match[1])) errors.push(`${path}: unknown message key ${match[1]}`);
    }
}

const missing = Object.fromEntries(Object.entries(catalogs).filter(([code]) => code !== 'en').map(([code, catalog]) => [code, Object.keys(english).filter(key => !Object.hasOwn(catalog, key)).sort()]));
const baselinePath = 'src/i18n/coverage-baseline.json';
if (process.argv.includes('--write-baseline') && errors.length === 0) {
    writeFileSync(baselinePath, JSON.stringify(missing, null, 2) + '\n');
} else if (!existsSync(baselinePath)) {
    errors.push('Missing coverage baseline: review gaps and run npm run i18n:baseline');
} else {
    const baseline = readJson(baselinePath);
    for (const [code, keys] of Object.entries(baseline)) {
        if (!Object.hasOwn(missing, code) || !Array.isArray(keys) || keys.some(key => typeof key !== 'string')) errors.push(`${code}: invalid translation coverage baseline`);
    }
    for (const [code, keys] of Object.entries(missing)) {
        const previous = Array.isArray(baseline[code]) ? baseline[code] : [];
        for (const key of keys) if (!previous.includes(key)) errors.push(`${code}.${key}: new untranslated message (review before updating the baseline)`);
        for (const key of previous) if (!keys.includes(key)) errors.push(`${code}.${key}: remove the resolved gap from the coverage baseline`);
    }
}
for (const [code, catalog] of Object.entries(catalogs)) console.log(`${code}: ${Object.keys(catalog).length}/${Object.keys(english).length} source messages translated`);
if (errors.length) {
    console.error(errors.join('\n'));
    process.exitCode = 1;
}
