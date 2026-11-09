@php
    $memberTotal = count($members);
    $memberLabel = trans_choice('chat.members.count', $memberTotal, ['count' => number_format($memberTotal)]);
    $activeMemberId = $filter === 'mine'
        ? auth()->id()
        : ($senderFilter !== 'all' ? (int) $senderFilter : null);
    $activeSender = collect($senders)->firstWhere('id', (int) $senderFilter);
    $hasFilters = $this->hasActiveFilters();
    $activeFilterCount = ($search !== '' ? 1 : 0)
        + ($senderFilter !== 'all' ? 1 : 0)
        + ($datePreset !== 'all' || $dateFrom !== '' || $dateTo !== '' ? 1 : 0)
        + ($filter === 'mine' ? 1 : 0);
@endphp

<div
    class="workspace"
    id="workspace"
    data-workspace
    x-data="{ deleteId: null, deletePreview: '', deleting: false, filterOpen: false }"
    wire:loading.attr="data-busy"
>
    <x-chat.sidebar
        :members="$this->visibleMembers()"
        :member-count="$memberTotal"
        :member-search="$memberSearch"
        :members-error="$membersError"
        :active-member-id="$activeMemberId"
    />

    <div class="workspace__overlay" data-sidebar-close aria-hidden="true"></div>

    <div class="workspace__content">
        <section class="chat" aria-labelledby="chat-heading">
            <header class="chat__header">
                <div class="chat__header-main">
                    <button
                        type="button"
                        class="icon-button chat__menu"
                        data-sidebar-open
                        aria-controls="workspace-sidebar"
                        aria-expanded="false"
                        aria-label="{{ __('chat.header.open_navigation') }}"
                        title="{{ __('chat.header.open_navigation') }}"
                    >
                        <x-icon name="menu" :size="19" />
                    </button>

                    <div class="chat__identity">
                        <span class="chat__mark" aria-hidden="true">
                            <x-icon name="chat" :size="21" />
                        </span>

                        <div class="chat__identity-text">
                            <p class="eyebrow">{{ __('chat.header.eyebrow') }}</p>
                            <h1 id="chat-heading" class="chat__title">{{ __('chat.header.title') }}</h1>
                            <p class="chat__subtitle">
                                <x-icon name="bolt" :size="13" />
                                {{ __('chat.header.subtitle') }}
                            </p>
                        </div>
                    </div>

                    <div class="chat__header-meta">
                        <div class="chat__locale">
                            <x-language-switcher compact />
                        </div>

                        @unless ($membersError)
                            <span class="member-count" title="{{ __('chat.header.member_title', ['label' => $memberLabel]) }}">
                                <x-icon name="users" :size="15" />
                                <span>{{ $memberLabel }}</span>
                            </span>
                        @endunless

                        <div
                            id="connection-status"
                            class="connection-status"
                            role="status"
                            aria-live="polite"
                            aria-atomic="true"
                            hidden
                        >
                            <span class="connection-status__dot" aria-hidden="true"></span>
                            <span data-connection-label>{{ __('chat.connection.connecting') }}</span>
                            <button
                                type="button"
                                class="connection-status__retry"
                                data-connection-retry
                                hidden
                            >
                                {{ __('chat.connection.retry') }}
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <div class="chat-toolbar">
                <div class="chat-toolbar__bar">
                    <div class="chat__search chat-toolbar__search">
                        <label for="message-search" class="sr-only">{{ __('chat.header.search_label') }}</label>
                        <div class="search-wrapper">
                            <x-icon name="search" :size="16" class="search__icon" />
                            <input
                                type="search"
                                id="message-search"
                                class="search__input"
                                placeholder="{{ __('chat.header.search_placeholder') }}"
                                wire:model.live.debounce.450ms="search"
                                wire:loading.attr="disabled"
                                wire:target="search"
                                wire:keydown.escape="clearSearchFilter"
                                aria-label="{{ __('chat.header.search_label') }}"
                                title="{{ __('chat.header.search_shortcut') }}"
                                autocomplete="off"
                                dir="auto"
                            >
                            @if ($search !== '')
                                <button
                                    type="button"
                                    class="search__clear"
                                    wire:click="clearSearchFilter"
                                    aria-label="{{ __('chat.header.clear_search') }}"
                                    title="{{ __('chat.common.clear_search') }}"
                                >
                                    <x-icon name="close" :size="14" />
                                </button>
                            @endif
                        </div>
                    </div>

                    <button
                        type="button"
                        class="chat-toolbar__toggle"
                        @click="filterOpen = ! filterOpen"
                        x-bind:aria-expanded="filterOpen"
                        aria-controls="chat-filters"
                        title="{{ __('chat.header.show_filters') }}"
                    >
                        <x-icon name="sliders" :size="16" />
                        <span>{{ __('chat.header.filters') }}</span>
                        @if ($activeFilterCount > 0)
                            <span class="chat-toolbar__count">{{ $activeFilterCount }}</span>
                        @endif
                    </button>

                    <div
                        class="chat-toolbar__filters"
                        id="chat-filters"
                        x-bind:class="{ 'is-open': filterOpen }"
                    >
                        <div class="chat__filter-group">
                            <label for="sender-filter" class="sr-only">{{ __('chat.header.filter_sender') }}</label>
                            <div class="select">
                                <select
                                    id="sender-filter"
                                    class="sender-select"
                                    wire:model.live="senderFilter"
                                    wire:loading.attr="disabled"
                                    wire:target="senderFilter"
                                    aria-label="{{ __('chat.header.filter_sender') }}"
                                >
                                    <option value="all">{{ __('chat.header.all_senders') }}</option>
                                    @foreach ($senders as $sender)
                                        <option value="{{ $sender['id'] }}">{{ $sender['name'] }}</option>
                                    @endforeach
                                </select>
                                <x-icon name="chevron-down" :size="16" class="select__chevron" />
                            </div>
                        </div>

                        <div class="chat__filter-group">
                            <label for="date-preset" class="sr-only">{{ __('chat.header.filter_date') }}</label>
                            <div class="select">
                                <select
                                    id="date-preset"
                                    class="date-select"
                                    wire:model.live="datePreset"
                                    wire:loading.attr="disabled"
                                    wire:target="datePreset"
                                    aria-label="{{ __('chat.header.filter_date') }}"
                                >
                                    <option value="all">{{ __('chat.header.all_time') }}</option>
                                    <option value="today">{{ __('chat.header.today') }}</option>
                                    <option value="yesterday">{{ __('chat.header.yesterday') }}</option>
                                    <option value="last_7_days">{{ __('chat.header.last_7_days') }}</option>
                                    <option value="last_30_days">{{ __('chat.header.last_30_days') }}</option>
                                    <option value="custom">{{ __('chat.header.custom_range') }}</option>
                                </select>
                                <x-icon name="chevron-down" :size="16" class="select__chevron" />
                            </div>
                        </div>

                        <fieldset class="segmented" aria-label="{{ __('chat.header.show_messages') }}">
                            <legend class="sr-only">{{ __('chat.header.filter_messages') }}</legend>

                            <label class="segmented__option">
                                <input
                                    type="radio"
                                    name="filter"
                                    value="all"
                                    class="segmented__input"
                                    wire:model.live="filter"
                                    wire:loading.attr="disabled"
                                    wire:target="filter"
                                    @checked($filter === 'all')
                                >
                                <span class="segmented__label">{{ __('chat.header.everyone') }}</span>
                            </label>

                            <label class="segmented__option">
                                <input
                                    type="radio"
                                    name="filter"
                                    value="mine"
                                    class="segmented__input"
                                    wire:model.live="filter"
                                    wire:loading.attr="disabled"
                                    wire:target="filter"
                                    @checked($filter === 'mine')
                                >
                                <span class="segmented__label">{{ __('chat.header.only_me') }}</span>
                            </label>
                        </fieldset>
                    </div>
                </div>

                @if ($datePreset === 'custom')
                    <div class="custom-date-row">
                        <label for="date-from" class="custom-date-row__label">
                            {{ __('chat.header.from') }}
                            <input
                                type="date"
                                id="date-from"
                                class="date-input"
                                wire:model="dateFrom"
                                wire:loading.attr="disabled"
                                wire:target="applyCustomDateRange"
                                aria-label="{{ __('chat.header.from_label') }}"
                            >
                        </label>

                        <span class="date-separator-text" aria-hidden="true">{{ __('chat.header.to') }}</span>

                        <label for="date-to" class="custom-date-row__label">
                            {{ __('chat.header.to') }}
                            <input
                                type="date"
                                id="date-to"
                                class="date-input"
                                wire:model="dateTo"
                                wire:loading.attr="disabled"
                                wire:target="applyCustomDateRange"
                                aria-label="{{ __('chat.header.to_label') }}"
                            >
                        </label>

                        <button
                            type="button"
                            class="button button--primary button--small custom-date-row__apply"
                            wire:click="applyCustomDateRange"
                            wire:loading.attr="disabled"
                            wire:target="applyCustomDateRange"
                        >
                            {{ __('chat.header.apply_dates') }}
                        </button>

                        @if ($dateError)
                            <p class="field-error custom-date-row__error" role="alert">
                                <x-icon name="alert" :size="14" />
                                {{ $dateError }}
                            </p>
                        @endif
                    </div>
                @endif

                @if ($hasFilters)
                    <div class="filter-chips" role="list" aria-label="{{ __('chat.header.active_filters') }}">
                        @if ($filter === 'mine')
                            <span class="filter-chip" role="listitem">
                                <x-icon name="users" :size="13" />
                                {{ __('chat.header.only_me') }}
                                <button
                                    type="button"
                                    class="filter-chip__remove"
                                    wire:click="clearOnlyMeFilter"
                                    aria-label="{{ __('chat.header.remove_only_me') }}"
                                    title="{{ __('chat.header.remove_filter') }}"
                                >
                                    <x-icon name="close" :size="12" />
                                </button>
                            </span>
                        @endif

                        @if ($search !== '')
                            <span class="filter-chip" role="listitem">
                                <x-icon name="search" :size="13" />
                                {{ __('chat.header.search_chip', ['term' => \Illuminate\Support\Str::limit($search, 28)]) }}
                                <button
                                    type="button"
                                    class="filter-chip__remove"
                                    wire:click="clearSearchFilter"
                                    aria-label="{{ __('chat.header.remove_search') }}"
                                    title="{{ __('chat.header.remove_filter') }}"
                                >
                                    <x-icon name="close" :size="12" />
                                </button>
                            </span>
                        @endif

                        @if ($senderFilter !== 'all')
                            <span class="filter-chip" role="listitem">
                                <x-icon name="message" :size="13" />
                                {{ __('chat.header.from_member', ['name' => $activeSender['name'] ?? __('chat.header.member_fallback')]) }}
                                <button
                                    type="button"
                                    class="filter-chip__remove"
                                    wire:click="clearSenderFilter"
                                    aria-label="{{ __('chat.header.remove_sender') }}"
                                    title="{{ __('chat.header.remove_filter') }}"
                                >
                                    <x-icon name="close" :size="12" />
                                </button>
                            </span>
                        @endif

                        @if ($datePreset !== 'all' || $dateFrom !== '' || $dateTo !== '')
                            <span class="filter-chip" role="listitem">
                                <x-icon name="clock" :size="13" />
                                {{ $this->datePresetLabel() }}
                                @if ($dateFrom !== '' || $dateTo !== '')
                                    <span class="filter-chip__detail">{{ __('chat.header.date_range', ['from' => $dateFrom !== '' ? $dateFrom : '…', 'to' => $dateTo !== '' ? $dateTo : '…']) }}</span>
                                @endif
                                <button
                                    type="button"
                                    class="filter-chip__remove"
                                    wire:click="clearDateFilter"
                                    aria-label="{{ __('chat.header.remove_date') }}"
                                    title="{{ __('chat.header.remove_filter') }}"
                                >
                                    <x-icon name="close" :size="12" />
                                </button>
                            </span>
                        @endif

                        <button
                            type="button"
                            class="filter-chips__clear"
                            wire:click="clearFilters"
                            title="{{ __('chat.header.clear_all_filters') }}"
                        >
                            {{ __('chat.header.clear_all') }}
                        </button>
                    </div>
                @endif
            </div>

            <div class="chat__stage">
                <div
                    class="chat__body"
                    id="message-list"
                    role="log"
                    aria-live="polite"
                    aria-relevant="additions"
                    aria-label="{{ __('chat.header.message_history') }}"
                    tabindex="0"
                    wire:loading.attr="aria-busy"
                    wire:target="search,senderFilter,datePreset,dateFrom,dateTo,filter,loadMore"
                >
                    @if ($loadError)
                        <div class="error-state" role="alert">
                            <span class="error-state__icon" aria-hidden="true">
                                <x-icon name="wifi-off" :size="24" />
                            </span>
                            <h2>{{ __('chat.messages.load_error_title') }}</h2>
                            <p>{{ $loadError }}</p>
                            <button
                                type="button"
                                class="button button--primary button--small"
                                wire:click="retryLoad"
                                wire:loading.attr="disabled"
                                wire:target="retryLoad"
                            >
                                <span wire:loading.remove wire:target="retryLoad">
                                    <x-icon name="refresh" :size="15" />
                                    {{ __('chat.common.try_again') }}
                                </span>
                                <span wire:loading wire:target="retryLoad" class="button__loading">
                                    <span class="spinner" aria-hidden="true"></span>
                                    {{ __('chat.messages.retry_loading') }}
                                </span>
                            </button>
                        </div>
                    @elseif (count($messages) === 0)
                        <div class="empty-state">
                            <div class="empty-state__art" aria-hidden="true">
                                <span class="empty-state__ring empty-state__ring--one"></span>
                                <span class="empty-state__ring empty-state__ring--two"></span>
                                <span class="empty-state__icon">
                                    <x-icon name="message" :size="26" />
                                </span>
                            </div>
                            <h2>
                                @if ($hasFilters)
                                    {{ __('chat.messages.empty_filtered_title') }}
                                @else
                                    {{ __('chat.messages.empty_title') }}
                                @endif
                            </h2>
                            <p>
                                @if ($hasFilters)
                                    {{ __('chat.messages.empty_filtered_copy') }}
                                    <br>
                                    <button type="button" class="button button--primary button--small inline-button" wire:click="clearFilters">
                                        {{ __('chat.header.clear_all_filters') }}
                                    </button>
                                @else
                                    {{ __('chat.messages.empty_copy') }}
                                @endif
                            </p>
                            @unless ($hasFilters)
                                <button type="button" class="button button--primary button--small" data-focus-composer>
                                    <x-icon name="send" :size="15" />
                                    {{ __('chat.messages.say_hello') }}
                                </button>
                            @endunless
                        </div>
                    @else
                        @if ($hasMore)
                            <div class="load-more">
                                <button
                                    type="button"
                                    class="button button--ghost button--small"
                                    data-load-more
                                    wire:click="loadMore"
                                    wire:loading.attr="disabled"
                                    wire:target="loadMore"
                                >
                                    <span wire:loading.remove wire:target="loadMore">
                                        <x-icon name="history" :size="15" />
                                        {{ __('chat.messages.load_older') }}
                                    </span>
                                    <span wire:loading wire:target="loadMore" class="button__loading">
                                        <span class="spinner" aria-hidden="true"></span>
                                        {{ __('chat.messages.loading_older') }}
                                    </span>
                                </button>
                            </div>
                        @else
                            <div class="transcript-start">
                                <span>{{ __('chat.messages.beginning') }}</span>
                            </div>
                        @endif

                        @php
                            $previousMessage = null;
                            $previousCreatedAt = null;
                        @endphp

                        @foreach ($messages as $message)
                            @php
                                $createdAt = \Illuminate\Support\Carbon::parse($message['created_at']);
                                $own = (int) $message['user']['id'] === auth()->id();
                                $showDate = $previousCreatedAt === null || ! $previousCreatedAt->isSameDay($createdAt);
                                $sameAuthor = $previousMessage !== null
                                    && (int) $previousMessage['user']['id'] === (int) $message['user']['id'];
                                $gap = $previousCreatedAt ? abs($createdAt->diffInSeconds($previousCreatedAt)) : null;
                                $grouped = $sameAuthor && ! $showDate && $gap !== null && $gap < 300;

                                $nextMessage = $messages[$loop->index + 1] ?? null;
                                $nextCreatedAt = $nextMessage ? \Illuminate\Support\Carbon::parse($nextMessage['created_at']) : null;
                                $nextGap = $nextCreatedAt ? abs($nextCreatedAt->diffInSeconds($createdAt)) : null;
                                $lastInGroup = $nextMessage === null
                                    || ! $nextCreatedAt->isSameDay($createdAt)
                                    || (int) $nextMessage['user']['id'] !== (int) $message['user']['id']
                                    || $nextGap >= 300;

                                $dateLabel = $createdAt->isToday()
                                    ? __('chat.messages.today')
                                    : ($createdAt->isYesterday()
                                        ? __('chat.messages.yesterday')
                                        : $createdAt->translatedFormat(__('chat.dates.short_format')));
                                $exactDate = __('chat.dates.exact', [
                                    'date' => $createdAt->translatedFormat(__('chat.dates.date_format')),
                                    'time' => $createdAt->translatedFormat(__('chat.dates.time_format')).' '.($createdAt->format('A') === 'AM' ? __('chat.dates.am') : __('chat.dates.pm')),
                                ]);
                                $relativeDate = $createdAt->diffForHumans();
                                $preview = \Illuminate\Support\Str::limit($message['content'], 120);
                                $reactionSummary = $this->reactionSummary($message['id']);
                                $selectedReactions = $this->selectedReactions($message['id']);
                                $quote = $replyContext[$message['id']] ?? null;
                            @endphp

                            @if ($showDate)
                                <div
                                    class="date-separator"
                                    wire:key="date-{{ $createdAt->format('Y-m-d') }}"
                                    role="separator"
                                    aria-label="{{ __('chat.messages.from_date', ['date' => $dateLabel]) }}"
                                >
                                    <span>{{ $dateLabel }}</span>
                                </div>
                            @endif

                            <article
                                class="message {{ $own ? 'message--own' : '' }} {{ $grouped ? 'message--grouped' : '' }} {{ $lastInGroup ? 'message--last' : '' }}"
                                wire:key="message-{{ $message['id'] }}"
                                id="message-{{ $message['id'] }}"
                                data-message-id="{{ $message['id'] }}"
                                tabindex="-1"
                                aria-label="{{ $own ? __('chat.messages.from_you') : __('chat.messages.from_member', ['name' => $message['user']['name']]) }}"
                            >
                                @if ($grouped)
                                    <span class="message__avatar message__avatar--ghost" aria-hidden="true"></span>
                                @else
                                    <span class="message__avatar">
                                        <x-avatar :user="$message['user']" size="medium" />
                                    </span>
                                @endif

                                <div class="message__body">
                                    @if (! $grouped)
                                        <div class="message__meta">
                                            <span class="message__name">{{ $own ? __('chat.common.you') : $message['user']['name'] }}</span>
                                        </div>
                                    @endif

                                    @if ($editingId === $message['id'])
                                        <form class="edit-form" wire:submit="saveEdit">
                                            <label class="sr-only" for="edit-message-{{ $message['id'] }}">{{ __('chat.messages.edit') }}</label>
                                            <textarea
                                                id="edit-message-{{ $message['id'] }}"
                                                class="textarea edit-form__textarea"
                                                rows="3"
                                                maxlength="2000"
                                                wire:model="editContent"
                                                data-autoresize
                                                required
                                                dir="auto"
                                                wire:keydown.escape="cancelEditing"
                                                x-init="$nextTick(() => { $el.focus(); $el.setSelectionRange($el.value.length, $el.value.length); })"
                                            ></textarea>

                                            @error('editContent')
                                                <p class="field-error" role="alert">{{ $message }}</p>
                                            @enderror

                                            <div class="edit-form__footer">
                                                <span class="edit-form__hint">{{ __('chat.messages.edit_hint') }}</span>
                                                <div class="edit-form__actions">
                                                    <button
                                                        type="button"
                                                        class="button button--ghost button--small"
                                                        wire:click="cancelEditing"
                                                    >
                                                        {{ __('chat.common.cancel') }}
                                                    </button>
                                                    <button
                                                        type="submit"
                                                        class="button button--primary button--small"
                                                        data-edit-submit
                                                        wire:loading.attr="disabled"
                                                        wire:target="saveEdit"
                                                    >
                                                        <span wire:loading.remove wire:target="saveEdit">{{ __('chat.messages.save_changes') }}</span>
                                                        <span wire:loading wire:target="saveEdit" class="button__loading">
                                                            <span class="spinner" aria-hidden="true"></span>
                                                            {{ __('chat.messages.saving') }}
                                                        </span>
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    @else
                                        @if ($quote)
                                            <x-chat.reply-quote :context="$quote" :preview="$preview" />
                                        @endif

                                        <div class="message__bubble" dir="auto">{{ $message['content'] }}</div>

                                        <x-chat.reaction-chips
                                            :message-id="$message['id']"
                                            :summary="$reactionSummary"
                                        />

                                        <div class="message__foot">
                                            <span class="message__details">
                                                @if (($message['updated_at'] ?? null) !== ($message['created_at'] ?? null))
                                                    <span class="message__edited">{{ __('chat.messages.edited') }}</span>
                                                @endif

                                                @if ($lastInGroup)
                                                    <time
                                                        class="message__time"
                                                        datetime="{{ $message['created_at'] }}"
                                                        title="{{ $exactDate }}"
                                                        aria-label="{{ __('chat.messages.sent_at', ['relative' => $relativeDate, 'exact' => $exactDate]) }}"
                                                    >{{ $relativeDate }}</time>
                                                @endif
                                            </span>

                                            <span class="message__actions">
                                                <button
                                                    type="button"
                                                    class="message__action message__action--copy"
                                                    data-copy-button
                                                    data-message-content="{{ $message['content'] }}"
                                                    aria-label="{{ __('chat.js.copy_message') }}"
                                                    title="{{ __('chat.messages.copy') }}"
                                                >
                                                    <x-icon name="copy" :size="15" />
                                                </button>

                                                <x-chat.reaction-picker
                                                    :message-id="$message['id']"
                                                    :options="$reactionOptions"
                                                    :selected="$selectedReactions"
                                                    :preview="$preview"
                                                />

                                                <button
                                                    type="button"
                                                    class="message__action"
                                                    wire:click="startReply({{ $message['id'] }})"
                                                    aria-label="{{ __('chat.messages.reply_to', ['preview' => $preview]) }}"
                                                    title="{{ __('chat.messages.reply') }}"
                                                >
                                                    <x-icon name="reply" :size="15" />
                                                </button>

                                                @if ($own)
                                                    <button
                                                        type="button"
                                                        class="message__action"
                                                        wire:click="startEditing({{ $message['id'] }})"
                                                        aria-label="{{ __('chat.messages.edit_preview', ['preview' => $preview]) }}"
                                                        title="{{ __('chat.messages.edit') }}"
                                                    >
                                                        <x-icon name="edit" :size="15" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="message__action message__action--danger"
                                                        data-delete-id="{{ $message['id'] }}"
                                                        data-delete-preview="{{ $preview }}"
                                                        @click="deleteId = $el.dataset.deleteId; deletePreview = $el.dataset.deletePreview; $refs.deleteDialog.showModal()"
                                                        aria-label="{{ __('chat.messages.delete_preview', ['preview' => $preview]) }}"
                                                        title="{{ __('chat.messages.delete') }}"
                                                    >
                                                        <x-icon name="trash" :size="15" />
                                                    </button>
                                                @endif
                                            </span>
                                        </div>
                                    @endif
                                </div>
                            </article>

                            @php
                                $previousMessage = $message;
                                $previousCreatedAt = $createdAt;
                            @endphp
                        @endforeach
                    @endif

                    <div
                        id="pending-messages"
                        class="pending-messages"
                        wire:ignore
                        aria-live="polite"
                        aria-relevant="additions removals"
                    ></div>
                </div>

                <div
                    class="chat__loading-indicator"
                    wire:loading.attr="data-active"
                    wire:target="search,senderFilter,datePreset,filter,applyCustomDateRange,loadMore"
                    role="status"
                    aria-label="{{ __('chat.header.updating_messages') }}"
                >
                    <span class="sr-only">{{ __('chat.header.updating_messages') }}</span>
                </div>

                <button
                    type="button"
                    class="new-messages"
                    data-new-messages
                    hidden
                    aria-label="{{ __('chat.messages.jump_new') }}"
                >
                    <span data-new-messages-label>{{ __('chat.messages.new_messages') }}</span>
                    <x-icon name="arrow-down" :size="16" />
                </button>
            </div>

            @error('chat')
                <div class="chat__notice alert alert--error" role="alert">
                    <x-icon name="alert" :size="18" />
                    <span>{{ $message }}</span>
                </div>
            @enderror

            @error('reaction')
                <div class="chat__notice alert alert--error" role="alert">
                    <x-icon name="alert" :size="18" />
                    <span>{{ $message }}</span>
                </div>
            @enderror

            <p class="chat__preview-note" title="{{ __('chat.messages.preview_note_title') }}">
                <x-icon name="info" :size="13" />
                {{ __('chat.messages.preview_note') }}
            </p>

            <div class="copy-toast" id="copy-toast" hidden>
                {{ __('chat.js.message_copied') }}
            </div>

            <form class="composer" wire:submit="send" data-composer>
                <x-chat.reply-draft :replying-to="$replyingTo" />

                <div class="composer__field">
                    <label for="new-message" class="sr-only">{{ __('chat.composer.label') }}</label>
                    <textarea
                        id="new-message"
                        class="textarea composer__textarea"
                        rows="1"
                        maxlength="2000"
                        placeholder="{{ __('chat.composer.placeholder') }}"
                        wire:model="newMessage"
                        data-autoresize
                        dir="auto"
                        enterkeyhint="send"
                        aria-describedby="composer-hint"
                        wire:keydown.escape="cancelReply"
                        required
                    ></textarea>

                    <div class="composer__toolbar">
                        <span class="composer__count" data-char-count hidden aria-live="polite"></span>
                        <button
                            type="submit"
                            class="button button--primary composer__send"
                            data-send-button
                            aria-label="{{ __('chat.composer.send_label') }}"
                            @disabled(trim($newMessage) === '')
                            wire:loading.attr="disabled"
                            wire:target="send"
                        >
                            <span wire:loading.remove wire:target="send" class="composer__send-content">
                                <x-icon name="send" :size="16" />
                                <span>{{ __('chat.composer.send') }}</span>
                            </span>
                            <span wire:loading wire:target="send" class="button__loading">
                                <span class="spinner" aria-hidden="true"></span>
                                {{ __('chat.composer.sending') }}
                            </span>
                        </button>
                    </div>

                    @error('newMessage')
                        <p class="field-error composer__error" role="alert">{{ $message }}</p>
                    @enderror

                    @if ($sendFailed)
                        <button
                            type="button"
                            class="composer__retry"
                            data-send-retry
                            wire:click="retrySend"
                            wire:loading.attr="disabled"
                            wire:target="retrySend"
                            aria-label="{{ __('chat.composer.retry') }}"
                            title="{{ __('chat.js.send_failed_retry') }}"
                        >
                            <x-icon name="refresh" :size="14" />
                            <span>{{ __('chat.composer.retry') }}</span>
                        </button>
                    @endif
                </div>

                <div class="composer__footer">
                    <p class="composer__hint" id="composer-hint">
                        <kbd>Enter</kbd> {{ __('chat.composer.hint_send') }} <span aria-hidden="true">·</span>
                        <kbd>Shift</kbd> + <kbd>Enter</kbd> {{ __('chat.composer.hint_newline') }}
                    </p>
                    <span class="composer__limit">{{ __('chat.composer.limit') }}</span>
                </div>
            </form>
        </section>
    </div>

    <dialog
        class="dialog"
        x-ref="deleteDialog"
        aria-labelledby="delete-title"
        aria-describedby="delete-description"
        @close="deleteId = null; deletePreview = ''; deleting = false"
        @cancel.prevent="! deleting && $refs.deleteDialog.close()"
        @click.self="! deleting && $refs.deleteDialog.close()"
    >
        <div class="dialog__panel">
            <span class="dialog__icon" aria-hidden="true">
                <x-icon name="trash" :size="22" />
            </span>
            <h2 class="dialog__title" id="delete-title">{{ __('chat.dialog.delete_title') }}</h2>
            <p class="dialog__text" id="delete-description">
                {{ __('chat.dialog.delete_text') }}
            </p>
            <p class="dialog__preview" x-text="deletePreview"></p>

            <div class="dialog__actions">
                <button
                    type="button"
                    class="button button--ghost"
                    @click="$refs.deleteDialog.close()"
                    :disabled="deleting"
                >
                    {{ __('chat.dialog.cancel') }}
                </button>
                <button
                    type="button"
                    class="button button--danger"
                    @click="deleting = true; $wire.delete(deleteId).then(() => { $refs.deleteDialog.close(); }).catch(() => {}).finally(() => { deleting = false; })"
                    :disabled="deleting"
                >
                    <span x-show="! deleting">{{ __('chat.dialog.delete') }}</span>
                    <span x-show="deleting" class="button__loading">
                        <span class="spinner" aria-hidden="true"></span>
                        {{ __('chat.dialog.deleting') }}
                    </span>
                </button>
            </div>
        </div>
    </dialog>
</div>
