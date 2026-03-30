<div>
    <div class="flex justify-between items-center mb-8">
        <p class="text-on-surface-variant">Manage your skills & expertise</p>
        <button wire:click="create" class="btn-primary-gradient px-6 py-3 rounded-xl font-bold text-sm flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">add</span> Add Skill
        </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @forelse($skills as $skill)
            <div class="bg-surface-container-lowest rounded-2xl shadow-ambient p-6 {{ $skill->is_wide ? 'md:col-span-2' : '' }}">
                <div class="flex items-start justify-between">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 bg-surface-container-low rounded-xl flex items-center justify-center text-{{ $skill->color }}">
                            <span class="material-symbols-outlined">{{ $skill->icon }}</span>
                        </div>
                        <div>
                            <h4 class="font-bold text-on-surface">{{ $skill->title }}</h4>
                            <p class="text-sm text-on-surface-variant mt-1">{{ $skill->description }}</p>
                            <div class="flex gap-2 mt-2">
                                <span class="text-xs bg-surface-container-high px-2 py-0.5 rounded-full text-outline">{{ $skill->color }}</span>
                                @if($skill->is_wide) <span class="text-xs bg-primary/10 px-2 py-0.5 rounded-full text-primary">wide</span> @endif
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-1">
                        <button wire:click="edit({{ $skill->id }})" class="text-primary p-2"><span class="material-symbols-outlined text-xl">edit</span></button>
                        <button wire:click="delete({{ $skill->id }})" wire:confirm="Delete this skill?" class="text-error p-2"><span class="material-symbols-outlined text-xl">delete</span></button>
                    </div>
                </div>
            </div>
        @empty
            <p class="text-center py-12 text-on-surface-variant bg-surface-container-lowest rounded-2xl md:col-span-2">No skills added yet.</p>
        @endforelse
    </div>

    @if($showModal)
        <div class="fixed inset-0 bg-on-surface/50 z-50 flex items-center justify-center p-4" wire:click.self="$set('showModal', false)">
            <div class="bg-surface-container-lowest rounded-2xl shadow-2xl p-8 max-w-lg w-full">
                <h2 class="font-headline text-xl font-bold mb-6">{{ $editing ? 'Edit' : 'Add' }} Skill</h2>
                <form wire:submit="save" class="space-y-4">
                    <div>
                        <label class="font-label text-xs uppercase tracking-widest text-outline font-bold block mb-2">Title</label>
                        <input wire:model="title" type="text" class="w-full bg-surface-container-low border-none rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary/20 outline-none" />
                        @error('title') <span class="text-error text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="font-label text-xs uppercase tracking-widest text-outline font-bold block mb-2">Description</label>
                        <textarea wire:model="description" rows="3" class="w-full bg-surface-container-low border-none rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary/20 outline-none resize-none"></textarea>
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="font-label text-xs uppercase tracking-widest text-outline font-bold block mb-2">Icon</label>
                            <input wire:model="icon" type="text" placeholder="code" class="w-full bg-surface-container-low border-none rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary/20 outline-none" />
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
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input wire:model="is_wide" type="checkbox" class="w-5 h-5 rounded text-primary focus:ring-primary/20" />
                        <span class="font-label text-sm font-bold">Wide card (spans 2 columns)</span>
                    </label>
                    <div class="flex justify-end gap-4 pt-4">
                        <button type="button" wire:click="$set('showModal', false)" class="px-6 py-3 bg-surface-container-high rounded-xl font-bold text-sm">Cancel</button>
                        <button type="submit" class="btn-primary-gradient px-6 py-3 rounded-xl font-bold text-sm">Save</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
