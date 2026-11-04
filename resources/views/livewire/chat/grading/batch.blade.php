<div class="chat-grading">
    <header class="chat-grading__header">
        <a href="{{ route('chat.grading') }}" wire:navigate class="chat-icon-btn" aria-label="Kembali ke Penilaian"
            title="Kembali ke Penilaian">
            <x-icon name="arrow_back" :size="20" />
        </a>
        <div>
            <h1 class="chat-grading__title">{{ $batch->name }}</h1>
            <p class="chat-grading__meta">{{ $batch->rubric_snapshot['name'] ?? '' }} · {{ $batch->model }}</p>
        </div>
    </header>

    <section class="chat-grading__panel" data-grading-queue data-upload-limit="{{ $uploadLimit }}"
        data-store-url="{{ route('chat.grading.items.store', $batch) }}" wire:ignore>
        <label class="chat-grading__secondary chat-grading__picker">
            <x-icon name="attach_file" :size="16" />
            Pilih tugas
            <input type="file" class="sr-only" multiple data-grading-files
                accept=".jpg,.jpeg,.png,.webp,.pdf,.docx" />
        </label>
        <p class="chat-grading__meta">
            Satu file untuk satu mahasiswa. Nama mahasiswa diambil dari nama file.
            Gambar dan dokumen maksimal {{ round($uploadLimit / 1048576, 1) }} MB per file.
        </p>
        <p class="chat-grading__status" data-grading-progress role="status"></p>
        <ul class="chat-grading__messages" data-grading-messages></ul>
    </section>
</div>
