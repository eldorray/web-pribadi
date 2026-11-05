<div class="chat-grading" x-data="{
    deleting: null,
    askDelete(button) {
        this.deleting = { id: Number(button.dataset.id), name: button.dataset.name };
        this.$refs.deleteDialog.showModal();
    },
}">
    {{-- One grading is one long request, so the row only changes if the page asks again. --}}
    @if ($grading)
        <div hidden wire:poll.5s wire:key="grading-poll"></div>
    @endif

    <header class="chat-grading__header">
        <a href="{{ route('chat.grading') }}" wire:navigate class="chat-icon-btn" aria-label="Kembali ke Penilaian"
            title="Kembali ke Penilaian">
            <x-icon name="arrow_back" :size="20" />
        </a>
        <div class="chat-grading__header-copy">
            <h1 class="chat-grading__title">{{ $batch->name }}</h1>
            <p class="chat-grading__meta">{{ $batch->rubric_snapshot['name'] ?? '' }} · {{ $batch->model }}</p>
        </div>
        <a href="{{ route('chat.grading.export', $batch) }}" class="chat-grading__secondary">
            <x-icon name="download" :size="16" />
            Unduh CSV
        </a>
    </header>

    <section class="chat-grading__panel" data-grading-queue data-upload-limit="{{ $uploadLimit }}" data-max-files="{{ $maxFiles }}"
        data-store-url="{{ route('chat.grading.items.store', $batch) }}"
        data-video-url="{{ route('chat.grading.videos.store', $batch) }}" wire:ignore>
        <label class="chat-grading__secondary chat-grading__picker">
            <x-icon name="attach_file" :size="16" />
            Pilih tugas
            <input type="file" class="sr-only" multiple data-grading-files
                accept=".jpg,.jpeg,.png,.webp,.pdf,.docx,.mp4,.mov,.webm,.m4v,.mkv" />
        </label>
        <p class="chat-grading__meta">
            Satu file untuk satu mahasiswa atau kelompok. Namanya diambil dari nama file, misalnya <code>Group 1.jpg</code>.
            Untuk carousel atau beberapa halaman, beri nama <code>Group 1 - 1.jpg</code>, <code>Group 1 - 2.jpg</code>, dan seterusnya (maksimal 10 gambar).
            Gambar dan dokumen maksimal {{ round($uploadLimit / 1048576, 1) }} MB per file.
            Video maksimal 20 menit, diolah di browser ini dan tidak disimpan di server, jadi biarkan halaman tetap terbuka.
            Suara dari file di atas 500 MB tidak dibaca otomatis, jadi transkripnya perlu ditempel.
        </p>
        <ul class="chat-grading__messages" data-grading-messages></ul>
    </section>

    <div class="chat-grading__review {{ $open ? 'has-detail' : '' }}">
        <section class="chat-grading__results" aria-labelledby="results-heading">
            <div class="chat-grading__results-head">
                <h2 id="results-heading" class="chat-grading__heading">Tugas ({{ $items->count() }})</h2>
                @if ($items->contains('status', \App\Models\GradingItem::QUEUED))
                    <button type="button" class="chat-grading__primary" data-grading-run>Mulai menilai</button>
                @endif
            </div>

            <div class="chat-grading__live" wire:ignore>
                <p class="chat-grading__status chat-grading__progress" data-grading-progress role="status"></p>
                <ul class="chat-grading__rows" data-grading-pending></ul>
            </div>

            @include('livewire.chat.grading.partials.table')
        </section>

        @if ($open)
            @include('livewire.chat.grading.partials.detail', ['item' => $open])
        @endif
    </div>

    <dialog class="chat-grading__dialog" x-ref="deleteDialog" aria-labelledby="delete-dialog-title"
        x-on:click.self="$el.close()" wire:ignore>
        <div class="chat-grading__dialog-body">
            <h2 id="delete-dialog-title" class="chat-grading__heading">Hapus tugas?</h2>
            <p>Tugas <strong x-text="deleting?.name"></strong> beserta file dan nilainya akan dihapus permanen, dan tidak bisa dikembalikan.</p>
            <div class="chat-grading__dialog-actions">
                <button type="button" class="chat-grading__secondary" autofocus x-on:click="$refs.deleteDialog.close()">Batal</button>
                <button type="button" class="chat-grading__danger"
                    x-on:click="$refs.deleteDialog.close(); $wire.deleteItem(deleting.id)">
                    <x-icon name="delete" :size="16" />
                    Hapus
                </button>
            </div>
        </div>
    </dialog>
</div>
