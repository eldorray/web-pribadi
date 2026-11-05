@php
    $isUser = $message->role === 'user';
@endphp

<div class="chat-msg {{ $isUser ? 'chat-msg--user' : 'chat-msg--ai' }}" wire:key="message-{{ $message->id }}">
    @if ($isUser)
        <div class="chat-bubble">
            @if ($message->content !== '')
                <p class="chat-bubble__text">{{ $message->content }}</p>
            @endif

            @foreach ((array) $message->attachments as $index => $attachment)
                @php
                    $mime = (string) ($attachment['mime'] ?? '');
                    $name = (string) ($attachment['name'] ?? 'berkas');
                @endphp

                @if (str_starts_with($mime, 'image/'))
                    <a href="{{ route('chat.file', ['message' => $message->id, 'index' => $index]) }}" target="_blank"
                        rel="noopener" class="chat-bubble__image">
                        <img src="{{ route('chat.file', ['message' => $message->id, 'index' => $index]) }}"
                            alt="{{ $name }}" loading="lazy" />
                    </a>
                @else
                    <a href="{{ route('chat.file', ['message' => $message->id, 'index' => $index]) }}" target="_blank"
                        rel="noopener" class="chat-file">
                        <x-icon name="attach_file" :size="14" />
                        {{ $name }}
                    </a>
                @endif
            @endforeach
        </div>
    @else
        <div class="chat-answer">
            @if ($message->is_error)
                <div class="chat-error">
                    <p class="chat-error__text">{{ $message->content }}</p>
                    <button type="button" wire:click="retry({{ $message->id }})" class="chat-error__retry">
                        <x-icon name="refresh" :size="14" /> Coba lagi
                    </button>
                </div>
            @elseif ($message->image_path)
                <figure class="chat-generated">
                    <div class="chat-generated__canvas">
                        <img src="{{ route('chat.file', ['message' => $message->id, 'index' => 'image']) }}"
                            alt="Gambar hasil buatan" loading="lazy" />
                    </div>
                    <figcaption class="chat-generated__meta">
                        <div>
                            @if ($message->image_used_ratio)
                                <span class="chat-generated__ratio">{{ $message->image_used_ratio }}</span>
                            @endif
                            <span>{{ $message->image_edit === 'edited' ? 'Diedit dengan' : 'Dibuat dengan' }} {{ $message->model }}</span>
                        </div>
                        <a href="{{ route('chat.file', ['message' => $message->id, 'index' => 'image', 'download' => 1]) }}"
                            class="chat-generated__download">
                            <x-icon name="download" :size="15" /> Unduh gambar
                        </a>
                    </figcaption>
                </figure>

                @if ($message->image_edit === 'redrawn')
                    <p class="chat-generated__notice">
                        Model ini tidak bisa mengedit gambar langsung, jadi gambar dibuat ulang dari deskripsi dan hasilnya
                        bisa berbeda. Pilih model gambar lain kalau perlu mengedit bagian tertentu saja.
                    </p>
                @endif

                @if ($message->image_fell_back)
                    <p class="chat-generated__notice">
                        Ukuran {{ $message->image_requested_ratio }} tidak didukung penyedia. Gambar dibuat dalam
                        {{ $message->image_used_ratio ?: 'ukuran bawaan' }}.
                    </p>
                @endif

                @if (trim((string) $message->content) !== '')
                    <details class="chat-generated__prompt">
                        <summary>Prompt yang dipakai</summary>
                        <p>{{ $message->content }}</p>
                    </details>
                @endif
            @else
                <div class="chat-markdown">
                    {!! $message->html() !!}
                </div>

                <a href="{{ route('chat.grading', ['dari_pesan' => $message->id]) }}" wire:navigate class="chat-save-rubric">
                    <x-icon name="grading" :size="14" /> Simpan sebagai rubrik
                </a>

                @if ($message->is_truncated)
                    <p class="chat-truncated">Jawaban terpotong.</p>
                @endif
            @endif
        </div>
    @endif
</div>
