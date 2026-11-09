@props([
    'messageId',
    'options' => [],
    'selected' => [],
    'preview' => '',
])

<div
    class="reaction-picker"
    x-data="{ open: false }"
    @keydown.escape.stop="open = false; $refs.trigger.focus()"
    @click.outside="open = false"
>
    <button
        type="button"
        class="message__action reaction-picker__trigger"
        x-ref="trigger"
        @click="open = ! open"
        x-bind:aria-expanded="open"
        aria-haspopup="menu"
        aria-label="{{ __('chat.reactions.add') }}"
        title="{{ __('chat.reactions.react') }}"
    >
        <x-icon name="smile" :size="15" />
    </button>

    <div
        class="reaction-picker__popover"
        x-show="open"
        x-cloak
        x-transition.origin.bottom.left
        role="menu"
        aria-label="{{ __('chat.reactions.choose', ['preview' => $preview]) }}"
    >
        <p class="reaction-picker__note">
            <span class="preview-dot" aria-hidden="true"></span>
            {{ __('chat.reactions.preview') }}
        </p>

        <div class="reaction-picker__grid" role="none">
            @foreach ($options as $emoji)
                @php $isSelected = in_array($emoji, $selected, true); @endphp

                <button
                    type="button"
                    class="reaction-picker__option {{ $isSelected ? 'is-selected' : '' }}"
                    role="menuitemcheckbox"
                    aria-checked="{{ $isSelected ? 'true' : 'false' }}"
                    aria-label="{{ $isSelected ? __('chat.reactions.remove_emoji', ['emoji' => $emoji]) : __('chat.reactions.add_emoji', ['emoji' => $emoji]) }}"
                    title="{{ $isSelected ? __('chat.reactions.remove_emoji', ['emoji' => $emoji]) : __('chat.reactions.add_emoji', ['emoji' => $emoji]) }}"
                    wire:click="toggleReaction({{ $messageId }}, '{{ $emoji }}')"
                    @click="open = false; $nextTick(() => $refs.trigger.focus())"
                >{{ $emoji }}</button>
            @endforeach
        </div>
    </div>
</div>
