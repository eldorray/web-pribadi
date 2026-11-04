@php
    $statusLabels = [
        'preparing' => 'Diolah di browser',
        'queued' => 'Menunggu',
        'processing' => 'Sedang dinilai',
        'ready' => 'Perlu diperiksa',
        'approved' => 'Disetujui',
        'failed' => 'Gagal',
    ];
@endphp

<ul class="chat-grading__rows">
    @forelse ($items as $item)
        <li class="chat-grading__row {{ $openItemId === $item->id ? 'is-open' : '' }}" wire:key="item-{{ $item->id }}"
            @if ($item->status === 'queued') data-grade-url="{{ route('chat.grading.items.grade', $item) }}"
                data-student-name="{{ $item->student_name }}" @endif>
            <button type="button" class="chat-grading__row-main" wire:click="openItem({{ $item->id }})">
                <strong>{{ $item->student_name }}</strong>
                <small>
                    @if ($item->aiTotal() !== null)
                        Usulan AI {{ \App\Models\GradingItem::formatScore($item->aiTotal()) }}/{{ \App\Models\GradingItem::formatScore($aiMaxScore) }}
                        ·
                    @endif
                    @if ($item->approved_at)
                        <b>Nilai {{ \App\Models\GradingItem::formatScore((float) $item->finalTotal()) }}/{{ \App\Models\GradingItem::formatScore($maxScore) }}</b>
                        (skala {{ \App\Models\GradingItem::formatScore((float) $item->finalScaled()) }})
                    @else
                        Belum disetujui
                    @endif
                </small>
            </button>

            <span class="chat-grading__badges">
                @if ($item->injectionAttempt())
                    <span class="chat-grading__badge is-danger">Tugas ini coba memberi perintah ke AI</span>
                @endif
                @if ($item->needsReview())
                    <span class="chat-grading__badge">perlu dicek</span>
                @endif
                <span class="chat-grading__badge">{{ $statusLabels[$item->status] ?? $item->status }}</span>
            </span>

            @if ($item->error)
                <small class="chat-grading__error chat-grading__row-note">{{ $item->error }}</small>
            @elseif ($item->status === 'preparing')
                <small class="chat-grading__row-note chat-grading__meta">Pilih ulang file video ini kalau pengolahannya terhenti.</small>
            @elseif ($item->status === 'processing')
                <small class="chat-grading__row-note chat-grading__meta">Kalau terhenti, tugas ini kembali ke antrean setelah 10 menit saat halaman dimuat ulang.</small>
            @endif
        </li>
    @empty
        <li class="chat-grading__empty">Belum ada tugas. Pilih file untuk memulai.</li>
    @endforelse
</ul>
