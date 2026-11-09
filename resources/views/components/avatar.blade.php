@props(['user', 'size' => 'medium'])

@php
    $name = is_array($user) ? ($user['name'] ?? '') : ($user->name ?? '');
    $avatar = is_array($user) ? ($user['avatar'] ?? null) : ($user->avatar ?? null);
    $id = is_array($user) ? ($user['id'] ?? 0) : ($user->id ?? 0);

    $words = preg_split('/\s+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $initials = mb_strtoupper(
        mb_substr($words[0] ?? '?', 0, 1).mb_substr($words[1] ?? '', 0, 1)
    );
    $tone = $id % 5;
@endphp

<span class="avatar avatar--{{ $size }} avatar--tone-{{ $tone }}" aria-hidden="true">
    <span class="avatar__initial">{{ $initials }}</span>

    @if ($avatar)
        <img
            class="avatar__image"
            src="{{ $avatar }}"
            alt=""
            loading="lazy"
            decoding="async"
            referrerpolicy="no-referrer"
            onerror="this.remove()"
        >
    @endif
</span>
