@props([
    'members' => [],
    'memberCount' => 0,
    'memberSearch' => '',
    'membersError' => null,
    'activeMemberId' => null,
    'appName' => config('app.name', 'Chat'),
])

@php
    $viewer = auth()->user();
    $memberTotal = (int) $memberCount;
    $memberLabel = trans_choice('chat.members.count', $memberTotal, ['count' => number_format($memberTotal)]);
    $trimmedSearch = trim((string) $memberSearch);
@endphp

<aside
    class="sidebar"
    id="workspace-sidebar"
    aria-label="{{ __('chat.sidebar.navigation') }}"
    data-sidebar
>
    <div class="sidebar__head">
        <a class="sidebar__brand" href="{{ route('chat') }}" wire:navigate aria-label="{{ __('chat.sidebar.brand_home', ['name' => $appName]) }}">
            <span class="sidebar__brand-mark" aria-hidden="true">
                <x-icon name="chat" :size="20" />
            </span>
            <span class="sidebar__brand-text">
                <span class="sidebar__brand-name">{{ $appName }}</span>
                <span class="sidebar__brand-sub">{{ __('chat.sidebar.brand_sub') }}</span>
            </span>
        </a>

        <button
            type="button"
            class="icon-button sidebar__drawer-close"
            data-sidebar-close
            aria-label="{{ __('chat.sidebar.close_navigation') }}"
            title="{{ __('chat.sidebar.close_navigation') }}"
        >
            <x-icon name="close" :size="18" />
        </button>

        <button
            type="button"
            class="icon-button sidebar__collapse"
            data-sidebar-collapse
            aria-label="{{ __('chat.sidebar.collapse') }}"
            aria-expanded="true"
            title="{{ __('chat.sidebar.collapse') }}"
        >
            <x-icon name="panel-left" :size="18" />
        </button>
    </div>

    <div class="sidebar__scroll">
        <nav class="sidebar__section" aria-label="{{ __('chat.sidebar.rooms') }}">
            <p class="sidebar__label">{{ __('chat.sidebar.rooms') }}</p>

            <div class="sidebar__room sidebar__room--active" aria-current="page">
                <span class="sidebar__room-icon" aria-hidden="true">
                    <x-icon name="chat" :size="17" />
                </span>
                <span class="sidebar__room-text">
                    <span class="sidebar__room-name">{{ __('chat.sidebar.global_chat') }}</span>
                    <span class="sidebar__room-sub">{{ __('chat.sidebar.room_sub', ['members' => $memberLabel]) }}</span>
                </span>
                <span class="sidebar__room-badge">{{ __('chat.sidebar.active') }}</span>
            </div>

            <button
                type="button"
                class="sidebar__room sidebar__room--disabled"
                disabled
                aria-disabled="true"
                title="{{ __('chat.sidebar.direct_messages_title') }}"
            >
                <span class="sidebar__room-icon" aria-hidden="true">
                    <x-icon name="message" :size="17" />
                </span>
                <span class="sidebar__room-text">
                    <span class="sidebar__room-name">{{ __('chat.sidebar.direct_messages') }}</span>
                    <span class="sidebar__room-sub">{{ __('chat.sidebar.coming_soon') }}</span>
                </span>
                <span class="sidebar__soon">{{ __('chat.sidebar.soon') }}</span>
            </button>
        </nav>

        <section class="sidebar__section sidebar__members" aria-labelledby="members-heading">
            <div class="sidebar__section-head">
                <h2 class="sidebar__label" id="members-heading">{{ __('chat.sidebar.members') }}</h2>
                <span class="sidebar__count">{{ $memberTotal }}</span>
            </div>

            <div class="sidebar__search">
                <label class="sr-only" for="member-search">{{ __('chat.sidebar.search_members') }}</label>
                <x-icon name="search" :size="15" class="sidebar__search-icon" />
                <input
                    type="search"
                    id="member-search"
                    class="sidebar__search-input"
                    placeholder="{{ __('chat.sidebar.find_member') }}"
                    autocomplete="off"
                    dir="auto"
                    wire:model.live.debounce.250ms="memberSearch"
                    wire:loading.attr="disabled"
                    wire:target="memberSearch"
                >
                @if ($trimmedSearch !== '')
                    <button
                        type="button"
                        class="sidebar__search-clear"
                        wire:click="clearMemberSearch"
                        aria-label="{{ __('chat.sidebar.clear_member_search') }}"
                        title="{{ __('chat.sidebar.clear_member_search') }}"
                    >
                        <x-icon name="close" :size="13" />
                    </button>
                @endif
            </div>

            @if ($membersError)
                <div class="sidebar__members-state" role="alert">
                    <p>{{ $membersError }}</p>
                    <button
                        type="button"
                        class="button button--ghost button--small"
                        wire:click="retryMembers"
                    >
                        <x-icon name="refresh" :size="14" />
                        {{ __('chat.common.try_again') }}
                    </button>
                </div>
            @elseif (count($members) === 0)
                <div class="sidebar__members-state">
                    @if ($trimmedSearch !== '')
                        <p>{{ __('chat.sidebar.no_match', ['term' => $trimmedSearch]) }}</p>
                        <button
                            type="button"
                            class="button button--ghost button--small"
                            wire:click="clearMemberSearch"
                        >
                            {{ __('chat.common.clear_search') }}
                        </button>
                    @else
                        <p>{{ __('chat.sidebar.no_members') }}</p>
                    @endif
                </div>
            @else
                <ul class="sidebar__member-list" role="list">
                    @foreach ($members as $member)
                        @php
                            $isSelf = (int) $member['id'] === (int) $viewer?->id;
                            $isActive = (int) $activeMemberId === (int) $member['id'];
                            $showLabel = $isSelf
                                ? __('chat.sidebar.show_from_you')
                                : __('chat.sidebar.show_from', ['name' => $member['name']]);
                        @endphp

                        <li wire:key="sidebar-member-{{ $member['id'] }}">
                            <button
                                type="button"
                                class="member {{ $isActive ? 'is-active' : '' }}"
                                wire:click="filterBySender({{ $member['id'] }})"
                                aria-pressed="{{ $isActive ? 'true' : 'false' }}"
                                aria-label="{{ $showLabel }}"
                                title="{{ $showLabel }}"
                            >
                                <x-avatar :user="$member" size="small" />

                                <span class="member__identity">
                                    <span class="member__name">{{ $member['name'] }}</span>
                                    <span class="member__sub">{{ $isSelf ? __('chat.sidebar.member_you') : __('chat.sidebar.member') }}</span>
                                </span>

                                @if ($isActive)
                                    <span class="member__active" aria-hidden="true">
                                        <x-icon name="check" :size="12" />
                                    </span>
                                @endif
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    <div class="sidebar__profile">
        <div class="sidebar__profile-card">
            <x-avatar :user="$viewer" size="small" />

            <div class="sidebar__profile-identity">
                <span class="sidebar__profile-name">{{ $viewer?->name ?? __('chat.sidebar.signed_in') }}</span>
                <span class="sidebar__profile-sub">{{ __('chat.sidebar.signed_in_google') }}</span>
            </div>

            <div class="sidebar__profile-actions">
                <x-language-switcher compact />
                <x-theme-toggle size="small" />
            </div>
        </div>

        <form method="POST" action="{{ route('logout') }}" class="sidebar__logout-form">
            @csrf
            <button type="submit" class="sidebar__logout" title="{{ __('chat.sidebar.sign_out') }}" aria-label="{{ __('chat.sidebar.sign_out') }}">
                <x-icon name="logout" :size="17" />
                <span>{{ __('chat.sidebar.sign_out') }}</span>
            </button>
        </form>
    </div>
</aside>
