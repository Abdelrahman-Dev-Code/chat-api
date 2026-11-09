import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

import { bootChat } from './chat';
import { setupConnection } from './connection';
import { bootWorkspace } from './workspace';

window.Pusher = Pusher;

/*
 * Chat-only bundle: Echo, Pusher and the workspace behaviour never load on the
 * login screen. The server injects window.chatConfig only on the chat page,
 * keeping the compiled bundle environment-agnostic and secret-free.
 */
const chatConfig = window.chatConfig;

if (chatConfig?.key) {
    try {
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: chatConfig.key,
            wsHost: chatConfig.host,
            wsPort: chatConfig.port,
            wssPort: chatConfig.port,
            forceTLS: chatConfig.scheme === 'https',
            enabledTransports: ['ws', 'wss'],
            authEndpoint: '/broadcasting/auth',
            auth: {
                headers: {
                    'X-CSRF-TOKEN': chatConfig.csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        });

        setupConnection(window.Echo);
    } catch (error) {
        console.error('Unable to initialise real-time updates.', error);
    }
}

document.addEventListener('livewire:init', () => {
    bootWorkspace();
    bootChat();
});
