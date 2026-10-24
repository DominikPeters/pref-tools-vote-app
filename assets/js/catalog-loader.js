import { createTranslator } from './message-format.mjs';
import languages from '../../src/i18n/languages.json';

// Vite gives each lazily loaded catalog its own hashed URL.
const catalogs = import.meta.glob('../../src/i18n/locales/*.json', { import: 'default' });

export const isSupportedLocale = locale => Object.hasOwn(languages, locale);

export async function loadTranslator(locale) {
    const load = catalogs[`../../src/i18n/locales/${locale}.json`];
    if (!load || !Object.hasOwn(languages, locale)) throw new Error('Unsupported poll language');
    const [messages, fallback] = await Promise.all([load(), locale === 'en' ? {} : catalogs['../../src/i18n/locales/en.json']()]);
    return createTranslator({ locale, messages, fallback, direction: languages[locale].direction });
}
