import { announce } from './toast';
import { t } from './i18n';
import { initTheme } from './theme';

initTheme();

/*
 * Google's consent screen is a full page navigation. Lock the link while it is
 * happening so a double-click cannot start two OAuth flows.
 */
document.addEventListener('click', (event) => {
    const link = event.target.closest('[data-oauth-link]');

    if (!link) {
        return;
    }

    if (link.dataset.loading === 'true') {
        event.preventDefault();

        return;
    }

    link.dataset.loading = 'true';
    link.classList.add('is-loading');
    link.setAttribute('aria-busy', 'true');

    const label = link.querySelector('[data-oauth-label]');
    const loadingLabel = link.querySelector('[data-oauth-loading-label]');

    if (label) {
        label.hidden = true;
    }

    if (loadingLabel) {
        loadingLabel.hidden = false;
    }

    const status = document.querySelector('[data-oauth-status]');
    const statusText = document.querySelector('[data-oauth-status-text]');
    const delay = document.querySelector('[data-oauth-delay]');

    if (status) {
        status.hidden = false;
    }

    if (statusText) {
        statusText.textContent = t('js.oauth_completing');
    }

    if (delay) {
        delay.hidden = false;
    }

    announce(t('js.oauth_announce'));
});

const resetOAuthLink = () => {
    document.querySelectorAll('[data-oauth-link]').forEach((link) => {
        delete link.dataset.loading;
        link.classList.remove('is-loading');
        link.removeAttribute('aria-busy');

        const label = link.querySelector('[data-oauth-label]');
        const loadingLabel = link.querySelector('[data-oauth-loading-label]');

        if (label) {
            label.hidden = false;
            label.textContent = t('js.oauth_reset');
        }

        if (loadingLabel) {
            loadingLabel.hidden = true;
        }
    });

    const status = document.querySelector('[data-oauth-status]');
    const delay = document.querySelector('[data-oauth-delay]');

    if (status) {
        status.hidden = true;
    }

    if (delay) {
        delay.hidden = true;
    }
};

window.addEventListener('pageshow', resetOAuthLink);

let localeSwitchTimeout;

const resetLocaleSwitchers = () => {
    window.clearTimeout(localeSwitchTimeout);

    const currentUrl = new URL(window.location.href);

    if (currentUrl.searchParams.has('_locale_refresh')) {
        currentUrl.searchParams.delete('_locale_refresh');
        window.history.replaceState(window.history.state, '', currentUrl);
    }

    document.querySelectorAll('[data-locale-switch]').forEach((form) => {
        delete form.dataset.switching;

        const button = form.querySelector('button[type="submit"]');

        if (button) {
            button.disabled = false;
            button.removeAttribute('aria-busy');
        }
    });
};

document.addEventListener('livewire:navigated', resetLocaleSwitchers);

document.addEventListener('submit', async (event) => {
    const form = event.target.closest('[data-locale-switch]');

    if (!form || !window.Livewire?.navigate) {
        return;
    }

    event.preventDefault();

    const button = form.querySelector('button[type="submit"]');

    if (!button || form.dataset.switching === 'true') {
        return;
    }

    form.dataset.switching = 'true';
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const result = await response.json();

        if (!response.ok || typeof result.redirect !== 'string') {
            throw new Error(`Locale switch failed with HTTP ${response.status}`);
        }

        const destination = new URL(result.redirect, window.location.origin);
        destination.searchParams.set('_locale_refresh', Date.now().toString());
        localeSwitchTimeout = window.setTimeout(resetLocaleSwitchers, 5000);
        window.Livewire.navigate(destination.toString());
    } catch (error) {
        resetLocaleSwitchers();
        toast(t('js.locale_switch_failed'));
        console.error(error);
    }
});
