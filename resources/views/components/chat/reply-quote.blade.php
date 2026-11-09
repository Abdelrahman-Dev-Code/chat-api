@props([
    'context' => [],
    'preview' => '',
])

@php
    $deleted = (bool) ($context['deleted'] ?? false);
    $loaded = (bool) ($context['loaded'] ?? false);
    $content = trim((string) ($context['content'] ?? ''));
    $snippet = $content !== '' ? \Illuminate\Support\Str::limit($content, 160) : __('chat.reply.deleted');
    $name = $context['name'] ?? __('chat.reply.unknown_member');
    $label = $deleted
        ? __('chat.reply.original_deleted')
        : ($loaded ? __('chat.reply.jump') : __('chat.reply.outside_view'));
@endphp

<button
    type="button"
    class="reply-quote {{ $deleted ? 'reply-quote--deleted' : '' }}"
    @if (! $deleted) wire:click="jumpToMessage({{ (int) $context['id'] }})" @endif
    @disabled($deleted)
    title="{{ $label }}"
    aria-label="{{ $label }}: {{ $deleted ? __('chat.reply.original_deleted') : $snippet }}"
>
    <span class="reply-quote__bar" aria-hidden="true"></span>

    <span class="reply-quote__body">
        <span class="reply-quote__meta">
            <span class="reply-quote__name">{{ $name }}</span>
            <span class="reply-quote__preview-label">{{ __('chat.reply.tag') }}</span>
        </span>

        <span class="reply-quote__content" dir="auto">
            @if ($deleted)
                <span class="reply-quote__deleted">
                    <x-icon name="trash" :size="12" />
                    {{ __('chat.reply.original_deleted') }}
                </span>
            @else
                {{ $snippet }}
            @endif
        </span>
    </span>

    @if (! $deleted && $preview !== '')
        <span class="sr-only">{{ __('chat.reply.in_reply_to', ['name' => $name, 'preview' => $preview]) }}</span>
    @endif
</button>
