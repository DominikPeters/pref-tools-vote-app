import { createTranslator } from './message-format.mjs';
import languages from '../../src/i18n/languages.json';

// Vite gives each lazily loaded catalog its own hashed URL.
const catalogs = import.meta.glob('../../src/i18n/locales/*.json', { import: 'default' });

export const isSupportedLocale = locale => Object.hasOwn(languages, locale);

export async function loadTranslator(locale) {
    const load = catalogs[`../../src/i18n/locales/${locale}.json`];
    if (!load || !Object.hasOwn(languages, locale)) throw new Error('Unsupported poll language');
    const messages = await load();
    let fallback = {};
    // CI checks that locale keys are a subset of English; equal counts prove completeness.
    const sourceMessageCount = window.I18N?.sourceMessageCount;
    if (locale !== 'en' && Object.keys(messages).length !== sourceMessageCount) {
        const english = await catalogs['../../src/i18n/locales/en.json']();
        fallback = Object.fromEntries(
            Object.entries(english).filter(([key]) => !Object.hasOwn(messages, key))
        );
    }
    return createTranslator({ locale, messages, fallback, direction: languages[locale].direction });
}
