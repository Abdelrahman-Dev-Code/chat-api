@props([
    'messageId',
    'summary' => [],
])

@if ($summary !== [])
    <div class="reaction-chips" role="group" aria-label="{{ __('chat.reactions.previews_label') }}">
        @foreach ($summary as $reaction)
            @php
                $count = (int) $reaction['count'];
                $people = $count === 1
                    ? __('chat.reactions.one_person')
                    : __('chat.reactions.people', ['count' => $count]);
                $names = implode(', ', $reaction['people']);
            @endphp

            <button
                type="button"
                class="reaction-chip {{ $reaction['mine'] ? 'is-mine' : '' }}"
                wire:click="toggleReaction({{ $messageId }}, '{{ $reaction['emoji'] }}')"
                aria-pressed="{{ $reaction['mine'] ? 'true' : 'false' }}"
                aria-label="{{ __('chat.reactions.chip_label', ['emoji' => $reaction['emoji'], 'people' => $people, 'names' => $names]) }}"
                title="{{ __('chat.reactions.chip_title', ['title' => $reaction['title']]) }}"
            >
                <span class="reaction-chip__emoji" aria-hidden="true">{{ $reaction['emoji'] }}</span>
                <span class="reaction-chip__count">{{ $count }}</span>
            </button>
        @endforeach
    </div>
@endif
