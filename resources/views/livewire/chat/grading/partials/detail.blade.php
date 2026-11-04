@php
    $files = (array) $item->files;
    $ai = $item->ai_result['criteria'] ?? [];
    $transcriptMissing = in_array($item->transcript_status, ['failed', 'pending'], true);
@endphp

<section class="chat-grading__panel chat-grading__detail" aria-labelledby="detail-heading" wire:key="detail-{{ $item->id }}">
    <div class="chat-grading__detail-head">
        <label class="chat-grading__field chat-grading__detail-name">
            <span id="detail-heading">Nama mahasiswa</span>
            <input type="text" value="{{ $item->student_name }}" maxlength="200"
                wire:change="renameStudent({{ $item->id }}, $event.target.value)" />
        </label>
        <button type="button" class="chat-icon-btn" wire:click="closeItem" aria-label="Tutup detail" title="Tutup detail">
            <x-icon name="close" :size="18" />
        </button>
    </div>

    @if ($item->injectionAttempt())
        <p class="chat-grading__alert" role="alert">
            Tugas ini coba memberi perintah ke AI. Periksa isi dan skornya dengan teliti.
        </p>
    @endif

    @if ($item->error)
        <p class="chat-grading__error" role="alert">{{ $item->error }}</p>
    @endif

    <div class="chat-grading__preview">
        @if ($item->type === 'image' && isset($files[0]))
            <a href="{{ route('chat.grading.file', ['item' => $item, 'index' => 0]) }}" target="_blank" rel="noopener">
                <img src="{{ route('chat.grading.file', ['item' => $item, 'index' => 0]) }}"
                    alt="Tugas {{ $item->student_name }}" loading="lazy" />
            </a>
        @elseif ($item->type === 'document')
            @if (isset($files[0]))
                <a href="{{ route('chat.grading.file', ['item' => $item, 'index' => 0]) }}" target="_blank" rel="noopener"
                    class="chat-grading__link">Buka file asli</a>
            @endif
            @if ($item->extracted_text)
                <details>
                    <summary>Teks esai</summary>
                    <div class="chat-grading__text">{{ $item->extracted_text }}</div>
                </details>
            @endif
        @elseif ($item->type === 'video')
            @if ($files !== [])
                <div class="chat-grading__frames">
                    @foreach ($files as $index => $frame)
                        <figure wire:key="frame-{{ $index }}">
                            <img src="{{ route('chat.grading.file', ['item' => $item, 'index' => $index]) }}"
                                alt="Potongan video pada {{ gmdate('i:s', (int) ($frame['at'] ?? 0)) }}" loading="lazy" />
                            <figcaption>{{ gmdate('i:s', (int) ($frame['at'] ?? 0)) }}</figcaption>
                        </figure>
                    @endforeach
                </div>
            @endif

            @if (in_array($item->transcript_status, ['no_audio', 'skipped'], true))
                <p class="chat-grading__badge">tanpa transkrip</p>
            @endif

            <form wire:submit="saveTranscript" class="chat-grading__form">
                <label class="chat-grading__field">
                    <span>{{ $transcriptMissing ? 'Tempel transkrip' : 'Transkrip' }}</span>
                    <textarea wire:model="transcriptDraft" rows="6"></textarea>
                    @error('transcriptDraft') <small class="chat-grading__error">{{ $message }}</small> @enderror
                </label>
                <div class="chat-grading__actions">
                    <button type="submit" class="chat-grading__secondary">Simpan transkrip dan nilai ulang</button>
                    @if ($transcriptMissing)
                        <button type="button" class="chat-grading__secondary"
                            wire:click="gradeWithoutTranscript({{ $item->id }})"
                            wire:confirm="Isi ucapan tidak akan terbaca AI. Lanjut menilai tanpa transkrip?">Nilai tanpa transkrip</button>
                    @endif
                </div>
            </form>
        @endif
    </div>

    @if ($item->ai_result['summary'] ?? false)
        <p class="chat-grading__summary"><b>Ringkasan AI:</b> {{ $item->ai_result['summary'] }}</p>
    @endif

    <form wire:submit="saveReview" class="chat-grading__form">
        @error('scores') <p class="chat-grading__error">{{ $message }}</p> @enderror

        @foreach ($criteria as $index => $criterion)
            <fieldset class="chat-grading__criterion" wire:key="score-{{ $index }}">
                <legend>
                    {{ $criterion['name'] }}
                    @if ($ai[$index]['needs_review'] ?? false)
                        <span class="chat-grading__badge">perlu dicek</span>
                    @endif
                </legend>

                @if ($criterion['human_only'] ?? false)
                    <p class="chat-grading__proposal"><b>Dinilai dosen.</b> AI tidak bisa mendengar suara, jadi tidak memberi usulan. Dengarkan videonya.</p>
                @elseif (isset($ai[$index]))
                    <p class="chat-grading__proposal">
                        <b>Usulan AI: {{ \App\Models\GradingItem::formatScore((float) $ai[$index]['score']) }}/{{ \App\Models\GradingItem::formatScore((float) $criterion['max_score']) }}.</b>
                        {{ $ai[$index]['reason'] }}
                    </p>
                    @if ($ai[$index]['evidence'] !== '')
                        <blockquote class="chat-grading__evidence">{{ $ai[$index]['evidence'] }}</blockquote>
                    @endif
                @endif

                <label class="chat-grading__field chat-grading__score">
                    <span>Skor final (maks. {{ \App\Models\GradingItem::formatScore((float) $criterion['max_score']) }})</span>
                    <input type="number" min="0" max="{{ $criterion['max_score'] }}" step="any" inputmode="decimal"
                        wire:model="scores.{{ $index }}" />
                    @error("scores.$index") <small class="chat-grading__error">{{ $message }}</small> @enderror
                </label>
            </fieldset>
        @endforeach

        <label class="chat-grading__field">
            <span>Catatan dosen</span>
            <textarea wire:model="note" rows="3" maxlength="5000"></textarea>
        </label>

        <div class="chat-grading__actions">
            <button type="button" class="chat-grading__primary" wire:click="approve">
                <x-icon name="check" :size="16" />
                {{ $item->approved_at ? 'Setujui ulang' : 'Setujui' }}
            </button>
            <button type="submit" class="chat-grading__secondary">Simpan</button>
        </div>

        <div class="chat-grading__actions">
            <button type="button" class="chat-grading__link" wire:click="regrade({{ $item->id }})">
                Nilai ulang dengan AI
            </button>
            <button type="button" class="chat-grading__link" wire:click="deleteItem({{ $item->id }})"
                wire:confirm="Hapus tugas {{ $item->student_name }} beserta nilainya?">Hapus tugas</button>
        </div>
    </form>
</section>
