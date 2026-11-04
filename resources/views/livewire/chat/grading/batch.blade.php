<div class="chat-grading">
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
            Satu file untuk satu mahasiswa. Nama mahasiswa diambil dari nama file.
            Gambar dan dokumen maksimal {{ round($uploadLimit / 1048576, 1) }} MB per file.
            Video maksimal 20 menit, diolah di browser ini dan tidak disimpan di server, jadi biarkan halaman tetap terbuka.
            Suara dari file di atas 500 MB tidak dibaca otomatis, jadi transkripnya perlu ditempel.
        </p>
        <p class="chat-grading__status" data-grading-progress role="status"></p>
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

            @include('livewire.chat.grading.partials.table')
        </section>

        @if ($open)
            @include('livewire.chat.grading.partials.detail', ['item' => $open])
        @endif
    </div>
</div>
