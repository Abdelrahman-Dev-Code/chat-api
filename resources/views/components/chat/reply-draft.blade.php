@props([
    'replyingTo' => null,
])

@if ($replyingTo)
    @php
        $snippet = \Illuminate\Support\Str::limit(trim((string) ($replyingTo['content'] ?? '')), 180);
        $name = $replyingTo['name'] ?? __('chat.reply.member');
    @endphp

    <div class="composer__reply" role="group" aria-label="{{ __('chat.reply.preview_label') }}">
        <span class="composer__reply-bar" aria-hidden="true"></span>

        <div class="composer__reply-body">
            <p class="composer__reply-meta">
                <span class="preview-tag">{{ __('chat.reply.preview') }}</span>
                <span class="composer__reply-name">{{ __('chat.reply.replying_to', ['name' => $name]) }}</span>
            </p>
            <p class="composer__reply-content" dir="auto">{{ $snippet !== '' ? $snippet : __('chat.reply.deleted') }}</p>
        </div>

        <button
            type="button"
            class="icon-button composer__reply-cancel"
            wire:click="cancelReply"
            aria-label="{{ __('chat.reply.cancel') }}"
            title="{{ __('chat.reply.cancel') }}"
        >
            <x-icon name="close" :size="15" />
        </button>
    </div>
@endif
