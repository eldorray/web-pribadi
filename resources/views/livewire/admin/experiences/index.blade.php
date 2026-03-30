<div>
    <div class="flex justify-between items-center mb-8">
        <p class="text-on-surface-variant">Manage your experience timeline</p>
        <button wire:click="create" class="btn-primary-gradient px-6 py-3 rounded-xl font-bold text-sm flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">add</span> Add Experience
        </button>
    </div>

    <div class="space-y-4">
        @forelse($experiences as $exp)
            <div class="bg-surface-container-lowest rounded-2xl shadow-ambient p-6 flex items-center justify-between">
                <div class="flex items-center gap-6">
                    <div class="w-14 h-14 bg-surface-container-low rounded-2xl flex items-center justify-center text-{{ $exp->color }}">
                        <span class="material-symbols-outlined">{{ $exp->icon }}</span>
                    </div>
                    <div>
                        <h4 class="font-bold text-on-surface">{{ $exp->title }}</h4>
                        <p class="text-sm text-on-surface-variant">{{ $exp->company }}</p>
                        <span class="text-xs text-outline">{{ $exp->year_range }}</span>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button wire:click="edit({{ $exp->id }})" class="text-primary p-2"><span class="material-symbols-outlined text-xl">edit</span></button>
                    <button wire:click="delete({{ $exp->id }})" wire:confirm="Delete this experience?" class="text-error p-2"><span class="material-symbols-outlined text-xl">delete</span></button>
                </div>
            </div>
        @empty
            <p class="text-center py-12 text-on-surface-variant bg-surface-container-lowest rounded-2xl">No experiences added yet.</p>
        @endforelse
    </div>

    @if($showModal)
        <div class="fixed inset-0 bg-on-surface/50 z-50 flex items-center justify-center p-4" wire:click.self="$set('showModal', false)">
            <div class="bg-surface-container-lowest rounded-2xl shadow-2xl p-8 max-w-lg w-full max-h-[90vh] overflow-y-auto">
                <h2 class="font-headline text-xl font-bold mb-6">{{ $editing ? 'Edit' : 'Add' }} Experience</h2>
                <form wire:submit="save" class="space-y-4">
                    <div>
                        <label class="font-label text-xs uppercase tracking-widest text-outline font-bold block mb-2">Title</label>
                        <input wire:model="title" type="text" class="w-full bg-surface-container-low border-none rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary/20 outline-none" />
                        @error('title') <span class="text-error text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="font-label text-xs uppercase tracking-widest text-outline font-bold block mb-2">Company</label>
                        <input wire:model="company" type="text" class="w-full bg-surface-container-low border-none rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary/20 outline-none" />
                        @error('company') <span class="text-error text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="font-label text-xs uppercase tracking-widest text-outline font-bold block mb-2">Description</label>
                        <textarea wire:model="description" rows="3" class="w-full bg-surface-container-low border-none rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary/20 outline-none resize-none"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="font-label text-xs uppercase tracking-widest text-outline font-bold block mb-2">Start Year</label>
                            <input wire:model="start_year" type="text" maxlength="4" class="w-full bg-surface-container-low border-none rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary/20 outline-none" />
                        </div>
                        <div>
                            <label class="font-label text-xs uppercase tracking-widest text-outline font-bold block mb-2">End Year</label>
                            <input wire:model="end_year" type="text" placeholder="PRESENT" class="w-full bg-surface-container-low border-none rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary/20 outline-none" />
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="font-label text-xs uppercase tracking-widest text-outline font-bold block mb-2">Icon</label>
                            <input wire:model="icon" type="text" placeholder="token" class="w-full bg-surface-container-low border-none rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary/20 outline-none" />
                        </div>
                        <div>
                            <label class="font-label text-xs uppercase tracking-widest text-outline font-bold block mb-2">Color</label>
                            <select wire:model="color" class="w-full bg-surface-container-low border-none rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary/20 outline-none">
                                <option value="primary">Primary</option>
                                <option value="secondary">Secondary</option>
                                <option value="tertiary">Tertiary</option>
                            </select>
                        </div>
                        <div>
                            <label class="font-label text-xs uppercase tracking-widest text-outline font-bold block mb-2">Order</label>
                            <input wire:model="sort_order" type="number" class="w-full bg-surface-container-low border-none rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary/20 outline-none" />
                        </div>
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
