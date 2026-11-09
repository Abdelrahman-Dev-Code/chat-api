/*
 * Workspace navigation: desktop collapse + mobile slide-over drawer.
 *
 * The drawer is a real focus trap: while open the rest of the workspace is
 * inert, Tab cycles inside the sidebar, Escape closes it and focus returns to
 * the trigger. No presence or room state is invented here.
 */

import { t } from './i18n';

const MOBILE_QUERY = '(max-width: 900px)';
const STORAGE_KEY = 'chat-sidebar-collapsed';
const mobile = window.matchMedia(MOBILE_QUERY);

let booted = false;
let previousFocus = null;

const root = () => document.getElementById('workspace');
const sidebar = () => document.getElementById('workspace-sidebar');
const content = () => document.querySelector('.workspace__content');

const isMobile = () => mobile.matches;

const sidebarTriggers = () => {
    const current = root();

    return current ? [...current.querySelectorAll('[data-sidebar-open]')] : [];
};

const readCollapsed = () => {
    try {
        return localStorage.getItem(STORAGE_KEY) === 'true';
    } catch (error) {
        return false;
    }
};

const writeCollapsed = (collapsed) => {
    try {
        localStorage.setItem(STORAGE_KEY, collapsed ? 'true' : 'false');
    } catch (error) {
        /* Storage can be unavailable in private browsing; ignore. */
    }
};

const setCollapsed = (collapsed) => {
    const current = root();

    if (!current) {
        return;
    }

    current.classList.toggle('is-sidebar-collapsed', collapsed);

    const button = current.querySelector('[data-sidebar-collapse]');

    if (!button) {
        return;
    }

    const next = collapsed ? t('sidebar.expand') : t('sidebar.collapse');

    button.setAttribute('aria-expanded', String(!collapsed));
    button.setAttribute('aria-label', next);
    button.title = next;
};

const drawerFocusables = () => {
    const panel = sidebar();

    if (!panel) {
        return [];
    }

    return [...panel.querySelectorAll(
        'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
    )].filter((element) => element.offsetParent !== null);
};

const openDrawer = () => {
    const current = root();

    if (!current || !isMobile() || current.classList.contains('is-drawer-open')) {
        return;
    }

    previousFocus = document.activeElement;
    current.classList.add('is-drawer-open');
    document.body.classList.add('has-drawer');

    sidebarTriggers().forEach((trigger) => trigger.setAttribute('aria-expanded', 'true'));

    const main = content();

    if (main) {
        main.inert = true;
    }

    window.requestAnimationFrame(() => {
        drawerFocusables()[0]?.focus();
    });
};

const closeDrawer = ({ restoreFocus = true } = {}) => {
    const current = root();

    if (!current) {
        return;
    }

    const wasOpen = current.classList.contains('is-drawer-open');

    current.classList.remove('is-drawer-open');
    document.body.classList.remove('has-drawer');

    sidebarTriggers().forEach((trigger) => trigger.setAttribute('aria-expanded', 'false'));

    const main = content();

    if (main) {
        main.inert = false;
    }

    if (wasOpen && restoreFocus && previousFocus instanceof HTMLElement) {
        previousFocus.focus({ preventScroll: true });
    }

    previousFocus = null;
};

const applyStoredState = () => {
    if (isMobile()) {
        closeDrawer({ restoreFocus: false });

        return;
    }

    setCollapsed(readCollapsed());
};

const trapFocus = (event) => {
    const current = root();

    if (!current || !current.classList.contains('is-drawer-open')) {
        return;
    }

    if (event.key === 'Escape') {
        event.preventDefault();
        closeDrawer();

        return;
    }

    if (event.key !== 'Tab') {
        return;
    }

    const focusables = drawerFocusables();

    if (focusables.length === 0) {
        event.preventDefault();

        return;
    }

    const first = focusables[0];
    const last = focusables[focusables.length - 1];
    const active = document.activeElement;

    if (event.shiftKey && (active === first || !sidebar()?.contains(active))) {
        event.preventDefault();
        last.focus();

        return;
    }

    if (!event.shiftKey && active === last) {
        event.preventDefault();
        first.focus();
    }
};

const handleClick = (event) => {
    if (event.target.closest('[data-sidebar-open]')) {
        openDrawer();

        return;
    }

    if (event.target.closest('[data-sidebar-close]')) {
        closeDrawer();

        return;
    }

    const collapse = event.target.closest('[data-sidebar-collapse]');

    if (collapse) {
        const current = root();

        if (!current) {
            return;
        }

        const next = !current.classList.contains('is-sidebar-collapsed');

        setCollapsed(next);
        writeCollapsed(next);

        return;
    }

    // A member row filters the transcript and closes the mobile drawer.
    if (isMobile() && event.target.closest('.member')) {
        closeDrawer({ restoreFocus: false });
    }
};

export const bootWorkspace = () => {
    if (booted) {
        applyStoredState();

        return;
    }

    booted = true;

    document.addEventListener('click', handleClick);
    document.addEventListener('keydown', trapFocus);
    mobile.addEventListener('change', applyStoredState);
    document.addEventListener('livewire:navigated', applyStoredState);

    applyStoredState();
};
