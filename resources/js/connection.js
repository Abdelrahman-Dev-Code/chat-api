import { announce } from './toast';
import { t } from './i18n';

const root = document.documentElement;

const indicator = () => document.getElementById('connection-status');

const render = (state) => {
    root.dataset.connection = state;

    const element = indicator();

    if (!element) {
        return;
    }

    if (state === 'connected') {
        element.hidden = true;

        return;
    }

    const label = element.querySelector('[data-connection-label]');
    const retry = element.querySelector('[data-connection-retry]');
    const offline = state === 'offline';

    element.dataset.state = offline ? 'offline' : 'reconnecting';

    if (label) {
        label.textContent = offline ? t('connection.offline') : t('connection.reconnecting');
    }

    if (retry) {
        retry.hidden = offline || state === 'connecting';
    }

    element.hidden = false;
};

export const setupConnection = (echo) => {
    const pusher = echo?.connector?.pusher;

    if (!pusher) {
        return;
    }

    const connection = pusher.connection;
    let connectingTimer = null;
    let hasConnected = connection.state === 'connected';

    const showConnectingSoon = () => {
        window.clearTimeout(connectingTimer);
        connectingTimer = window.setTimeout(() => render('connecting'), 850);
    };

    const reconnect = () => {
        if (navigator.onLine === false) {
            render('offline');

            return;
        }

        showConnectingSoon();
        pusher.connect();
    };

    const handleState = (state) => {
        window.clearTimeout(connectingTimer);

        if (state === 'connected') {
            if (!hasConnected) {
                announce(t('connection.reconnected'));
            }

            hasConnected = true;
            render('connected');

            return;
        }

        hasConnected = false;

        if (state === 'connecting' || state === 'initialized') {
            showConnectingSoon();

            return;
        }

        render(state === 'unavailable' || state === 'failed' ? 'unavailable' : 'disconnected');
    };

    connection.bind('state_change', ({ current }) => handleState(current));

    if (connection.state === 'connected') {
        render('connected');
    } else {
        showConnectingSoon();
    }

    window.addEventListener('online', reconnect);

    window.addEventListener('offline', () => {
        window.clearTimeout(connectingTimer);
        render('offline');
    });

    // A mobile browser can suspend the socket while backgrounded. Reconnect on
    // return instead of leaving the room silently stale.
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState !== 'visible') {
            return;
        }

        if (navigator.onLine === false) {
            render('offline');

            return;
        }

        if (connection.state === 'disconnected' || connection.state === 'unavailable' || connection.state === 'failed') {
            reconnect();
        }
    });

    document.addEventListener('click', (event) => {
        const retry = event.target.closest('[data-connection-retry]');

        if (!retry) {
            return;
        }

        reconnect();
    });
};
