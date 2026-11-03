@php
    $messages = $conversation?->messages ?? collect();
@endphp

<div class="chat-app" x-data="{ drawer: false }" @keydown.escape.window="drawer = false">
    {{-- ── Sidebar ───────────────────────────────────────────────────────── --}}
    <div class="chat-drawer-scrim" x-show="drawer" x-transition.opacity @click="drawer = false"
        style="display: none;"></div>

    <aside class="chat-sidebar" :class="drawer ? 'is-open' : ''">
        <div class="chat-sidebar__top">
            <a href="{{ route('chat') }}" wire:navigate class="chat-brand">
                <x-icon name="auto_awesome" :size="18" />
                <span>Chat</span>
            </a>
            <button type="button" class="chat-icon-btn chat-only-mobile" @click="drawer = false"
                aria-label="Tutup daftar">
                <x-icon name="close" :size="18" />
            </button>
        </div>

        <button type="button" wire:click="newChat" class="chat-new">
            <x-icon name="add" :size="18" />
            Chat baru
        </button>

        <nav class="chat-conversations" aria-label="Daftar percakapan">
            @forelse ($conversations as $item)
                <div class="chat-conversation" x-data="{ renaming: false, title: @js($item->title) }"
                    wire:key="conversation-{{ $item->id }}"
                    :class="{ 'is-active': {{ $conversation?->id === $item->id ? 'true' : 'false' }} }">
                    <a href="{{ route('chat', ['conversation' => $item->id]) }}" wire:navigate
                        class="chat-conversation__link" x-show="!renaming">
                        <span class="chat-conversation__title">{{ $item->title }}</span>
                    </a>

                    <form x-show="renaming" x-cloak @submit.prevent="$wire.rename({{ $item->id }}, title); renaming = false"
                        class="chat-conversation__rename">
                        <input type="text" x-model="title" maxlength="50" aria-label="Judul percakapan"
                            @keydown.escape="renaming = false" />
                    </form>

                    <div class="chat-conversation__actions">
                        <button type="button" @click="renaming = !renaming" title="Ganti nama"
                            aria-label="Ganti nama percakapan">
                            <x-icon name="edit" :size="14" />
                        </button>
                        <button type="button" wire:click="deleteConversation({{ $item->id }})"
                            wire:confirm="Hapus percakapan ini?" title="Hapus" aria-label="Hapus percakapan">
                            <x-icon name="delete" :size="14" />
                        </button>
                    </div>
                </div>
            @empty
                <p class="chat-conversations__empty">Belum ada percakapan.</p>
            @endforelse
        </nav>

        <div class="chat-sidebar__footer">
            <div class="chat-user">
                <span class="chat-user__avatar">{{ strtoupper(mb_substr($chatUser->name, 0, 1)) }}</span>
                <span class="chat-user__name">{{ $chatUser->name }}</span>
            </div>
            <form method="POST" action="{{ route('chat.logout') }}">
                @csrf
                <button type="submit" class="chat-icon-btn" title="Keluar" aria-label="Keluar">
                    <x-icon name="logout" :size="18" />
                </button>
            </form>
        </div>
    </aside>

    {{-- ── Main column ───────────────────────────────────────────────────── --}}
    <div class="chat-main">
        <header class="chat-topbar">
            <button type="button" class="chat-icon-btn chat-only-mobile" @click="drawer = true"
                aria-label="Buka daftar percakapan">
                <x-icon name="menu" :size="20" />
            </button>
            <p class="chat-topbar__title">{{ $conversation?->title ?? 'Chat baru' }}</p>
            <span class="chat-topbar__model">{{ $imageMode ? 'Gambar' : ($modelId ?: 'model') }}</span>
        </header>

        <div class="chat-scroll" id="chat-thread" data-chat-scroll>
            @if ($messages->isEmpty())
                <div class="chat-empty">
                    <div class="chat-empty__mark" aria-hidden="true">
                        <x-icon name="auto_awesome" :size="26" />
                    </div>
                    <h1 class="chat-empty__title">Halo, {{ $chatUser->name }}.</h1>
                    <p class="chat-empty__lede">Mau menulis apa hari ini?</p>
                </div>
            @else
                <div class="chat-thread">
                    @foreach ($messages as $message)
                        @include('livewire.chat.partials.message', ['message' => $message])
                    @endforeach
                </div>
            @endif

            {{-- Livewire streams answer fragments into this element. --}}
            <div id="chat-stream" class="chat-stream" aria-live="polite"></div>

            <div class="chat-thinking" wire:loading.delay.short wire:target="send,retry">
                <span class="chat-thinking__dots"><span></span><span></span><span></span></span>
                Sedang berpikir…
            </div>
        </div>

        {{-- ── Composer ─────────────────────────────────────────────────── --}}
        <div class="chat-composer-wrap">
            <form wire:submit="send" class="chat-composer">
                @if ($uploads !== [])
                    <div class="chat-chips">
                        @foreach ($uploads as $index => $upload)
                            <span class="chat-chip" wire:key="upload-{{ $index }}">
                                <span class="chat-chip__name">{{ $upload->getClientOriginalName() }}</span>
                                <button type="button" wire:click="removeUpload({{ $index }})"
                                    aria-label="Hapus lampiran">
                                    <x-icon name="close" :size="12" />
                                </button>
                            </span>
                        @endforeach
                    </div>
                @endif

                <textarea wire:model="prompt" data-chat-input rows="1" placeholder="Tulis pesan…" maxlength="20000"
                    aria-label="Pesan" class="chat-input"></textarea>

                <div class="chat-toolbar">
                    <div class="chat-toolbar__left">
                        <label class="chat-icon-btn" title="Lampirkan berkas"
                            @class(['is-disabled' => $imageMode || !$supportsVision])>
                            <x-icon name="attach_file" :size="18" />
                            <input type="file" wire:model="uploads" multiple class="sr-only"
                                accept=".jpg,.jpeg,.png,.webp,.pdf,.docx"
                                @disabled($imageMode || !$supportsVision) />
                        </label>

                        <div class="chat-select-wrap">
                            <select wire:model.live="modelId" class="chat-select" aria-label="Model">
                                @forelse ($models as $model)
                                    <option value="{{ $model->model_id }}">{{ $model->label }}</option>
                                @empty
                                    <option value="">Belum ada model</option>
                                @endforelse
                            </select>
                            <x-icon name="expand_more" :size="16" class="chat-select__caret" />
                        </div>

                        @if ($supportsEffort && !$imageMode)
                            <div class="chat-select-wrap">
                                <select wire:model="effort" class="chat-select" aria-label="Kedalaman">
                                    @foreach ($efforts as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <x-icon name="expand_more" :size="16" class="chat-select__caret" />
                            </div>
                        @endif

                        <button type="button" wire:click="toggleImageMode"
                            class="chat-mode-btn @if ($imageMode) is-active @endif"
                            title="Buat gambar">
                            <x-icon name="image" :size="16" />
                            <span class="chat-mode-btn__label">Gambar</span>
                        </button>
                    </div>

                    <button type="submit" class="chat-send" wire:loading.attr="disabled"
                        wire:target="send,retry" aria-label="Kirim">
                        <x-icon name="arrow_upward" :size="18" wire:loading.remove wire:target="send,retry" />
                        <x-icon name="stop" :size="18" wire:loading wire:target="send,retry" />
                    </button>
                </div>
            </form>

            @error('prompt')
                <p class="chat-composer__error">{{ $message }}</p>
            @enderror
            @error('uploads')
                <p class="chat-composer__error">{{ $message }}</p>
            @enderror
            @error('uploads.*')
                <p class="chat-composer__error">{{ $message }}</p>
            @enderror

            @if ($models->isEmpty())
                <p class="chat-composer__hint">
                    Belum ada model aktif. Buka <strong>admin → Chat AI</strong>, lalu centang
                    <strong>Aktif</strong> pada model yang ingin dipakai.
                </p>
            @endif
        </div>
    </div>
</div>
