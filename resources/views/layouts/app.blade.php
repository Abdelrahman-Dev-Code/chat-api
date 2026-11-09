<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#f5f6fa">

    <title>{{ $title ?? config('app.name', 'Chat') }}</title>

    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><rect width=%22100%22 height=%22100%22 rx=%2224%22 fill=%22%235b5bd6%22/><path d=%22M50 23c-16 0-29 11.7-29 26.1 0 5.2 1.7 10 4.7 14L21 77l15.2-4.4A31 31 0 0 0 50 75c16 0 29-11.7 29-25.9S66 23 50 23Z%22 fill=%22white%22/></svg>">

    {{-- Apply the saved theme before first paint to avoid a flash. --}}
    <script>
        (function () {
            try {
                var saved = localStorage.getItem('chat-theme');
                var theme = saved === 'light' || saved === 'dark'
                    ? saved
                    : (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                document.documentElement.dataset.theme = theme;
                document.documentElement.style.colorScheme = theme;
            } catch (error) {
                document.documentElement.dataset.theme = 'light';
            }
        })();
    </script>

    {{-- Server-provided UI strings keep the JavaScript bundle localized. --}}
    <script>
        window.chatUi = @json(__('chat'));
        window.chatLocale = @json(app()->getLocale());
        window.chatDirection = @json(app()->getLocale() === 'ar' ? 'rtl' : 'ltr');
    </script>

    @vite('resources/css/app.css')
    @livewireStyles

    @if (auth()->check() && request()->routeIs('chat'))
        @php
            $chatConfig = [
                'key' => config('broadcasting.connections.reverb.key'),
                'host' => config('reverb.client.host'),
                'port' => config('reverb.client.port'),
                'scheme' => config('reverb.client.scheme'),
                'csrfToken' => csrf_token(),
            ];
        @endphp
        <script>
            window.chatConfig = @json($chatConfig);
        </script>
    @endif
</head>
<body class="{{ request()->routeIs('chat') ? 'is-chat' : '' }}">
    <a class="skip-link" href="#main-content">{{ __('chat.common.skip') }}</a>

    <div class="app">
        @unless (request()->routeIs('chat'))
        <header class="topbar">
            <a class="brand" href="{{ auth()->check() ? route('chat') : route('login') }}" wire:navigate aria-label="{{ __('chat.common.home', ['name' => config('app.name', 'Chat')]) }}">
                <span class="brand__mark" aria-hidden="true">
                    <x-icon name="chat" :size="19" />
                </span>
                <span class="brand__name">{{ config('app.name', 'Chat') }}</span>
            </a>

            <div class="topbar__actions">
                <x-language-switcher compact />
                <x-theme-toggle />

                @auth
                    <div
                        class="account"
                        x-data="{ open: false }"
                        @click.outside="open = false"
                        @keydown.escape.stop="open = false"
                    >
                        <button
                            type="button"
                            class="account__trigger"
                            x-bind:aria-expanded="open"
                            aria-controls="account-menu"
                            @click="open = ! open; if (open) $nextTick(() => $refs.menu.focus())"
                        >
                            <x-avatar :user="auth()->user()" size="small" />
                            <span class="account__meta">
                                <span class="account__name">{{ auth()->user()->name }}</span>
                                <span class="account__status">{{ __('chat.common.signed_in') }}</span>
                            </span>
                            <span class="account__chevron" aria-hidden="true">
                                <x-icon name="chevron-down" :size="16" />
                            </span>
                        </button>

                        <div
                            id="account-menu"
                            class="account__menu"
                            x-ref="menu"
                            x-show="open"
                            x-cloak
                            x-transition.origin.top.right
                            tabindex="-1"
                        >
                            <div class="account__menu-head">
                                <x-avatar :user="auth()->user()" size="medium" />
                                <div class="account__menu-identity">
                                    <span class="account__menu-name">{{ auth()->user()->name }}</span>
                                    <span class="account__menu-email">{{ auth()->user()->email }}</span>
                                </div>
                            </div>

                            <p class="account__menu-label">{{ __('chat.sidebar.signed_in_google') }}</p>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="account__action">
                                    <x-icon name="logout" :size="17" />
                                    <span>{{ __('chat.common.sign_out') }}</span>
                                </button>
                            </form>
                        </div>
                    </div>
                @else
                    @unless (request()->routeIs('login'))
                        <a href="{{ route('login') }}" class="button button--ghost button--small" wire:navigate>
                            {{ __('chat.common.sign_in') }}
                        </a>
                    @endunless
                @endauth
            </div>
        </header>
        @endunless

        <main class="main" id="main-content" tabindex="-1">
            {{ $slot }}
        </main>
    </div>

    <div id="toast-region" class="toast-region" role="region" aria-label="{{ __('chat.common.notifications') }}"></div>
    <div id="live-status" class="sr-only" role="status" aria-live="polite" aria-atomic="true"></div>

    @vite('resources/js/app.js')

    @if (request()->routeIs('chat'))
        @vite('resources/js/chat-app.js')
    @endif

    @livewireScripts
</body>
</html>
