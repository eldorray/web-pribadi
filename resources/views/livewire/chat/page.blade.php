@php
    $messages = $conversation?->messages ?? collect();
    $activeModel = $models->firstWhere('model_id', $modelId);
@endphp

<div class="chat-app" x-data="{
        drawer: false,
        collapsed: window.chatSidebarCollapsed(),
        toggleSidebar() {
            this.collapsed = ! this.collapsed;
            document.documentElement.classList.toggle('chat-sidebar-collapsed', this.collapsed);
            try { localStorage.setItem('chat-sidebar', this.collapsed ? 'collapsed' : 'open'); } catch (_) {}
            this.$nextTick(() => this.$refs[this.collapsed ? 'sidebarOpen' : 'sidebarClose'].focus());
        },
    }" @keydown.escape.window="drawer = false">
    {{-- ── Sidebar ───────────────────────────────────────────────────────── --}}
    <div class="chat-drawer-scrim" x-show="drawer" x-transition.opacity @click="drawer = false"
        style="display: none;"></div>

    <aside id="chat-sidebar" class="chat-sidebar" :class="drawer ? 'is-open' : ''">
        <div class="chat-sidebar__top">
            <a href="{{ route('chat') }}" wire:navigate class="chat-brand">
                <span class="chat-brand__mark"><x-icon name="smart_toy" :size="18" /></span>
                <span><strong>Chat AI</strong><small>Ruang pribadi</small></span>
            </a>
            <button type="button" class="chat-icon-btn chat-only-mobile" @click="drawer = false"
                aria-label="Tutup daftar">
                <x-icon name="close" :size="18" />
            </button>
            <button type="button" class="chat-icon-btn chat-only-desktop" x-ref="sidebarClose" @click="toggleSidebar()"
                :aria-expanded="(!collapsed).toString()" aria-controls="chat-sidebar" aria-label="Sembunyikan sidebar"
                title="Sembunyikan sidebar">
                <x-icon name="left_panel_close" :size="20" />
            </button>
        </div>

        <button type="button" wire:click="newChat" class="chat-new">
            <x-icon name="add" :size="18" />
            Chat baru
        </button>

        <a href="{{ route('chat.grading') }}" wire:navigate class="chat-nav-link">
            <x-icon name="grading" :size="18" />
            Penilaian
        </a>

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
                :aria-expanded="drawer.toString()" aria-controls="chat-sidebar" aria-label="Buka daftar percakapan">
                <x-icon name="menu" :size="20" />
            </button>
            <button type="button" class="chat-icon-btn chat-sidebar-reopen" x-ref="sidebarOpen" @click="toggleSidebar()"
                :aria-expanded="(!collapsed).toString()" aria-controls="chat-sidebar" aria-label="Tampilkan sidebar"
                title="Tampilkan sidebar">
                <x-icon name="left_panel_open" :size="20" />
            </button>
            <div class="chat-topbar__copy">
                <p class="chat-topbar__title">{{ $conversation?->title ?? 'Chat baru' }}</p>
                <p class="chat-topbar__status">
                    {{ $imageMode ? 'Studio gambar' : 'Percakapan' }}
                    @if ($activeModel)
                        <span aria-hidden="true">·</span> {{ $activeModel->label }}
                    @endif
                </p>
            </div>
            <span class="chat-topbar__mode @if ($imageMode) is-image @endif">
                <x-icon :name="$imageMode ? 'image' : 'smart_toy'" :size="14" />
                {{ $imageMode ? 'Gambar' : 'Chat' }}
            </span>
        </header>

        <div class="chat-scroll" id="chat-thread" data-chat-scroll>
            @if ($messages->isEmpty())
                <div class="chat-empty">
                    <div class="chat-empty__mark" aria-hidden="true">
                        <x-icon :name="$imageMode ? 'image' : 'smart_toy'" :size="26" />
                    </div>
                    <h1 class="chat-empty__title">
                        {{ $imageMode ? 'Bayangkan. Lalu buat.' : 'Halo, '.$chatUser->name.'.' }}
                    </h1>
                    <p class="chat-empty__lede">
                        {{ $imageMode
                            ? 'Pilih ukuran, jelaskan visualnya, lalu AI menyusun prompt yang lebih presisi.'
                            : 'Tanyakan apa saja, olah dokumen, atau mulai membuat gambar.' }}
                    </p>

                    <div class="chat-starters" aria-label="Contoh permintaan">
                        @if ($imageMode)
                            <button type="button"
                                wire:click='useStarter("Buat flyer PPDB sekolah yang modern, warna hijau dan emas, judul besar PENERIMAAN PESERTA DIDIK BARU", "image")'>
                                <span>Flyer PPDB</span><small>Poster promosi sekolah</small>
                            </button>
                            <button type="button"
                                wire:click='useStarter("Buat visual marketing untuk website absensi sekolah, tampilkan dashboard pada laptop dan ponsel", "image")'>
                                <span>Visual marketing</span><small>Grafis untuk web</small>
                            </button>
                            <button type="button"
                                wire:click='useStarter("Buat ilustrasi ruang kelas modern yang hangat dengan siswa Indonesia sedang belajar", "image")'>
                                <span>Ilustrasi kelas</span><small>Suasana pendidikan</small>
                            </button>
                        @else
                            <button type="button" wire:click='useStarter("Bantu saya menyusun pengumuman yang ringkas dan profesional")'>
                                <span>Tulis pengumuman</span><small>Rapi dan profesional</small>
                            </button>
                            <button type="button" wire:click='useStarter("Ringkas dokumen ini dan ambil poin pentingnya")'>
                                <span>Ringkas dokumen</span><small>Lampirkan PDF atau DOCX</small>
                            </button>
                            <button type="button"
                                wire:click='useStarter("Buat flyer PPDB sekolah yang modern", "image")'>
                                <span>Buat gambar</span><small>Flyer, poster, ilustrasi</small>
                            </button>
                        @endif
                    </div>
                </div>
            @else
                <div class="chat-thread">
                    @foreach ($messages as $message)
                        @include('livewire.chat.partials.message', ['message' => $message])
                    @endforeach
                </div>
            @endif

            {{-- Immediate send feedback lives here until Livewire renders the saved message. --}}
            <div class="chat-pending-slot" data-chat-pending wire:ignore aria-live="polite"></div>

            {{-- Livewire streams answer fragments into this element. --}}
            <div id="chat-stream" class="chat-stream" aria-live="polite"></div>

            <div class="chat-thinking" role="status" wire:loading.flex.delay.short wire:target="send,retry">
                <span class="chat-thinking__dots" aria-hidden="true"><span></span><span></span><span></span></span>
                <span>{{ $imageMode ? 'Sedang membuat gambar…' : 'Sedang berpikir…' }}</span>
            </div>
        </div>

        {{-- ── Composer ─────────────────────────────────────────────────── --}}
        <div class="chat-composer-wrap">
            <div class="chat-mode-switch" role="group" aria-label="Mode kerja">
                <button type="button" wire:click="setMode('chat')" @class(['is-active' => !$imageMode])
                    aria-pressed="{{ $imageMode ? 'false' : 'true' }}">
                    <x-icon name="smart_toy" :size="16" />
                    Chat
                </button>
                <button type="button" wire:click="setMode('image')" @class(['is-active' => $imageMode])
                    aria-pressed="{{ $imageMode ? 'true' : 'false' }}">
                    <x-icon name="image" :size="16" />
                    Buat gambar
                </button>
            </div>

            <form wire:submit="send" class="chat-composer" data-chat-form>
                @if ($imageMode)
                    <div class="chat-image-options">
                        <div class="chat-image-options__intro">
                            <span>Ukuran kanvas</span>
                            <small>Otomatis atau pilih ukuran</small>
                        </div>
                        <div class="chat-size-options" role="radiogroup" aria-label="Ukuran gambar">
                            @foreach ($imageSizes as $value => $size)
                                <button type="button" role="radio"
                                    aria-checked="{{ $imageSize === $value ? 'true' : 'false' }}"
                                    wire:click="setImageSize('{{ $value }}')"
                                    @class(['chat-size-option', 'is-active' => $imageSize === $value])>
                                    <span class="chat-size-option__shape chat-size-option__shape--{{ str_replace(':', '-', $value) }}"
                                        aria-hidden="true"></span>
                                    <span><strong>{{ $size['label'] }}</strong><small>{{ $size['hint'] }}</small></span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($uploads !== [])
                    <div class="chat-chips">
                        @foreach ($uploads as $index => $upload)
                            <span class="chat-chip" wire:key="upload-{{ $index }}">
                                <span class="chat-chip__name">{{ $upload->getClientOriginalName() }}</span>
                                <button type="button" wire:click="removeUpload({{ $index }})"
                                    aria-label="Hapus lampiran {{ $upload->getClientOriginalName() }}">
                                    <x-icon name="close" :size="12" />
                                </button>
                            </span>
                        @endforeach
                    </div>
                @endif

                <div class="chat-input-row">
                    <textarea wire:model="prompt" data-chat-input rows="1" enterkeyhint="send"
                        placeholder="{{ $imageMode ? 'Contoh: flyer PPDB hijau-emas, judul besar, suasana sekolah modern…' : 'Tulis pesan…' }}"
                        maxlength="20000" aria-label="{{ $imageMode ? 'Deskripsi gambar' : 'Pesan' }}"
                        class="chat-input"></textarea>

                    <button type="submit" class="chat-send" data-chat-send
                        aria-label="Kirim" title="Kirim">
                        <x-icon name="arrow_upward" :size="18" />
                    </button>
                    {{-- Image generation is one blocking call; it cannot be interrupted. --}}
                    @unless ($imageMode)
                        <button type="button" class="chat-stop" data-chat-stop data-stop-url="{{ route('chat.stop') }}"
                            aria-label="Hentikan proses" title="Hentikan proses" hidden>
                            <x-icon name="stop" :size="17" />
                        </button>
                    @endunless
                </div>

                <div class="chat-toolbar">
                    <div class="chat-toolbar__left">
                        @if (!$imageMode)
                            <label class="chat-attachment" title="Lampirkan berkas">
                                <x-icon name="attach_file" :size="17" />
                                <span>Lampiran</span>
                                <input type="file" wire:model="uploads" multiple class="sr-only"
                                    accept="{{ $supportsVision ? '.jpg,.jpeg,.png,.webp,.pdf,.docx' : '.pdf,.docx' }}" />
                            </label>
                        @endif

                        <div class="chat-select-wrap">
                            <select wire:model.live="modelId" class="chat-select" aria-label="Model AI">
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
                                <select wire:model="effort" class="chat-select" aria-label="Kedalaman jawaban">
                                    @foreach ($efforts as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <x-icon name="expand_more" :size="16" class="chat-select__caret" />
                            </div>
                        @endif
                    </div>

                    <span class="chat-key-hint">Enter kirim <span>·</span> Shift+Enter baris baru</span>
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
