import { t } from './i18n';

const STORAGE_KEY = 'chat-theme';
const root = document.documentElement;
const media = window.matchMedia('(prefers-color-scheme: dark)');

const storedTheme = () => {
    try {
        const value = localStorage.getItem(STORAGE_KEY);

        return value === 'light' || value === 'dark' ? value : null;
    } catch (error) {
        return null;
    }
};

const applyTheme = (theme) => {
    root.dataset.theme = theme;
    root.style.colorScheme = theme;

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        const next = theme === 'dark' ? 'light' : 'dark';
        const label = t(next === 'dark' ? 'common.theme_dark' : 'common.theme_light');

        button.setAttribute('aria-pressed', String(theme === 'dark'));
        button.setAttribute('aria-label', label);
        button.title = label;
    });

    const themeColor = document.querySelector('meta[name="theme-color"]');

    if (themeColor) {
        themeColor.setAttribute('content', theme === 'dark' ? '#0a0d16' : '#f4f5fa');
    }
};

export const initTheme = () => {
    applyTheme(storedTheme() ?? (media.matches ? 'dark' : 'light'));

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-theme-toggle]');

        if (!button) {
            return;
        }

        const next = root.dataset.theme === 'dark' ? 'light' : 'dark';

        applyTheme(next);

        try {
            localStorage.setItem(STORAGE_KEY, next);
        } catch (error) {
            // A blocked storage area should never block theme switching.
        }
    });

    media.addEventListener('change', (event) => {
        if (storedTheme() === null) {
            applyTheme(event.matches ? 'dark' : 'light');
        }
    });

    window.addEventListener('storage', (event) => {
        if (event.key === STORAGE_KEY && (event.newValue === 'light' || event.newValue === 'dark')) {
            applyTheme(event.newValue);
        }
    });
};
