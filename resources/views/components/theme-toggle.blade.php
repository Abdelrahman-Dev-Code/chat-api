@props(['size' => 'medium'])

<button
    type="button"
    {{ $attributes->merge(['class' => 'theme-toggle icon-button']) }}
    data-theme-toggle
    aria-label="{{ __('chat.common.theme_dark') }}"
    aria-pressed="false"
    title="{{ __('chat.common.theme_dark') }}"
>
    <x-icon name="moon" class="theme-toggle__icon theme-toggle__icon--moon" :size="19" />
    <x-icon name="sun" class="theme-toggle__icon theme-toggle__icon--sun" :size="19" />
</button>
