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
                    <img src="{{ route('chat.file', ['message' => $message->id, 'index' => 'image']) }}"
                        alt="Gambar hasil buatan" loading="lazy" />
                    <a href="{{ route('chat.file', ['message' => $message->id, 'index' => 'image', 'download' => 1]) }}"
                        class="chat-generated__download">
                        <x-icon name="download" :size="14" /> Unduh
                    </a>
                </figure>
            @else
                <div class="chat-markdown">
                    {!! $message->html() !!}
                </div>

                @if ($message->is_truncated)
                    <p class="chat-truncated">Jawaban terpotong.</p>
                @endif
            @endif
        </div>
    @endif
</div>
