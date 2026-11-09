@props(['name', 'size' => '20', 'stroke' => '1.8'])

<svg
    {{ $attributes->merge([
        'class' => 'icon icon--'.$name,
        'width' => $size,
        'height' => $size,
        'viewBox' => '0 0 24 24',
        'fill' => 'none',
        'stroke' => 'currentColor',
        'stroke-width' => $stroke,
        'stroke-linecap' => 'round',
        'stroke-linejoin' => 'round',
        'aria-hidden' => 'true',
        'focusable' => 'false',
    ]) }}
>
    @switch($name)
        @case('chat')
            <path d="M21 11.5a8.4 8.4 0 0 1-8.5 8.3 9 9 0 0 1-3.8-.8L3 21l2-5.4a8.1 8.1 0 0 1-1-4A8.4 8.4 0 0 1 12.5 3 8.4 8.4 0 0 1 21 11.5Z" />
            <path d="M8.5 11.5h.01M12.5 11.5h.01M16.5 11.5h.01" stroke-width="2.4" />
            @break

        @case('sun')
            <circle cx="12" cy="12" r="4" />
            <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" />
            @break

        @case('moon')
            <path d="M21 12.8A8.5 8.5 0 1 1 11.2 3a6.7 6.7 0 0 0 9.8 9.8Z" />
            @break

        @case('globe')
            <circle cx="12" cy="12" r="9" />
            <path d="M3 12h18" />
            <path d="M12 3c2.5 2.5 3.8 5.6 3.8 9S14.5 18.5 12 21c-2.5-2.5-3.8-5.6-3.8-9S9.5 5.5 12 3Z" />
            @break

        @case('chevron-down')
            <path d="m6 9 6 6 6-6" />
            @break

        @case('logout')
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
            <path d="m16 17 5-5-5-5M21 12H9" />
            @break

        @case('edit')
            <path d="M12 20h9" />
            <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" />
            @break

        @case('trash')
            <path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6M10 11v6M14 11v6" />
            @break

        @case('send')
            <path d="m22 2-7 20-4-9-9-4Z" />
            <path d="M22 2 11 13" />
            @break

        @case('arrow-down')
            <path d="M12 5v14M19 12l-7 7-7-7" />
            @break

        @case('arrow-right')
            <path d="M5 12h14M12 5l7 7-7 7" />
            @break

        @case('history')
            <path d="M3 12a9 9 0 1 0 3-6.7L3 8" />
            <path d="M3 3v5h5M12 7v5l3 2" />
            @break

        @case('refresh')
            <path d="M20 11a8 8 0 0 0-14.8-4L3 10" />
            <path d="M3 4v6h6M4 13a8 8 0 0 0 14.8 4L21 14" />
            <path d="M21 20v-6h-6" />
            @break

        @case('check')
            <path d="m5 12 4.5 4.5L19 7" />
            @break

        @case('check-circle')
            <circle cx="12" cy="12" r="9" />
            <path d="m8.5 12 2.5 2.5 4.5-5" />
            @break

        @case('alert')
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7v5M12 16h.01" />
            @break

        @case('close')
            <path d="m6 6 12 12M18 6 6 18" />
            @break

        @case('bolt')
            <path d="M13 2 4.5 13.5H11l-1 8.5 8.5-11.5H12l1-8.5Z" />
            @break

        @case('lock')
            <rect x="4" y="10" width="16" height="11" rx="2" />
            <path d="M8 10V7a4 4 0 0 1 8 0v3M12 15v2" />
            @break

        @case('shield')
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z" />
            <path d="m9 12 2 2 4-4" />
            @break

        @case('sparkles')
            <path d="m12 3 1.4 4.1L17.5 8.5l-4.1 1.4L12 14l-1.4-4.1-4.1-1.4 4.1-1.4Z" />
            <path d="m19 14 .7 2.1 2.1.7-2.1.7L19 19.6l-.7-2.1-2.1-.7 2.1-.7Z" />
            @break

        @case('users')
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
            @break

        @case('message')
            <path d="M21 15a2 2 0 0 1-2 2H8l-5 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2Z" />
            @break

        @case('wifi-off')
            <path d="m2 2 20 20M8.5 8.5a10.7 10.7 0 0 1 12.6 2M5 12.5a10.7 10.7 0 0 1 2.1-1.5M2 8.8a15.8 15.8 0 0 1 4.2-2.6M8.8 16.1a5 5 0 0 1 6.4 0M12 20h.01" />
            @break

        @case('clock')
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7v5l3 2" />
            @break

        @case('menu')
            <path d="M4 7h16M4 12h16M4 17h16" />
            @break

        @case('search')
            <circle cx="11" cy="11" r="7" />
            <path d="m20 20-3.8-3.8" />
            @break

        @case('smile')
            <circle cx="12" cy="12" r="9" />
            <path d="M8.5 14.5a4.4 4.4 0 0 0 7 0" />
            <path d="M9 9.5h.01M15 9.5h.01" stroke-width="2.4" />
            @break

        @case('reply')
            <path d="M9 17 4 12l5-5" />
            <path d="M4 12h9a7 7 0 0 1 7 7v1" />
            @break

        @case('sliders')
            <path d="M4 7h9M17 7h3M4 17h3M11 17h9" />
            <circle cx="15" cy="7" r="2" />
            <circle cx="9" cy="17" r="2" />
            @break

        @case('panel-left')
            <rect x="3" y="4" width="18" height="16" rx="2" />
            <path d="M9 4v16" />
            @break

        @case('info')
            <circle cx="12" cy="12" r="9" />
            <path d="M12 11v5M12 8h.01" />
            @break

        @default
            <circle cx="12" cy="12" r="9" />
    @endswitch
</svg>
