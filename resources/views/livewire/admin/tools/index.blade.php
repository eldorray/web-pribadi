<div>
    <div class="flex justify-between items-center mb-8">
        <p class="text-sm" style="color: var(--color-ink-4);">Manage tools displayed on the homepage fan stack.</p>
        <button wire:click="create"
            class="btn-primary-gradient px-5 py-2.5 rounded-full font-bold text-sm flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">add</span> Add Tool
        </button>
    </div>

    <div class="surface overflow-hidden">
        <table class="w-full">
            <thead>
                <tr class="border-b" style="border-color: var(--color-line);">
                    <th class="text-left px-5 py-3 text-xs uppercase tracking-widest font-semibold"
                        style="color: var(--color-ink-5);">#</th>
                    <th class="text-left px-5 py-3 text-xs uppercase tracking-widest font-semibold"
                        style="color: var(--color-ink-5);">Icon</th>
                    <th class="text-left px-5 py-3 text-xs uppercase tracking-widest font-semibold"
                        style="color: var(--color-ink-5);">Name</th>
                    <th class="text-left px-5 py-3 text-xs uppercase tracking-widest font-semibold hidden md:table-cell"
                        style="color: var(--color-ink-5);">Gradient / BG</th>
                    <th class="text-center px-5 py-3 text-xs uppercase tracking-widest font-semibold"
                        style="color: var(--color-ink-5);">Active</th>
                    <th class="text-right px-5 py-3 text-xs uppercase tracking-widest font-semibold"
                        style="color: var(--color-ink-5);">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tools as $tool)
                    <tr class="border-b last:border-0" style="border-color: var(--color-line);">
                        <td class="px-5 py-4 text-sm font-mono" style="color: var(--color-ink-5);">
                            {{ $tool->sort_order }}</td>
                        <td class="px-5 py-4">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center"
                                style="background: {{ $tool->gradient }};">
                                @if ($tool->icon_url)
                                    <img src="{{ $tool->icon_url }}" alt="{{ $tool->name }}"
                                        class="w-6 h-6 object-contain" />
                                @elseif($tool->icon_svg)
                                    <div class="w-5 h-5">{!! preg_replace('/<svg/', '<svg width="20" height="20"', $tool->icon_svg, 1) !!}</div>
                                @else
                                    <span class="material-symbols-outlined text-white text-base">apps</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-5 py-4 font-semibold text-sm" style="color: var(--color-ink);">{{ $tool->name }}
                        </td>
                        <td class="px-5 py-4 hidden md:table-cell">
                            <code class="text-xs font-mono"
                                style="color: var(--color-ink-4);">{{ Str::limit($tool->gradient, 50) }}</code>
                        </td>
                        <td class="px-5 py-4 text-center">
                            <button wire:click="toggleActive({{ $tool->id }})"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold"
                                style="background: {{ $tool->is_active ? 'rgba(16,185,129,0.10)' : 'rgba(156,163,175,0.10)' }}; color: {{ $tool->is_active ? '#047857' : '#6b7280' }};">
                                <span class="w-1.5 h-1.5 rounded-full"
                                    style="background: {{ $tool->is_active ? '#10b981' : '#9ca3af' }};"></span>
                                {{ $tool->is_active ? 'Active' : 'Hidden' }}
                            </button>
                        </td>
                        <td class="px-5 py-4 text-right">
                            <button wire:click="edit({{ $tool->id }})"
                                class="text-xs font-semibold mr-2 hover:underline"
                                style="color: var(--color-ink);">Edit</button>
                            <button wire:click="delete({{ $tool->id }})"
                                wire:confirm="Delete {{ $tool->name }}?"
                                class="text-xs font-semibold hover:underline"
                                style="color: var(--color-error);">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center text-sm" style="color: var(--color-ink-4);">No
                            tools yet. Click "Add Tool" to create one.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Modal --}}
    @if ($showModal)
        <div class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4"
            wire:click.self="$set('showModal', false)">
            <div class="bg-white rounded-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto p-6 sm:p-8">
                <h2 class="font-bold text-xl mb-6" style="color: var(--color-ink);">
                    {{ $editing ? 'Edit Tool' : 'New Tool' }}</h2>

                <form wire:submit="save" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-widest mb-2"
                            style="color: var(--color-ink-4);">Name</label>
                        <input wire:model="name" type="text" placeholder="Figma"
                            class="w-full px-4 py-2.5 rounded-lg border text-sm"
                            style="border-color: var(--color-line); background: var(--color-card-soft);" />
                        @error('name')
                            <span class="text-xs mt-1 block" style="color: var(--color-error);">{{ $message }}</span>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-widest mb-2"
                            style="color: var(--color-ink-4);">Background / Gradient (CSS)</label>
                        <input wire:model.live.debounce.500ms="gradient" type="text"
                            placeholder="#111827 or linear-gradient(...)"
                            class="w-full px-4 py-2.5 rounded-lg border text-sm font-mono"
                            style="border-color: var(--color-line); background: var(--color-card-soft);" />
                        <div class="mt-2 flex items-center gap-3">
                            <div class="w-12 h-12 rounded-lg" style="background: {{ $gradient ?: '#111827' }};"></div>
                            <span class="text-xs" style="color: var(--color-ink-5);">Live preview</span>
                        </div>
                        @error('gradient')
                            <span class="text-xs mt-1 block" style="color: var(--color-error);">{{ $message }}</span>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-widest mb-2"
                            style="color: var(--color-ink-4);">Icon — choose ONE</label>

                        <div class="space-y-3">
                            {{-- Upload --}}
                            <div>
                                <p class="text-xs mb-1.5" style="color: var(--color-ink-4);">Option A — Upload PNG/SVG
                                </p>
                                <input type="file" wire:model="iconUpload" accept="image/*"
                                    class="w-full text-xs file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-gray-100 file:text-ink" />
                                <div wire:loading wire:target="iconUpload" class="text-xs mt-1"
                                    style="color: var(--color-ink-4);">Uploading…</div>
                                @if ($icon_url)
                                    <div class="mt-2 flex items-center gap-2">
                                        <img src="{{ $icon_url }}" alt="preview"
                                            class="w-10 h-10 object-contain rounded border"
                                            style="border-color: var(--color-line);" />
                                        <button type="button" wire:click="$set('icon_url', '')"
                                            class="text-xs hover:underline"
                                            style="color: var(--color-error);">Clear</button>
                                    </div>
                                @endif
                            </div>

                            {{-- SVG paste --}}
                            <div>
                                <p class="text-xs mb-1.5" style="color: var(--color-ink-4);">Option B — Paste raw SVG
                                    markup</p>
                                <textarea wire:model="icon_svg" rows="4"
                                    placeholder='<svg viewBox="0 0 24 24" fill="white"><path d="..."/></svg>'
                                    class="w-full px-3 py-2 rounded-lg border text-xs font-mono"
                                    style="border-color: var(--color-line); background: var(--color-card-soft);"></textarea>
                                <p class="text-[11px] mt-1" style="color: var(--color-ink-5);">
                                    Tip: Get clean SVGs from <a href="https://simpleicons.org" target="_blank"
                                        rel="noopener" class="underline">simpleicons.org</a>. Set
                                    <code>fill="white"</code> for icons that contrast with the gradient.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-widest mb-2"
                                style="color: var(--color-ink-4);">Sort order</label>
                            <input wire:model="sort_order" type="number"
                                class="w-full px-4 py-2.5 rounded-lg border text-sm"
                                style="border-color: var(--color-line); background: var(--color-card-soft);" />
                        </div>
                        <div class="flex items-end">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input wire:model="is_active" type="checkbox" class="w-4 h-4" />
                                <span class="text-sm font-medium" style="color: var(--color-ink-3);">Show on
                                    homepage</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t" style="border-color: var(--color-line);">
                        <button type="button" wire:click="$set('showModal', false)"
                            class="px-5 py-2.5 rounded-full font-bold text-sm"
                            style="background: var(--color-card-soft); color: var(--color-ink);">Cancel</button>
                        <button type="submit"
                            class="btn-primary-gradient px-5 py-2.5 rounded-full font-bold text-sm">{{ $editing ? 'Update' : 'Create' }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
