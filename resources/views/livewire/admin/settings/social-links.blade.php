<div>
    <div class="flex justify-between items-center mb-8">
        <p class="text-on-surface-variant">Manage your social media links</p>
        <button wire:click="create" class="btn-primary-gradient px-6 py-3 rounded-xl font-bold text-sm flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">add</span> Add Link
        </button>
    </div>

    <div class="bg-surface-container-lowest rounded-2xl shadow-ambient overflow-hidden">
        <div class="space-y-2 p-4">
            @forelse($links as $link)
                <div class="flex items-center justify-between p-4 bg-surface-container-low rounded-xl">
                    <div class="flex items-center gap-4">
                        <span class="material-symbols-outlined text-primary">link</span>
                        <div>
                            <p class="font-bold text-sm">{{ $link->platform }}</p>
                            <p class="text-xs text-outline truncate max-w-xs">{{ $link->url }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button wire:click="edit({{ $link->id }})" class="text-primary p-2"><span class="material-symbols-outlined text-xl">edit</span></button>
                        <button wire:click="delete({{ $link->id }})" wire:confirm="Delete this link?" class="text-error p-2"><span class="material-symbols-outlined text-xl">delete</span></button>
                    </div>
                </div>
            @empty
                <p class="text-center py-8 text-on-surface-variant">No social links added yet.</p>
            @endforelse
        </div>
    </div>

    @if($showModal)
        <div class="fixed inset-0 bg-on-surface/50 z-50 flex items-center justify-center p-4" wire:click.self="$set('showModal', false)">
            <div class="bg-surface-container-lowest rounded-2xl shadow-2xl p-8 max-w-md w-full">
                <h2 class="font-headline text-xl font-bold mb-6">{{ $editing ? 'Edit' : 'Add' }} Social Link</h2>
                <form wire:submit="save" class="space-y-4">
                    <div>
                        <label class="font-label text-xs uppercase tracking-widest text-outline font-bold block mb-2">Platform</label>
                        <input wire:model="platform" type="text" placeholder="e.g. LinkedIn" class="w-full bg-surface-container-low border-none rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary/20 outline-none" />
                        @error('platform') <span class="text-error text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="font-label text-xs uppercase tracking-widest text-outline font-bold block mb-2">URL</label>
                        <input wire:model="url" type="text" placeholder="https://..." class="w-full bg-surface-container-low border-none rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary/20 outline-none" />
                        @error('url') <span class="text-error text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="font-label text-xs uppercase tracking-widest text-outline font-bold block mb-2">Sort Order</label>
                        <input wire:model="sort_order" type="number" class="w-full bg-surface-container-low border-none rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary/20 outline-none" />
                    </div>
                    <div class="flex justify-end gap-4 pt-4">
                        <button type="button" wire:click="$set('showModal', false)" class="px-6 py-3 bg-surface-container-high rounded-xl font-bold text-sm">Cancel</button>
                        <button type="submit" class="btn-primary-gradient px-6 py-3 rounded-xl font-bold text-sm">Save</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
