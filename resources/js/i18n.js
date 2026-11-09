/*
 * Tiny translation helper for the browser bundle.
 *
 * The server injects the complete `lang/{locale}/chat.php` catalogue through
 * `window.chatUi`, so the JavaScript never duplicates UI copy and always
 * follows the same locale as the rendered page.
 */

const catalogue = () => window.chatUi ?? {};

export const t = (key, replacements = {}) => {
    const value = key
        .split('.')
        .reduce((carry, part) => (carry && typeof carry === 'object' ? carry[part] : undefined), catalogue());

    let text = typeof value === 'string' ? value : key;

    Object.entries(replacements).forEach(([name, replacement]) => {
        text = text.split(`:${name}`).join(String(replacement));
    });

    return text;
};

export const isRtl = () => (window.chatDirection ?? document.documentElement.dir) === 'rtl';
