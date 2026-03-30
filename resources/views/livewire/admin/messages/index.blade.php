<div>
    <p class="text-on-surface-variant mb-8">Contact form submissions from your portfolio</p>

    <div class="bg-surface-container-lowest rounded-2xl shadow-ambient overflow-hidden">
        @forelse($messages as $msg)
            <div class="border-b border-outline-variant/10 last:border-none">
                <button wire:click="view({{ $msg->id }})" class="w-full text-left px-6 py-5 flex items-start gap-4 hover:bg-surface-container-low/50 transition-colors">
                    <div class="w-10 h-10 bg-{{ $msg->is_read ? 'surface-container-high' : 'primary' }} rounded-full flex items-center justify-center flex-shrink-0 mt-1">
                        <span class="material-symbols-outlined text-sm {{ $msg->is_read ? 'text-outline' : 'text-on-primary' }}">person</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="font-bold text-sm {{ !$msg->is_read ? 'text-on-surface' : 'text-on-surface-variant' }}">{{ $msg->name }}</p>
                                <p class="text-xs text-outline">{{ $msg->email }}</p>
                            </div>
                            <span class="text-xs text-outline whitespace-nowrap ml-4">{{ $msg->created_at->diffForHumans() }}</span>
                        </div>
                        <p class="text-sm {{ !$msg->is_read ? 'text-on-surface font-medium' : 'text-on-surface-variant' }} mt-1">{{ $msg->subject }}</p>
                        @if($viewingId !== $msg->id)
                            <p class="text-xs text-outline truncate mt-1">{{ Str::limit($msg->message, 80) }}</p>
                        @endif
                    </div>
                    <span class="material-symbols-outlined text-outline transition-transform {{ $viewingId === $msg->id ? 'rotate-180' : '' }}">expand_more</span>
                </button>

                @if($viewingId === $msg->id)
                    <div class="px-6 pb-6 ml-14">
                        <div class="bg-surface-container-low rounded-xl p-6">
                            <p class="text-on-surface leading-relaxed whitespace-pre-line">{{ $msg->message }}</p>
                        </div>
                        <div class="flex gap-3 mt-4">
                            <a href="mailto:{{ $msg->email }}?subject=Re: {{ $msg->subject }}" class="btn-primary-gradient px-4 py-2 rounded-lg font-bold text-xs flex items-center gap-1">
                                <span class="material-symbols-outlined text-sm">reply</span> Reply
                            </a>
                            @if($msg->is_read)
                                <button wire:click="markAsUnread({{ $msg->id }})" class="px-4 py-2 bg-surface-container-high rounded-lg font-bold text-xs text-on-surface-variant hover:bg-surface-container-highest transition-colors">Mark Unread</button>
                            @else
                                <button wire:click="markAsRead({{ $msg->id }})" class="px-4 py-2 bg-surface-container-high rounded-lg font-bold text-xs text-on-surface-variant hover:bg-surface-container-highest transition-colors">Mark Read</button>
                            @endif
                            <button wire:click="delete({{ $msg->id }})" wire:confirm="Delete this message?" class="px-4 py-2 bg-error/10 text-error rounded-lg font-bold text-xs hover:bg-error/20 transition-colors">Delete</button>
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <div class="text-center py-16">
                <span class="material-symbols-outlined text-5xl text-outline mb-4 block">inbox</span>
                <p class="text-on-surface-variant">No messages yet. Contact form submissions will appear here.</p>
            </div>
        @endforelse
    </div>
</div>
