import { IntlMessageFormat } from 'intl-messageformat';

const formatters = new Map();

export function formatMessage(pattern, params = {}, locale = 'en') {
    const cacheKey = `${locale}\0${pattern}`;
    if (!formatters.has(cacheKey)) {
        if (formatters.size >= 1024) formatters.clear();
        // Messages are plain text, never HTML or React-style rich text.
        formatters.set(cacheKey, new IntlMessageFormat(pattern, locale, undefined, { ignoreTag: true }));
    }
    const values = {};
    for (const [name, value] of Object.entries(params)) {
        if (value !== null && !['string', 'number', 'boolean'].includes(typeof value)) {
            throw new TypeError('Message parameters must be scalar values.');
        }
        values[name] = value === null ? '' : typeof value === 'boolean' ? String(value) : value;
    }
    return String(formatters.get(cacheKey).format(values));
}

export function escapeMessage(text) {
    return String(text).replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]);
}

export function createTranslator({ locale = 'en', messages = {}, fallback = {}, direction = 'ltr' } = {}) {
    const has = key => Object.hasOwn(messages, key) || Object.hasOwn(fallback, key);
    const t = (key, params = {}) => {
        if (Object.hasOwn(messages, key)) return formatMessage(messages[key], params, locale);
        if (Object.hasOwn(fallback, key)) return formatMessage(fallback[key], params, 'en');
        return key;
    };
    return { locale, direction, has, t, html: (key, params = {}) => escapeMessage(t(key, params)) };
}
