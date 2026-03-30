<div>
    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
        <div class="bg-surface-container-lowest rounded-2xl p-6 shadow-ambient">
            <div class="flex items-center gap-4 mb-4">
                <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center">
                    <span class="material-symbols-outlined text-primary">work</span>
                </div>
                <div>
                    <p class="text-3xl font-black font-headline text-on-surface">{{ $totalProjects }}</p>
                    <p class="text-xs font-label uppercase tracking-widest text-outline">Total Projects</p>
                </div>
            </div>
            <p class="text-sm text-on-surface-variant">{{ $featuredProjects }} featured</p>
        </div>

        <div class="bg-surface-container-lowest rounded-2xl p-6 shadow-ambient">
            <div class="flex items-center gap-4 mb-4">
                <div class="w-12 h-12 bg-tertiary/10 rounded-xl flex items-center justify-center">
                    <span class="material-symbols-outlined text-tertiary">mail</span>
                </div>
                <div>
                    <p class="text-3xl font-black font-headline text-on-surface">{{ $totalMessages }}</p>
                    <p class="text-xs font-label uppercase tracking-widest text-outline">Messages</p>
                </div>
            </div>
            <p class="text-sm text-on-surface-variant">{{ $unreadMessages }} unread</p>
        </div>

        <div class="bg-surface-container-lowest rounded-2xl p-6 shadow-ambient">
            <div class="flex items-center gap-4 mb-4">
                <div class="w-12 h-12 bg-secondary/10 rounded-xl flex items-center justify-center">
                    <span class="material-symbols-outlined text-secondary">psychology</span>
                </div>
                <div>
                    <p class="text-3xl font-black font-headline text-on-surface">{{ $totalSkills }}</p>
                    <p class="text-xs font-label uppercase tracking-widest text-outline">Skills</p>
                </div>
            </div>
        </div>

        <div class="bg-surface-container-lowest rounded-2xl p-6 shadow-ambient">
            <div class="flex items-center gap-4 mb-4">
                <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center">
                    <span class="material-symbols-outlined text-primary">timeline</span>
                </div>
                <div>
                    <p class="text-3xl font-black font-headline text-on-surface">{{ $totalExperiences }}</p>
                    <p class="text-xs font-label uppercase tracking-widest text-outline">Experiences</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Messages -->
    <div class="bg-surface-container-lowest rounded-2xl p-6 shadow-ambient">
        <h2 class="font-headline text-lg font-bold mb-6">Recent Messages</h2>
        @if($recentMessages->count() > 0)
            <div class="space-y-4">
                @foreach($recentMessages as $msg)
                    <div class="flex items-start gap-4 p-4 rounded-xl {{ $msg->is_read ? 'bg-surface-container-low' : 'bg-primary/5' }} transition-colors">
                        <div class="w-10 h-10 bg-{{ $msg->is_read ? 'surface-container-high' : 'primary' }} rounded-full flex items-center justify-center flex-shrink-0">
                            <span class="material-symbols-outlined text-sm {{ $msg->is_read ? 'text-outline' : 'text-on-primary' }}">person</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-start">
                                <p class="font-bold text-sm text-on-surface">{{ $msg->name }}</p>
                                <span class="text-xs text-outline whitespace-nowrap ml-4">{{ $msg->created_at->diffForHumans() }}</span>
                            </div>
                            <p class="text-sm text-on-surface-variant font-medium">{{ $msg->subject }}</p>
                            <p class="text-xs text-outline truncate mt-1">{{ $msg->message }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
            <a href="{{ route('admin.messages') }}" class="mt-4 text-primary font-bold text-sm flex items-center gap-1 hover:gap-2 transition-all inline-block">
                View all messages <span class="material-symbols-outlined text-sm">arrow_forward</span>
            </a>
        @else
            <p class="text-on-surface-variant text-center py-8">No messages yet.</p>
        @endif
    </div>
</div>
