<div class="space-y-8">
    {{-- ───────────── Koneksi ───────────── --}}
    <section class="surface p-6">
        <div class="flex items-start justify-between gap-4 mb-6">
            <div>
                <h2 class="text-lg font-bold" style="color: var(--color-ink);">Koneksi penyedia</h2>
                <p class="text-sm mt-1" style="color: var(--color-ink-4);">
                    Penyedia apa pun yang OpenAI-compatible. Base URL biasanya berakhir dengan <code
                        class="font-mono text-xs">/v1</code>.
                </p>
            </div>
            <span
                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold whitespace-nowrap"
                style="background: {{ $hasApiKey ? 'rgba(16,185,129,0.10)' : 'rgba(156,163,175,0.12)' }}; color: {{ $hasApiKey ? '#047857' : '#6b7280' }};">
                <span class="w-1.5 h-1.5 rounded-full"
                    style="background: {{ $hasApiKey ? '#10b981' : '#9ca3af' }};"></span>
                {{ $hasApiKey ? 'API key tersimpan' : 'API key belum ada' }}
            </span>
        </div>

        <form wire:submit="saveConnection" class="space-y-5">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-widest mb-2"
                    style="color: var(--color-ink-4);">Base URL</label>
                <input wire:model="baseUrl" type="text" inputmode="url" placeholder="https://api.openai.com/v1"
                    class="w-full px-4 py-2.5 rounded-lg border text-sm font-mono"
                    style="border-color: var(--color-line); background: var(--color-card-soft);" />
                @error('baseUrl')
                    <span class="text-xs mt-1 block" style="color: var(--color-error);">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-widest mb-2"
                    style="color: var(--color-ink-4);">API key</label>
                <input wire:model="apiKey" type="password" autocomplete="new-password"
                    placeholder="{{ $hasApiKey ? '•••••••• (kosongkan untuk mempertahankan)' : 'sk-...' }}"
                    class="w-full px-4 py-2.5 rounded-lg border text-sm font-mono"
                    style="border-color: var(--color-line); background: var(--color-card-soft);" />
                <p class="text-xs mt-1.5" style="color: var(--color-ink-5);">Disimpan terenkripsi. Nilainya tidak pernah
                    ditampilkan lagi setelah disimpan.</p>
                @error('apiKey')
                    <span class="text-xs mt-1 block" style="color: var(--color-error);">{{ $message }}</span>
                @enderror
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-widest mb-2"
                        style="color: var(--color-ink-4);">Mode buat gambar</label>
                    <select wire:model="imageMode"
                        class="w-full px-4 py-2.5 rounded-lg border text-sm"
                        style="border-color: var(--color-line); background: var(--color-card-soft);">
                        <option value="images">/images/generations</option>
                        <option value="chat">/chat/completions (modalities)</option>
                    </select>
                    <p class="text-xs mt-1.5" style="color: var(--color-ink-5);">Jika satu mode gagal, coba mode
                        lainnya.</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-widest mb-2"
                        style="color: var(--color-ink-4);">Streaming</label>
                    <label class="flex items-center gap-3 px-4 py-2.5 rounded-lg border cursor-pointer"
                        style="border-color: var(--color-line); background: var(--color-card-soft);">
                        <input wire:model="streaming" type="checkbox" class="w-4 h-4" />
                        <span class="text-sm" style="color: var(--color-ink-3);">Tampilkan jawaban bertahap</span>
                    </label>
                    <p class="text-xs mt-1.5" style="color: var(--color-ink-5);">Matikan jika hosting mem-buffer
                        respons.</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <button type="submit" class="btn-primary-gradient px-5 py-2.5 rounded-full text-sm">Simpan</button>
                <button type="button" wire:click="testConnection" wire:loading.attr="disabled"
                    wire:target="testConnection"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full text-sm font-semibold border"
                    style="border-color: var(--color-line); color: var(--color-ink);">
                    <x-icon name="refresh" :size="16" />
                    <span wire:loading.remove wire:target="testConnection">Tes koneksi</span>
                    <span wire:loading wire:target="testConnection">Menguji…</span>
                </button>

                @if ($connectionMessage !== '')
                    <span class="text-sm font-medium"
                        style="color: {{ $connectionFailed ? 'var(--color-error)' : '#047857' }};">{{ $connectionMessage }}</span>
                @endif
            </div>
        </form>
    </section>

    {{-- ───────────── Model ───────────── --}}
    <section class="surface p-6">
        <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
            <div>
                <h2 class="text-lg font-bold" style="color: var(--color-ink);">Model</h2>
                <p class="text-sm mt-1" style="color: var(--color-ink-4);">Hanya model yang dicentang yang muncul di
                    halaman chat.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" wire:click="fetchModels" wire:loading.attr="disabled" wire:target="fetchModels"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-semibold border"
                    style="border-color: var(--color-line); color: var(--color-ink);">
                    <x-icon name="refresh" :size="16" /> Ambil model
                </button>
                <div class="flex items-center gap-2">
                    <input wire:model="newModelId" type="text" placeholder="model-id"
                        class="px-3 py-2 rounded-lg border text-sm font-mono w-44"
                        style="border-color: var(--color-line); background: var(--color-card-soft);" />
                    <button type="button" wire:click="addModel"
                        class="inline-flex items-center gap-1 px-3 py-2 rounded-full text-sm font-semibold"
                        style="background: var(--color-card-soft); color: var(--color-ink);">
                        <x-icon name="add" :size="16" /> Tambah
                    </button>
                </div>
            </div>
        </div>

        @error('newModelId')
            <p class="text-xs mb-3" style="color: var(--color-error);">{{ $message }}</p>
        @enderror

        @if ($modelsMessage !== '')
            <p class="text-sm mb-4" style="color: var(--color-ink-3);">{{ $modelsMessage }}</p>
        @endif

        @foreach ([['judul' => 'Model chat', 'rows' => $chatModels], ['judul' => 'Model gambar', 'rows' => $imageModels]] as $group)
            <h3 class="text-xs font-semibold uppercase tracking-widest mt-4 mb-2" style="color: var(--color-ink-5);">
                {{ $group['judul'] }}</h3>

            @if ($group['rows']->isEmpty())
                <p class="text-sm mb-4" style="color: var(--color-ink-5);">Belum ada. Klik "Ambil model" atau tambah
                    manual.</p>
            @else
                <div class="overflow-x-auto -mx-2 px-2 mb-2">
                    <table class="w-full min-w-[640px]">
                        <thead>
                            <tr class="border-b" style="border-color: var(--color-line);">
                                @foreach (['Model', 'Label', 'Aktif', 'Default', 'Effort', 'Vision', 'Gambar', ''] as $head)
                                    <th class="text-left px-3 py-2 text-[11px] uppercase tracking-widest font-semibold"
                                        style="color: var(--color-ink-5);">{{ $head }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($group['rows'] as $model)
                                <tr class="border-b last:border-0" style="border-color: var(--color-line);"
                                    wire:key="model-{{ $model->id }}">
                                    <td class="px-3 py-2.5 text-xs font-mono" style="color: var(--color-ink-3);">
                                        {{ $model->model_id }}</td>
                                    <td class="px-3 py-2.5">
                                        <input type="text" value="{{ $model->label }}"
                                            wire:change="updateModel({{ $model->id }}, 'label', $event.target.value)"
                                            class="w-40 px-2 py-1 rounded-md border text-xs"
                                            style="border-color: var(--color-line); background: var(--color-card-soft);" />
                                    </td>
                                    @foreach (['is_enabled', 'is_default', 'supports_effort', 'supports_vision', 'is_image'] as $flag)
                                        <td class="px-3 py-2.5">
                                            <input type="checkbox" @checked($model->{$flag})
                                                wire:click="updateModel({{ $model->id }}, '{{ $flag }}', {{ $model->{$flag} ? 'false' : 'true' }})"
                                                class="w-4 h-4" />
                                        </td>
                                    @endforeach
                                    <td class="px-3 py-2.5 text-right">
                                        <button type="button" wire:click="deleteModel({{ $model->id }})"
                                            wire:confirm="Hapus model {{ $model->model_id }}?"
                                            class="text-xs font-semibold hover:underline"
                                            style="color: var(--color-error);">Hapus</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endforeach
    </section>

    {{-- ───────────── Pengguna chat ───────────── --}}
    <section class="surface p-6">
        <div class="mb-6">
            <h2 class="text-lg font-bold" style="color: var(--color-ink);">Pengguna chat</h2>
            <p class="text-sm mt-1" style="color: var(--color-ink-4);">Setiap orang punya PIN dan riwayat chat
                sendiri.</p>
        </div>

        @if (session('chat-user-message'))
            <p class="text-sm mb-4 font-medium" style="color: #047857;">{{ session('chat-user-message') }}</p>
        @endif

        <form wire:submit="addUser" class="flex flex-wrap items-end gap-3 mb-6">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-widest mb-2"
                    style="color: var(--color-ink-4);">Nama</label>
                <input wire:model="newUserName" type="text" placeholder="Ayu"
                    class="px-3 py-2 rounded-lg border text-sm w-44"
                    style="border-color: var(--color-line); background: var(--color-card-soft);" />
                @error('newUserName')
                    <span class="text-xs mt-1 block" style="color: var(--color-error);">{{ $message }}</span>
                @enderror
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-widest mb-2"
                    style="color: var(--color-ink-4);">PIN (6 angka)</label>
                <input wire:model="newUserPin" type="text" inputmode="numeric" maxlength="6" placeholder="123456"
                    class="px-3 py-2 rounded-lg border text-sm font-mono w-32"
                    style="border-color: var(--color-line); background: var(--color-card-soft);" />
                @error('newUserPin')
                    <span class="text-xs mt-1 block" style="color: var(--color-error);">{{ $message }}</span>
                @enderror
            </div>
            <button type="submit" class="btn-primary-gradient px-5 py-2.5 rounded-full text-sm">Tambah</button>
        </form>

        <div class="space-y-3">
            @forelse ($chatUsers as $chatUser)
                <div class="flex flex-wrap items-center gap-3 px-4 py-3 rounded-xl border"
                    style="border-color: var(--color-line); background: var(--color-card-soft);"
                    wire:key="user-{{ $chatUser->id }}">
                    <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold"
                        style="background: var(--color-ink); color: var(--color-on-primary);">
                        {{ strtoupper(mb_substr($chatUser->name, 0, 1)) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold truncate" style="color: var(--color-ink);">
                            {{ $chatUser->name }}</p>
                        <p class="text-xs" style="color: var(--color-ink-5);">
                            {{ $chatUser->conversations()->count() }} percakapan</p>
                    </div>

                    <form wire:submit="changePin({{ $chatUser->id }})" class="flex items-center gap-2">
                        <input wire:model="pinEdits.{{ $chatUser->id }}" type="text" inputmode="numeric"
                            maxlength="6" placeholder="PIN baru"
                            class="px-3 py-1.5 rounded-lg border text-xs font-mono w-28"
                            style="border-color: var(--color-line); background: var(--color-card);" />
                        <button type="submit" class="text-xs font-semibold hover:underline"
                            style="color: var(--color-ink);">Simpan</button>
                    </form>

                    @error('pin')
                        <span class="text-xs w-full" style="color: var(--color-error);">{{ $message }}</span>
                    @enderror

                    <button type="button" wire:click="deleteUser({{ $chatUser->id }})"
                        wire:confirm="Hapus {{ $chatUser->name }} beserta seluruh percakapannya?"
                        class="text-xs font-semibold hover:underline" style="color: var(--color-error);">Hapus</button>
                </div>
            @empty
                <p class="text-sm" style="color: var(--color-ink-5);">Belum ada pengguna chat.</p>
            @endforelse
        </div>
    </section>
</div>
