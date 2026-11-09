<div class="auth">
    <section class="auth__card" aria-labelledby="login-heading">
        <div class="auth__art">
            <div class="auth__art-glow" aria-hidden="true"></div>

            <div class="auth__art-top">
                <p class="auth__badge">
                    <span class="auth__badge-dot" aria-hidden="true"></span>
                    {{ __('chat.login.badge') }}
                </p>
                <p class="auth__art-title">{{ __('chat.login.title_line_1') }}<br>{{ __('chat.login.title_line_2') }}</p>
                <p class="auth__art-copy">
                    {{ __('chat.login.copy') }}
                </p>
            </div>

            <div class="auth__mock" aria-hidden="true">
                <div class="auth__mock-bar">
                    <span class="auth__mock-people">
                        <span class="auth__mock-avatar auth__mock-avatar--one">S</span>
                        <span class="auth__mock-avatar auth__mock-avatar--two">M</span>
                        <span class="auth__mock-avatar auth__mock-avatar--three">A</span>
                    </span>
                    <span class="auth__mock-title">{{ __('chat.login.mock_channel') }}</span>
                    <span class="auth__mock-live"><span></span>{{ __('chat.login.mock_live') }}</span>
                </div>

                <div class="auth__mock-thread">
                    <div class="auth__mock-row">
                        <span class="auth__mock-avatar auth__mock-avatar--one">S</span>
                        <div class="auth__mock-bubble">
                            <span class="auth__mock-name">Sara</span>
                            {{ __('chat.login.mock_sara') }}
                        </div>
                    </div>
                    <div class="auth__mock-row auth__mock-row--own">
                        <div class="auth__mock-bubble auth__mock-bubble--own">
                            {{ __('chat.login.mock_you') }}
                        </div>
                    </div>
                    <div class="auth__mock-row">
                        <span class="auth__mock-avatar auth__mock-avatar--two">M</span>
                        <div class="auth__mock-bubble">
                            <span class="auth__mock-name">Maya</span>
                            {{ __('chat.login.mock_maya') }}
                        </div>
                    </div>
                </div>

                <div class="auth__mock-composer">
                    <span>{{ __('chat.login.mock_composer') }}</span>
                    <span class="auth__mock-send">
                        <x-icon name="send" :size="14" />
                    </span>
                </div>
            </div>

            <ul class="auth__proof">
                <li><x-icon name="bolt" :size="16" /> {{ __('chat.login.proof_instant') }}</li>
                <li><x-icon name="lock" :size="16" /> {{ __('chat.login.proof_private') }}</li>
                <li><x-icon name="shield" :size="16" /> {{ __('chat.login.proof_verified') }}</li>
            </ul>
        </div>

        <div class="auth__body">
            <div class="auth__body-inner">
                <div class="auth__wordmark">
                    <span class="auth__wordmark-mark" aria-hidden="true">
                        <x-icon name="chat" :size="18" />
                    </span>
                    <span>{{ config('app.name', 'Chat') }}</span>
                </div>

                <p class="eyebrow">{{ __('chat.login.welcome') }}</p>
                <h1 id="login-heading" class="auth__title">{{ __('chat.login.heading_line_1') }}<br>{{ __('chat.login.heading_line_2') }}</h1>
                <p class="auth__lead">
                    {{ __('chat.login.lead') }}
                </p>

                @if (session('status'))
                    <div class="alert alert--success" role="status">
                        <x-icon name="check-circle" :size="18" />
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                @if ($errors->has('google') || $errors->has('code') || $errors->has('state'))
                    <div class="alert alert--error" role="alert">
                        <x-icon name="alert" :size="18" />
                        <span>
                            {{ $errors->first('google') ?: __('chat.login.error') }}
                        </span>
                    </div>
                @endif

                <a
                    href="{{ route('auth.google.web.redirect') }}"
                    class="button button--google"
                    data-oauth-link
                >
                    <svg class="button__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.27-4.74 3.27-8.1Z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.65l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84A11 11 0 0 0 12 23Z"/>
                        <path fill="#FBBC05" d="M5.84 14.11a6.6 6.6 0 0 1 0-4.22V7.05H2.18a11 11 0 0 0 0 9.9l3.66-2.84Z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15A10.96 10.96 0 0 0 12 1 11 11 0 0 0 2.18 7.05l3.66 2.84C6.71 7.3 9.14 5.38 12 5.38Z"/>
                    </svg>
                    <span class="button__label" data-oauth-label>{{ __('chat.login.continue') }}</span>
                    <span class="button__loading-label" data-oauth-loading-label>{{ __('chat.login.redirecting') }}</span>
                    <span class="button__spinner" aria-hidden="true"><span class="spinner"></span></span>
                    <span class="button__trailing" aria-hidden="true">
                        <x-icon name="arrow-down" :size="17" class="button__arrow" />
                    </span>
                </a>

                <p class="button__note">
                    <span>{{ __('chat.login.other_account') }}</span>
                    <x-icon name="arrow-right" :size="12" class="button__note-icon" />
                </p>

                <p class="oauth__status" data-oauth-status hidden>
                    <span class="oauth__status-text" data-oauth-status-text>{{ __('chat.login.completing') }}</span>
                </p>

                <p class="oauth__delay" data-oauth-delay hidden>
                    <span class="oauth__delay-dot" aria-hidden="true"></span>
                    <span>{{ __('chat.login.delay_google') }}</span>
                </p>

                <p class="auth__fineprint">
                    {{ __('chat.login.fineprint') }}
                </p>

                <div class="auth__trust">
                    <x-icon name="shield" :size="16" />
                    <span>{{ __('chat.login.trust') }}</span>
                </div>
            </div>
        </div>
    </section>

    <p class="auth__footer">{{ __('chat.login.footer') }}</p>
</div>
