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
</div>
