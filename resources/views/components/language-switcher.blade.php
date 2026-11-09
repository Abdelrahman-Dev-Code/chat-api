@props(['compact' => false])

@php
    $current = app()->getLocale();
    $next = $current === 'ar' ? 'en' : 'ar';
    $name = config('app.locale_names.'.$next, strtoupper($next));
    $label = __('chat.locale.switch_to', ['language' => $name]);
@endphp

<form method="POST" action="{{ route('locale.switch', $next) }}" class="locale-switcher" data-locale-switch>
    @csrf
    <button
        type="submit"
        class="locale-switcher__button {{ $compact ? 'locale-switcher__button--compact' : '' }}"
        aria-label="{{ $label }}"
        title="{{ $label }}"
        hreflang="{{ $next }}"
        lang="{{ $next }}"
    >
        <x-icon name="globe" :size="$compact ? 15 : 16" />
        <span class="locale-switcher__label">{{ $name }}</span>
    </button>
</form>
