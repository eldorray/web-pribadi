<div>
    <!-- Header -->
    <div class="flex justify-between items-center mb-8">
        <p class="text-on-surface-variant">Manage your portfolio projects</p>
        <button wire:click="create" class="btn-primary-gradient px-6 py-3 rounded-xl font-bold text-sm flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">add</span> Add Project
        </button>
    </div>

    <!-- Projects Table -->
    <div class="bg-surface-container-lowest rounded-2xl shadow-ambient overflow-hidden">
        <table class="w-full">
            <thead>
                <tr class="bg-surface-container-low">
                    <th class="text-left px-6 py-4 font-label text-xs uppercase tracking-widest text-outline">#</th>
                    <th class="text-left px-6 py-4 font-label text-xs uppercase tracking-widest text-outline">Image</th>
                    <th class="text-left px-6 py-4 font-label text-xs uppercase tracking-widest text-outline">Title</th>
                    <th class="text-left px-6 py-4 font-label text-xs uppercase tracking-widest text-outline">Category</th>
                    <th class="text-left px-6 py-4 font-label text-xs uppercase tracking-widest text-outline">Year</th>
                    <th class="text-left px-6 py-4 font-label text-xs uppercase tracking-widest text-outline">Featured</th>
                    <th class="text-right px-6 py-4 font-label text-xs uppercase tracking-widest text-outline">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($projects as $project)
                    <tr class="border-t border-outline-variant/10 hover:bg-surface-container-low/50 transition-colors">
                        <td class="px-6 py-4 text-sm text-outline">{{ $project->sort_order }}</td>
                        <td class="px-6 py-4">
                            @if($project->image_url)
                                <img src="{{ $project->image_url }}" alt="{{ $project->title }}" class="w-16 h-12 object-cover rounded-lg">
                            @else
                                <div class="w-16 h-12 bg-surface-container-high rounded-lg flex items-center justify-center">
                                    <span class="material-symbols-outlined text-outline text-sm">image</span>
                                </div>
                            @endif
                        </td>
                        <td class="px-6 py-4 font-bold text-sm">{{ $project->title }}</td>
                        <td class="px-6 py-4"><span class="text-xs font-bold uppercase tracking-wider text-primary bg-primary/10 px-3 py-1 rounded-full">{{ $project->category }}</span></td>
                        <td class="px-6 py-4 text-sm text-outline">{{ $project->year }}</td>
                        <td class="px-6 py-4">
                            @if($project->is_featured)
                                <span class="material-symbols-outlined text-primary" style="font-variation-settings: 'FILL' 1;">star</span>
                            @else
                                <span class="material-symbols-outlined text-outline">star</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <button wire:click="edit({{ $project->id }})" class="text-primary hover:text-primary-container transition-colors p-2">
                                <span class="material-symbols-outlined text-xl">edit</span>
                            </button>
                            <button wire:click="confirmDelete({{ $project->id }})" class="text-error hover:text-error/70 transition-colors p-2">
                                <span class="material-symbols-outlined text-xl">delete</span>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-on-surface-variant">No projects yet. Add your first project!</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Create/Edit Modal -->
    @if($showModal)
        <div class="fixed inset-0 bg-on-surface/50 z-50 flex items-center justify-center p-4" wire:click.self="$set('showModal', false)">
            <div class="bg-surface-container-lowest rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto p-8">
                <h2 class="font-headline text-xl font-bold mb-6">{{ $editing ? 'Edit Project' : 'New Project' }}</h2>
                <form wire:submit="save" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="font-label text-xs uppercase tracking-widest text-outline font-bold block mb-2">Title</label>
                            <input wire:model="title" type="text" class="w-full bg-surface-container-low border-none rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary/20 outline-none" />
                            @error('title') <span class="text-error text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="font-label text-xs uppercase tracking-widest text-outline font-bold block mb-2">Category</label>
                            <input wire:model="category" type="text" placeholder="e.g. UI/UX Design" class="w-full bg-surface-container-low border-none rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary/20 outline-none" />
                            @error('category') <span class="text-error text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div>
                        <label class="font-label text-xs uppercase tracking-widest text-outline font-bold block mb-2">Description</label>
                        <textarea wire:model="description" rows="4" class="w-full bg-surface-container-low border-none rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary/20 outline-none resize-none"></textarea>
                        @error('description') <span class="text-error text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="font-label text-xs uppercase tracking-widest text-outline font-bold block mb-2">Year</label>
                            <input wire:model="year" type="text" maxlength="4" class="w-full bg-surface-container-low border-none rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary/20 outline-none" />
                            @error('year') <span class="text-error text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="font-label text-xs uppercase tracking-widest text-outline font-bold block mb-2">Sort Order</label>
                            <input wire:model="sort_order" type="number" class="w-full bg-surface-container-low border-none rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary/20 outline-none" />
                        </div>
                        <div class="flex items-end">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input wire:model="is_featured" type="checkbox" class="w-5 h-5 rounded text-primary focus:ring-primary/20" />
                                <span class="font-label text-sm font-bold">Featured</span>
                            </label>
                        </div>
                    </div>
                    <div>
                        <label class="font-label text-xs uppercase tracking-widest text-outline font-bold block mb-2">Project Image</label>
                        <input wire:model="image" type="file" accept="image/*" class="w-full text-sm text-outline file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-primary/10 file:text-primary file:font-bold" />
                        @if($existingImage)
                            <p class="text-xs text-outline mt-2">Current: {{ basename($existingImage) }}</p>
                        @endif
                    </div>
                    <div class="flex justify-end gap-4 pt-4">
                        <button type="button" wire:click="$set('showModal', false)" class="px-6 py-3 bg-surface-container-high rounded-xl font-bold text-sm hover:bg-surface-container-highest transition-colors">Cancel</button>
                        <button type="submit" class="btn-primary-gradient px-6 py-3 rounded-xl font-bold text-sm">{{ $editing ? 'Update' : 'Create' }} Project</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Delete Confirmation Modal -->
    @if($confirmingDelete)
        <div class="fixed inset-0 bg-on-surface/50 z-50 flex items-center justify-center p-4">
            <div class="bg-surface-container-lowest rounded-2xl shadow-2xl p-8 max-w-sm w-full text-center">
                <span class="material-symbols-outlined text-5xl text-error mb-4">warning</span>
                <h3 class="font-headline text-lg font-bold mb-2">Delete Project?</h3>
                <p class="text-on-surface-variant text-sm mb-6">This action cannot be undone.</p>
                <div class="flex justify-center gap-4">
                    <button wire:click="cancelDelete" class="px-6 py-3 bg-surface-container-high rounded-xl font-bold text-sm">Cancel</button>
                    <button wire:click="delete" class="px-6 py-3 bg-error text-on-error rounded-xl font-bold text-sm">Delete</button>
                </div>
            </div>
        </div>
    @endif
</div>
