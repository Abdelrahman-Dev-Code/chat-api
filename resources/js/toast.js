import { t } from './i18n';

const ICONS = {
    success: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8.5 12 2.5 2.5 4.5-5"/></svg>',
    error: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5M12 16h.01"/></svg>',
    info: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>',
};

const CLOSE_ICON = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>';

const region = () => document.getElementById('toast-region');

export const announce = (message) => {
    const live = document.getElementById('live-status');

    if (!live) {
        return;
    }

    live.textContent = '';
    window.setTimeout(() => {
        live.textContent = message;
    }, 30);
};

export const toast = (message, type = 'info', { duration = 4200 } = {}) => {
    const container = region();

    if (!container) {
        return;
    }

    while (container.children.length >= 3) {
        container.firstElementChild?.remove();
    }

    const element = document.createElement('div');
    element.className = `toast toast--${type}`;
    element.setAttribute('role', type === 'error' ? 'alert' : 'status');

    const icon = document.createElement('span');
    icon.className = 'toast__icon';
    icon.innerHTML = ICONS[type] ?? ICONS.info;

    const text = document.createElement('span');
    text.className = 'toast__message';
    text.textContent = message;

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'toast__close';
    close.setAttribute('aria-label', t('common.dismiss'));
    close.innerHTML = CLOSE_ICON;

    element.append(icon, text, close);
    container.append(element);

    let timer;

    const remove = () => {
        window.clearTimeout(timer);

        if (!element.isConnected) {
            return;
        }

        element.classList.add('is-leaving');
        window.setTimeout(() => element.remove(), 170);
    };

    close.addEventListener('click', remove);
    element.addEventListener('mouseenter', () => window.clearTimeout(timer));
    element.addEventListener('mouseleave', () => {
        timer = window.setTimeout(remove, 1600);
    });

    timer = window.setTimeout(remove, duration);
};
