import { createTranslator, escapeMessage } from './message-format.mjs';

export { createTranslator } from './message-format.mjs';

// PHP initializes this before page modules execute. Poll previews get their own
// translator, so changing a poll's language never changes the interface language.
export const pageTranslator = createTranslator(window.I18N);
export const uiTranslator = createTranslator(window.UI_I18N || window.I18N);
export const t = pageTranslator.t;
export const tHtml = pageTranslator.html;
export const tUi = uiTranslator.t;
export const getLocale = () => pageTranslator.locale;

export function tFallback(key, fallback, params = {}) {
    return pageTranslator.has(key) ? t(key, params) : String(fallback);
}

export function tFallbackHtml(key, fallback, params = {}) {
    return escapeMessage(tFallback(key, fallback, params));
}

export function formatDate(date, options = {}) {
    return new Intl.DateTimeFormat(getLocale(), {
        year: 'numeric', month: 'long', day: 'numeric', ...options,
    }).format(date instanceof Date ? date : new Date(date));
}

export function formatNumber(number, options = {}) {
    return new Intl.NumberFormat(getLocale(), options).format(number);
}
