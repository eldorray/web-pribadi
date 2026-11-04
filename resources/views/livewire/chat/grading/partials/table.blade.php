@php
    $statusLabels = [
        'preparing' => 'Diolah di browser',
        'queued' => 'Menunggu',
        'processing' => 'Dinilai',
        'ready' => 'Perlu diperiksa',
        'approved' => 'Disetujui',
        'failed' => 'Gagal',
    ];
@endphp

<ul class="chat-grading__rows">
    @forelse ($items as $item)
        <li class="chat-grading__row" wire:key="item-{{ $item->id }}"
            @if ($item->status === 'queued') data-grade-url="{{ route('chat.grading.items.grade', $item) }}"
                data-student-name="{{ $item->student_name }}" @endif>
            <strong>{{ $item->student_name }}</strong>
            <span class="chat-grading__badge">{{ $statusLabels[$item->status] ?? $item->status }}</span>
            @if ($item->error)
                <small class="chat-grading__error">{{ $item->error }}</small>
            @endif
        </li>
    @empty
        <li class="chat-grading__empty">Belum ada tugas. Pilih file untuk memulai.</li>
    @endforelse
</ul>
