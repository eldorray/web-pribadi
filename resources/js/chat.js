/**
 * Chat UI behaviour: composer, code copy buttons, auto-scroll, stop button.
 *
 * Everything here is delegated or re-initialised on `livewire:navigated` so it
 * keeps working after a wire:navigate page swap.
 */

const CHAT_COMPONENT = 'chat.page';

let chatInFlight = false;
let pendingSubmission = null;
let activeSubmission = null;

function chatRequestDetails(options) {
    try {
        const body = typeof options?.body === 'string'
            ? JSON.parse(options.body)
            : (options?.body ?? {});
        const components = body.components ?? [];
        const chatComponents = components.filter((component) => {
            try {
                const snapshot = typeof component.snapshot === 'string'
                    ? JSON.parse(component.snapshot)
                    : component.snapshot;

                return snapshot?.memo?.name === CHAT_COMPONENT;
            } catch (_) {
                return false;
            }
        });

        return {
            isChat: chatComponents.length > 0,
            methods: chatComponents.flatMap((component) =>
                (component.calls ?? []).map((call) => call.method)
            ),
        };
    } catch (_) {
        return { isChat: false, methods: [] };
    }
}

function isGeneratingRequest(details) {
    return details.methods.some((method) => method === 'send' || method === 'retry');
}

function setChatBusy(busy) {
    document.querySelectorAll('[data-chat-form]').forEach((form) => {
        form.classList.toggle('is-busy', busy);
        form.setAttribute('aria-busy', busy ? 'true' : 'false');
    });

    // Image mode has no stop button, so its send button stays put, disabled.
    const canStop = document.querySelector('[data-chat-stop]') !== null;

    document.querySelectorAll('[data-chat-send]').forEach((button) => {
        button.hidden = busy && canStop;
        button.disabled = busy;
    });

    document.querySelectorAll('[data-chat-stop]').forEach((button) => {
        button.hidden = !busy;
        if (!busy) button.disabled = false;
    });
}

function restoreSubmission(submission) {
    if (!submission) return;

    const textarea = document.querySelector('[data-chat-input]');
    if (!(textarea instanceof HTMLTextAreaElement)) return;
    if (textarea.value.trim() !== '') return;

    textarea.value = submission.text;
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
    autoGrow(textarea);
    textarea.focus();
}

function submissionHasError(effects) {
    try {
        const snapshot = JSON.parse(effects?.snapshot ?? '{}');
        const errors = snapshot?.memo?.errors ?? {};

        return Object.keys(errors).some((key) =>
            key === 'prompt' || key === 'uploads' || key.startsWith('uploads.')
        );
    } catch (_) {
        return false;
    }
}

function registerChatRequestHooks() {
    if (!window.Livewire) return;

    window.Livewire.hook('request', ({ options, fail }) => {
        const details = chatRequestDetails(options);
        if (!details.isChat) return;

        if (isGeneratingRequest(details)) {
            chatInFlight = true;
            setChatBusy(true);
        }

        if (details.methods.includes('send') && pendingSubmission) {
            activeSubmission = pendingSubmission;
            pendingSubmission = null;
        }

        if (isGeneratingRequest(details)) {
            fail(() => {
                finishSubmission(false);
                chatInFlight = false;
                setChatBusy(false);
            });
        }
    });

    window.Livewire.hook('commit', ({ component, commit, respond, succeed, fail }) => {
        if (component?.name !== CHAT_COMPONENT) return;

        const methods = (commit?.calls ?? []).map((call) => call.method);
        if (!methods.some((method) => method === 'send' || method === 'retry')) return;

        succeed(({ effects }) => finishSubmission(!submissionHasError(effects)));
        fail(() => finishSubmission(false));

        respond(() => {
            chatInFlight = false;
            setChatBusy(false);
        });
    });
}

/**
 * Stop = ask the server to end the answer. The Livewire request is left to
 * finish, because aborting it would strand the page on its pre-send snapshot.
 */
async function stopStream(button) {
    button.disabled = true;

    try {
        const response = await fetch(button.dataset.stopUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
        });

        if (!response.ok) button.disabled = false;
    } catch (_) {
        button.disabled = false;
    }
}

function autoGrow(textarea) {
    textarea.style.height = 'auto';
    textarea.style.height = Math.min(textarea.scrollHeight, 200) + 'px';
}

function removeSubmission(submission) {
    submission?.bubble?.remove();
    document.querySelector('[data-chat-scroll]')?.classList.remove('has-pending-message');
}

function finishSubmission(succeeded) {
    const submission = activeSubmission;
    if (!submission) return;

    if (!succeeded || document.querySelector('.chat-composer__error')) {
        restoreSubmission(submission);
    }

    removeSubmission(submission);
    if (pendingSubmission === submission) pendingSubmission = null;
    activeSubmission = null;
}

function stageSubmission(textarea, animate = true) {
    const text = textarea.value.trim();
    const slot = document.querySelector('[data-chat-pending]');
    const scroller = document.querySelector('[data-chat-scroll]');

    if (text === '' || !slot || !scroller) return;

    pendingSubmission?.bubble?.remove();

    const source = textarea.getBoundingClientRect();
    const bubble = document.createElement('div');
    bubble.className = 'chat-pending-message';
    bubble.setAttribute('role', 'status');
    bubble.setAttribute('aria-label', 'Pesan dikirim');
    bubble.textContent = text;
    slot.appendChild(bubble);
    scroller.classList.add('has-pending-message');
    scrollThreadToBottom(true);

    if (animate) {
        const target = bubble.getBoundingClientRect();
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const animation = bubble.animate(
            reduceMotion
                ? [
                    { opacity: 0.35 },
                    { opacity: 1 },
                ]
                : [
                    {
                        opacity: 0.2,
                        transform: `translate3d(${source.right - target.right}px, ${source.top - target.top}px, 0) scale(0.96)`,
                    },
                    {
                        opacity: 1,
                        transform: 'translate3d(0, 0, 0) scale(1)',
                    },
                ],
            {
                duration: reduceMotion ? 140 : 260,
                easing: 'cubic-bezier(0.23, 1, 0.32, 1)',
                fill: 'both',
            }
        );

        animation.finished.finally(() => {
            bubble.getAnimations().forEach((item) => item.cancel());
        });
    }

    pendingSubmission = { text, bubble };
    activeSubmission = pendingSubmission;
    // Keep Livewire's deferred model intact for this request; only clear the
    // visible control. The server response will sync the actual model to ''.
    textarea.value = '';
    autoGrow(textarea);
}

function setupComposer(root = document) {
    root.querySelectorAll('[data-chat-form]').forEach((form) => {
        if (form.dataset.chatSubmitReady === '1') return;
        form.dataset.chatSubmitReady = '1';

        form.addEventListener('submit', (event) => {
            const textarea = form.querySelector('[data-chat-input]');
            if (!(textarea instanceof HTMLTextAreaElement)) return;

            const pointerInitiated = event.submitter instanceof HTMLElement;

            // Livewire reads deferred input changes in a microtask immediately
            // after submit. Stage the visual copy after that payload is queued.
            queueMicrotask(() => stageSubmission(textarea, pointerInitiated));
        }, { capture: true });
    });

    root.querySelectorAll('[data-chat-input]').forEach((textarea) => {
        if (textarea.dataset.chatReady === '1') return;
        textarea.dataset.chatReady = '1';

        autoGrow(textarea);
        textarea.addEventListener('input', () => autoGrow(textarea));

        textarea.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' || event.shiftKey || event.isComposing) return;

            event.preventDefault();
            textarea.closest('form')?.requestSubmit();
        });
    });

    root.querySelectorAll('[data-chat-stop]').forEach((button) => {
        if (button.dataset.chatReady === '1') return;
        button.dataset.chatReady = '1';

        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            stopStream(button);
        });
    });

    setChatBusy(chatInFlight);
}

function setupCopyButtons(root = document) {
    root.querySelectorAll('.chat-markdown pre > code').forEach((code) => {
        if (code.dataset.chatReady === '1') return;
        code.dataset.chatReady = '1';

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'chat-copy';
        button.textContent = 'Salin';

        button.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(code.textContent ?? '');
                button.textContent = 'Tersalin';
            } catch (_) {
                button.textContent = 'Gagal';
            }

            setTimeout(() => {
                button.textContent = 'Salin';
            }, 1600);
        });

        code.parentElement?.appendChild(button);
    });
}

function scrollThreadToBottom(force = false) {
    const scroller = document.querySelector('[data-chat-scroll]');
    if (!scroller) return;

    const nearBottom = scroller.scrollHeight - scroller.scrollTop - scroller.clientHeight < 160;
    if (force || nearBottom) scroller.scrollTop = scroller.scrollHeight;
}

function reconcilePendingSubmission() {
    const submission = activeSubmission ?? pendingSubmission;
    if (!submission) return;

    const savedMessages = Array.from(document.querySelectorAll('.chat-bubble__text'));
    const saved = savedMessages.some((message) => message.textContent?.trim() === submission.text);

    if (saved) finishSubmission(true);
}

function setupThreadObserver() {
    const thread = document.querySelector('[data-chat-scroll]');
    if (!thread || thread.dataset.chatObserved === '1') return;
    thread.dataset.chatObserved = '1';

    const observer = new MutationObserver(() => {
        scrollThreadToBottom();
        reconcilePendingSubmission();
        // A finished answer arrives by morph, not by page load.
        setupCopyButtons(thread);
    });
    observer.observe(thread, { childList: true, subtree: true, characterData: true });

    scrollThreadToBottom(true);
}

/** Keep the URL in step with the conversation that was just created. */
function registerConversationListener() {
    if (window.__chatConversationListener) return;
    window.__chatConversationListener = true;

    window.addEventListener('chat-conversation', (event) => {
        const id = event.detail?.id;
        if (!id) return;

        const url = new URL(window.location.href);
        url.pathname = `/chat/${id}`;
        window.history.replaceState({}, '', url.toString());
    });

    window.addEventListener('chat-focus-composer', () => {
        requestAnimationFrame(() => {
            const textarea = document.querySelector('[data-chat-input]');
            if (!(textarea instanceof HTMLTextAreaElement)) return;
            autoGrow(textarea);
            textarea.focus();
            textarea.setSelectionRange(textarea.value.length, textarea.value.length);
        });
    });
}

function initChat() {
    setupComposer();
    setupCopyButtons();
    setupThreadObserver();
    registerConversationListener();
}

document.addEventListener('livewire:init', registerChatRequestHooks);
document.addEventListener('DOMContentLoaded', initChat);
document.addEventListener('livewire:navigated', initChat);
