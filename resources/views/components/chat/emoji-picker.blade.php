@props([])

<div
    class="emoji-picker"
    x-data="{ open: false, query: '' }"
    @click.outside="open = false"
    @keydown.escape.stop="open = false"
>
    <button
        type="button"
        class="emoji-picker__trigger"
        @click="open = ! open"
        x-bind:aria-expanded="open"
        aria-haspopup="grid"
        aria-label="{{ __('chat.composer.emoji') }}"
        title="{{ __('chat.composer.emoji_button') }}"
    >
        <span class="emoji-picker__emoji" aria-hidden="true">😀</span>
    </button>

    <div
        class="emoji-picker__popover"
        x-show="open"
        x-cloak
        x-transition.origin.top.left
        role="grid"
        aria-label="{{ __('chat.composer.emoji') }}"
    >
        <div class="emoji-picker__search">
            <label for="emoji-picker-search" class="sr-only">{{ __('chat.composer.emoji') }}</label>
            <input
                type="search"
                id="emoji-picker-search"
                class="emoji-picker__search-input"
                placeholder="{{ __('chat.header.search_placeholder') }}"
                x-model="query"
                @input="open = true"
                aria-label="{{ __('chat.composer.emoji') }}"
            />
        </div>

        <div class="emoji-picker__grid" role="none">
            @php
                $emojis = [
                    '😀', '😃', '😄', '😁', '😆', '😅', '😂', '🤣', '😊', '😇',
                    '🙂', '🙃', '😉', '😌', '😍', '🥰', '😘', '😛', '😜', '😝',
                    '😴', '😔', '😕', '😟', '😞', '😢', '😭', '😤', '😠', '😡',
                    '😬', '😰', '😱', '😳', '🤐', '😶', '😐', '😑', '😒', '🙄',
                    '😯', '😦', '😧', '😮', '😲', '🥵', '🥶', '🤡',
                ];
            @endphp

            @foreach ($emojis as $emoji)
                <button
                    type="button"
                    class="emoji-picker__option"
                    role="gridcell"
                    aria-label="{{ $emoji }}"
                    title="{{ $emoji }}"
                    x-show="query === '' || '{{ $emoji }}'.toLowerCase().includes(query.toLowerCase())"
                    @click="open = false; $el.dispatchEvent(new CustomEvent('emoji-select', { detail: '{{ $emoji }}', bubbles: true }))"
                >
                    <span class="emoji-picker__emoji" aria-hidden="true">{{ $emoji }}</span>
                </button>
            @endforeach
        </div>

        <p
            class="emoji-picker__empty"
            x-show="query !== '' && $el.querySelectorAll('.emoji-picker__option:not([style*=\"display: none\"])').length === 0"
        >
            {{ __('chat.messages.empty_filtered_title') }}
        </p>
    </div>
</div>
