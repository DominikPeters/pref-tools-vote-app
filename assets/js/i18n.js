/**
 * Internationalization (i18n) helper for JavaScript.
 *
 * Translations are embedded in the page via window.TRANSLATIONS (set by PHP).
 * The locale is available via window.LOCALE.
 *
 * Usage:
 *   import { t, tFallback } from './i18n.js';
 *
 *   // Standard translation (returns key if not found)
 *   t('submit_vote')                    // => "Submit Vote"
 *   t('responses_count', { count: 5 })  // => "5 responses"
 *
 *   // With explicit fallback (for technical terms like voting rules)
 *   tFallback('rule_schulze', 'Schulze')  // => Uses fallback if no translation
 *
 * Plurals:
 *   Use '|' to separate singular and plural forms:
 *   'response|responses' with { count: 1 } => 'response'
 *   'response|responses' with { count: 2 } => 'responses'
 */

/**
 * Translate a key to its localized string.
 *
 * @param {string} key - The translation key
 * @param {Object} [params={}] - Parameters to interpolate (e.g., { count: 5 })
 * @returns {string} The translated string, or the key if not found
 */
export function t(key, params = {}) {
    const translations = window.TRANSLATIONS || {};
    let text = translations[key];

    // If key not found, return the key itself as fallback
    if (text === undefined) {
        return key;
    }

    // Handle plurals: "singular|plural" syntax
    if (text.includes('|') && params.count !== undefined) {
        const [singular, plural] = text.split('|', 2);
        text = params.count === 1 ? singular : plural;
    }

    // Interpolate parameters: :name => value
    for (const [name, value] of Object.entries(params)) {
        text = text.replaceAll(`:${name}`, String(value));
    }

    return text;
}

/**
 * Translate a key with an explicit fallback value.
 *
 * Useful for technical terms (like voting rule names) where:
 * - Some terms should be translated (e.g., "Majority Judgment" → "Jugement Majoritaire")
 * - Others should stay in English (e.g., "Schulze", "Borda Count")
 *
 * Usage:
 *   tFallback('rule_schulze', 'Schulze')           // Uses English "Schulze" if no translation
 *   tFallback('rule_majority_judgment', 'Majority Judgment')  // Uses French if available
 *
 * @param {string} key - The translation key
 * @param {string} fallback - The fallback value if key is not found
 * @param {Object} [params={}] - Parameters to interpolate
 * @returns {string} The translated string, or the fallback
 */
export function tFallback(key, fallback, params = {}) {
    const translations = window.TRANSLATIONS || {};
    let text = translations[key];

    // If key not found, use the fallback
    if (text === undefined) {
        text = fallback;
    }

    // Handle plurals: "singular|plural" syntax
    if (text.includes('|') && params.count !== undefined) {
        const [singular, plural] = text.split('|', 2);
        text = params.count === 1 ? singular : plural;
    }

    // Interpolate parameters: :name => value
    for (const [name, value] of Object.entries(params)) {
        text = text.replaceAll(`:${name}`, String(value));
    }

    return text;
}

/**
 * Get the current locale code.
 *
 * @returns {string} The locale code (e.g., 'en', 'fr')
 */
export function getLocale() {
    return window.LOCALE || 'en';
}

/**
 * Format a date according to the current locale.
 *
 * @param {Date|string|number} date - The date to format
 * @param {Object} [options] - Intl.DateTimeFormat options
 * @returns {string} The formatted date
 */
export function formatDate(date, options = {}) {
    const locale = getLocale();
    const dateObj = date instanceof Date ? date : new Date(date);

    const defaultOptions = {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    };

    return dateObj.toLocaleDateString(locale, { ...defaultOptions, ...options });
}

/**
 * Format a number according to the current locale.
 *
 * @param {number} number - The number to format
 * @param {Object} [options] - Intl.NumberFormat options
 * @returns {string} The formatted number
 */
export function formatNumber(number, options = {}) {
    const locale = getLocale();
    return new Intl.NumberFormat(locale, options).format(number);
}
