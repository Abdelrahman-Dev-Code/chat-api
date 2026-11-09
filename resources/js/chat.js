import { announce, toast } from './toast';
import { t } from './i18n';

const MAX_LENGTH = 2000;
const NEAR_LIMIT = 1700;
const PIN_THRESHOLD = 120;

const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const finePointer = () => window.matchMedia('(hover: hover) and (pointer: fine)').matches;

let booted = false;
let transcriptObserver = null;
let transcriptList = null;
let scrollListener = null;
let prependAnchor = null;
let anchorTimer = null;
let pinnedToBottom = true;
let newMessageCount = 0;
let scrollFrame = null;

const messageList = () => document.getElementById('message-list');

const clearPendingMessage = () => {
    const list = messageList();
    const pending = document.getElementById('pending-messages');

    pending?.replaceChildren();
    list?.removeAttribute('data-has-pending');
};

const showPendingMessage = (content) => {
    const list = messageList();
    const container = document.getElementById('pending-messages');

    if (!list || !container) {
        return;
    }

    const message = document.createElement('article');
    message.className = 'message message--own message--pending';
    message.setAttribute('aria-label', t('composer.sending'));

    const body = document.createElement('div');
    body.className = 'message__body';

    const bubble = document.createElement('div');
    bubble.className = 'message__bubble';
    bubble.dir = 'auto';
    bubble.textContent = content;

    const foot = document.createElement('div');
    foot.className = 'message__foot';

    const status = document.createElement('span');
    status.className = 'message__details';
    status.textContent = t('composer.sending');
    status.setAttribute('role', 'status');

    foot.append(status);
    body.append(bubble, foot);
    message.append(body);
    container.replaceChildren(message);
    list.dataset.hasPending = 'true';
    pinnedToBottom = true;
    setNewMessageCount(0);
    window.requestAnimationFrame(() => scrollToLatest('auto'));
};

const isNearBottom = (list) => list.scrollHeight - list.scrollTop - list.clientHeight < PIN_THRESHOLD;

const scrollToLatest = (behavior = 'auto') => {
    const list = messageList();

    if (!list) {
        return;
    }

    list.scrollTo({
        top: list.scrollHeight,
        behavior: reducedMotion() ? 'auto' : behavior,
    });
};

const focusComposer = () => {
    const input = document.getElementById('new-message');

    if (!input) {
        return;
    }

    input.focus({ preventScroll: true });

    const length = input.value.length;

    input.setSelectionRange(length, length);
    input.scrollIntoView({ block: 'nearest' });
};

const highlightMessage = (messageId) => {
    const target = document.querySelector(`[data-message-id="${Number(messageId)}"]`);

    if (!target) {
        return false;
    }

    target.scrollIntoView({
        behavior: reducedMotion() ? 'auto' : 'smooth',
        block: 'center',
    });

    target.classList.remove('is-highlighted');
    // Force a reflow so the highlight can replay on repeated jumps.
    void target.offsetWidth;
    target.classList.add('is-highlighted');

    window.setTimeout(() => {
        target.classList.remove('is-highlighted');
    }, 1900);

    target.focus({ preventScroll: true });

    return true;
};

const scheduleScroll = () => {
    if (scrollFrame !== null) {
        window.cancelAnimationFrame(scrollFrame);
    }

    scrollFrame = window.requestAnimationFrame(() => {
        scrollFrame = null;

        if (pinnedToBottom) {
            scrollToLatest('auto');
        }
    });
};

const resize = (input) => {
    if (!input.matches('[data-autoresize]')) {
        return;
    }

    input.style.height = 'auto';

    if (input.value === '') {
        input.style.height = '';

        return;
    }

    const maxHeight = Number.parseInt(window.getComputedStyle(input).maxHeight, 10);
    const height = Number.isFinite(maxHeight)
        ? Math.min(input.scrollHeight, maxHeight)
        : input.scrollHeight;

    input.style.height = `${height}px`;
};

const updateInput = (input) => {
    resize(input);

    const composer = input.closest('[data-composer]');

    if (composer) {
        const count = composer.querySelector('[data-char-count]');
        const button = composer.querySelector('[data-send-button]');
        const length = input.value.length;

        if (count) {
            count.hidden = length < NEAR_LIMIT;
            count.textContent = `${length} / ${MAX_LENGTH}`;
            count.classList.toggle('is-near-limit', length >= NEAR_LIMIT);
            count.classList.toggle('is-at-limit', length >= MAX_LENGTH);
        }

        if (button) {
            button.disabled = composer.dataset.submitting === 'true' || input.value.trim().length === 0;
        }
    }

    const editForm = input.closest('.edit-form');

    if (editForm) {
        const save = editForm.querySelector('[data-edit-submit]');

        if (save) {
            save.disabled = input.value.trim().length === 0;
        }
    }
};

const updateAllInputs = () => {
    document.querySelectorAll('[data-autoresize]').forEach(updateInput);
};

const updateInputsWithin = (node) => {
    if (!(node instanceof Element)) {
        return;
    }

    if (node.matches('[data-autoresize]')) {
        updateInput(node);
    }

    node.querySelectorAll('[data-autoresize]').forEach(updateInput);
};

const resetComposerBusy = () => {
    document.querySelectorAll('[data-composer]').forEach((form) => {
        delete form.dataset.submitting;
        form.removeAttribute('aria-busy');

        const input = form.querySelector('#new-message');

        if (input) {
            updateInput(input);
        }

        const retry = form.querySelector('[data-send-retry]');

        if (retry) {
            retry.disabled = false;
        }
    });
};

const setNewMessageCount = (count, shouldAnnounce = false) => {
    newMessageCount = Math.max(0, count);

    const button = document.querySelector('[data-new-messages]');
    const label = button?.querySelector('[data-new-messages-label]');

    if (!button || !label) {
        return;
    }

    if (newMessageCount === 0) {
        button.hidden = true;

        return;
    }

    const text = newMessageCount === 1
        ? t('js.new_message_one')
        : t('js.new_message_many', { count: newMessageCount });

    label.textContent = text;
    button.setAttribute('aria-label', t('js.jump_to', { label: text }));
    button.hidden = false;

    if (shouldAnnounce) {
        announce(t('js.new_available', { label: text }));
    }
};

const teardownTranscript = () => {
    transcriptObserver?.disconnect();
    transcriptObserver = null;

    if (transcriptList && scrollListener) {
        transcriptList.removeEventListener('scroll', scrollListener);
    }

    transcriptList = null;
    scrollListener = null;
    prependAnchor = null;

    if (anchorTimer) {
        window.clearTimeout(anchorTimer);
        anchorTimer = null;
    }
};

const observeTranscript = () => {
    teardownTranscript();

    const list = messageList();

    if (!list) {
        return;
    }

    transcriptList = list;
    pinnedToBottom = true;

    scrollListener = () => {
        pinnedToBottom = isNearBottom(list);

        if (pinnedToBottom) {
            setNewMessageCount(0);
        }
    };

    list.addEventListener('scroll', scrollListener, { passive: true });

    transcriptObserver = new MutationObserver((mutations) => {
        const current = messageList();

        if (!current) {
            return;
        }

        if (prependAnchor !== null) {
            current.scrollTop = prependAnchor.top + (current.scrollHeight - prependAnchor.height);
            prependAnchor = null;

            if (anchorTimer) {
                window.clearTimeout(anchorTimer);
                anchorTimer = null;
            }
        } else if (pinnedToBottom) {
            scheduleScroll();
        }

        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach(updateInputsWithin);
        });
    });

    transcriptObserver.observe(list, { childList: true, subtree: true });

    scrollToLatest();
    updateAllInputs();
};

const initPage = () => {
    setNewMessageCount(0);
    pinnedToBottom = true;
    observeTranscript();
};

const clearComposer = () => {
    const form = document.querySelector('[data-composer]');
    const input = form?.querySelector('#new-message');

    if (!form || !input) {
        return;
    }

    input.value = '';
    input.style.height = '';
    resetComposerBusy();
    updateInput(input);

    if (finePointer()) {
        input.focus();
    }
};

const registerLivewireEvents = () => {
    window.Livewire.on('message-sent', () => {
        clearPendingMessage();
        setNewMessageCount(0);
        pinnedToBottom = true;
        scrollToLatest('smooth');

        window.requestAnimationFrame(clearComposer);

        toast(t('js.message_sent'), 'success', { duration: 2200 });
        announce(t('js.message_sent_announce'));
    });

    window.Livewire.on('message-send-failed', () => {
        clearPendingMessage();
        toast(t('js.send_failed_retry'), 'error', { duration: 5600 });
    });

    window.Livewire.on('message-updated', () => {
        if (pinnedToBottom) {
            scheduleScroll();
        }

        toast(t('js.message_updated'), 'success', { duration: 2200 });
        announce(t('js.message_updated_announce'));
    });

    window.Livewire.on('message-deleted', () => {
        if (pinnedToBottom) {
            scheduleScroll();
        }

        toast(t('js.message_deleted'), 'success', { duration: 2600 });
        announce(t('js.message_deleted_announce'));
    });

    window.Livewire.on('message-received', () => {
        if (pinnedToBottom) {
            scrollToLatest('smooth');

            return;
        }

        setNewMessageCount(newMessageCount + 1, true);
    });

    window.Livewire.on('transcript-reset', () => {
        setNewMessageCount(0);
        pinnedToBottom = true;

        window.requestAnimationFrame(() => {
            scrollToLatest('smooth');
        });
    });

    window.Livewire.on('reply-started', () => {
        window.requestAnimationFrame(focusComposer);
        announce(t('js.reply_opened'));
    });

    window.Livewire.on('scroll-to-message', (payload = {}) => {
        const messageId = payload.messageId ?? payload.id;

        if (highlightMessage(messageId)) {
            announce(t('js.jumped_original'));
        }
    });

    window.Livewire.on('reply-target-unavailable', (payload = {}) => {
        const message = payload.message ?? t('js.reply_unavailable');

        toast(message, 'info', { duration: 5600 });
        announce(message);
    });

    window.Livewire.on('reaction-preview-updated', () => {
        announce(t('js.reaction_updated'));
    });

    window.Livewire.hook('request', ({ respond, fail }) => {
        respond(() => resetComposerBusy());

        fail(({ status } = {}) => {
            prependAnchor = null;
            clearPendingMessage();
            resetComposerBusy();

            if (status === 419) {
                toast(t('js.session_expired'), 'error', { duration: 8000 });

                return;
            }

            toast(t('js.request_failed'), 'error', { duration: 5600 });
        });
    });
};

const initChat = () => {
    if (booted) {
        return;
    }

    booted = true;

    document.addEventListener('input', (event) => {
        const input = event.target.closest('[data-autoresize]');

        if (input) {
            updateInput(input);
        }
    });

    document.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            const search = document.getElementById('message-search');

            if (search) {
                event.preventDefault();
                search.focus({ preventScroll: true });
                search.select();
                announce(t('js.search_focused'));
            }

            return;
        }

        const composer = event.target.closest('#new-message');

        if (!composer || event.key !== 'Enter' || event.shiftKey || event.isComposing) {
            return;
        }

        const form = composer.closest('[data-composer]');

        if (!form) {
            return;
        }

        event.preventDefault();

        if (composer.value.trim() === '' || form.dataset.submitting === 'true') {
            return;
        }

        form.requestSubmit();
    });

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('[data-composer]');

        if (!form) {
            return;
        }

        const input = form.querySelector('#new-message');

        if (!input) {
            return;
        }

        if (form.dataset.submitting === 'true' || input.value.trim() === '') {
            event.preventDefault();
            event.stopPropagation();

            if (input.value.trim() === '') {
                input.focus();
                announce(t('js.write_first'));
            }

            return;
        }

        form.dataset.submitting = 'true';
        form.setAttribute('aria-busy', 'true');
        showPendingMessage(input.value.trim());
    }, true);

    document.addEventListener('click', (event) => {
        const retrySend = event.target.closest('[data-send-retry]');

        if (retrySend) {
            const form = retrySend.closest('[data-composer]');
            const input = form?.querySelector('#new-message');

            if (form && input && form.dataset.submitting !== 'true') {
                form.dataset.submitting = 'true';
                form.setAttribute('aria-busy', 'true');
                retrySend.disabled = true;
                showPendingMessage(input.value.trim());
            }

            return;
        }

        const focusComposer = event.target.closest('[data-focus-composer]');

        if (focusComposer) {
            document.getElementById('new-message')?.focus();

            return;
        }

        const copyButton = event.target.closest('[data-copy-button]');

        if (copyButton) {
            const content = copyButton.dataset.messageContent;

            if (content === undefined || content === null) {
                return;
            }

            navigator.clipboard
                .writeText(content)
                .then(() => {
                    copyButton.classList.add('copied');
                    copyButton.setAttribute('aria-label', t('js.message_copied'));

                    const toast = document.getElementById('copy-toast');

                    if (toast) {
                        toast.hidden = false;
                        toast.classList.add('show');

                        window.setTimeout(() => {
                            toast.classList.remove('show');
                            window.setTimeout(() => {
                                toast.hidden = true;
                            }, 180);
                        }, 1400);
                    }

                    window.setTimeout(() => {
                        copyButton.classList.remove('copied');
                        copyButton.setAttribute('aria-label', t('js.copy_message'));
                    }, 1400);

                    announce(t('js.message_copied'));
                })
                .catch(() => {
                    toast(t('js.request_failed'), 'error', { duration: 5600 });
                });

            return;
        }

        const newMessages = event.target.closest('[data-new-messages]');

        if (newMessages) {
            const list = messageList();

            pinnedToBottom = true;
            setNewMessageCount(0);
            scrollToLatest('smooth');
            list?.focus({ preventScroll: true });
            announce(t('js.jumped_latest'));

            return;
        }

        const loadMore = event.target.closest('[data-load-more]');
        const list = messageList();

        if (loadMore && list) {
            prependAnchor = {
                height: list.scrollHeight,
                top: list.scrollTop,
            };

            if (anchorTimer) {
                window.clearTimeout(anchorTimer);
            }

            anchorTimer = window.setTimeout(() => {
                prependAnchor = null;
                anchorTimer = null;
            }, 6000);
        }
    });

    // Keep the latest messages visible when the mobile keyboard opens/closes.
    window.visualViewport?.addEventListener('resize', () => {
        if (pinnedToBottom) {
            scheduleScroll();
        }
    });

    document.addEventListener('livewire:navigating', teardownTranscript);
    document.addEventListener('livewire:navigated', initPage);

    registerLivewireEvents();
    initPage();
};

export const bootChat = () => initChat();
