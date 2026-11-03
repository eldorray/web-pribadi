/**
 * Chat UI behaviour: composer, code copy buttons, auto-scroll, stop button.
 *
 * Everything here is delegated or re-initialised on `livewire:navigated` so it
 * keeps working after a wire:navigate page swap.
 */

const CHAT_COMPONENT = 'chat.page';

let chatController = null;
let chatInFlight = false;

function isChatRequest(options) {
    try {
        const body = JSON.parse(options?.body ?? '{}');
        return (body.components ?? []).some(
            (component) => component.snapshot?.memo?.name === CHAT_COMPONENT
        );
    } catch (_) {
        return false;
    }
}

function registerChatRequestHooks() {
    if (!window.Livewire) return;

    window.Livewire.hook('request', ({ options }) => {
        if (!isChatRequest(options)) return;

        chatController = new AbortController();
        chatInFlight = true;
        options.signal = chatController.signal;
    });

    window.Livewire.hook('commit', ({ respond }) => {
        respond(() => {
            chatInFlight = false;
            chatController = null;
        });
    });
}

/** Stop = abort the in-flight request; the server saves what it already sent. */
function stopStream() {
    if (chatController) {
        chatController.abort();
        chatController = null;
    }

    chatInFlight = false;
}

function autoGrow(textarea) {
    textarea.style.height = 'auto';
    textarea.style.height = Math.min(textarea.scrollHeight, 200) + 'px';
}

function setupComposer(root = document) {
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

    root.querySelectorAll('.chat-send').forEach((button) => {
        if (button.dataset.chatReady === '1') return;
        button.dataset.chatReady = '1';

        button.addEventListener('click', (event) => {
            if (!chatInFlight) return;

            event.preventDefault();
            event.stopPropagation();
            stopStream();
        });
    });
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

function setupThreadObserver() {
    const thread = document.querySelector('[data-chat-scroll]');
    if (!thread || thread.dataset.chatObserved === '1') return;
    thread.dataset.chatObserved = '1';

    const observer = new MutationObserver(() => {
        scrollThreadToBottom();
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
