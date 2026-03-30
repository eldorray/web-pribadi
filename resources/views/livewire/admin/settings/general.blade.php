<div>
    @if($saved)
        <div class="mb-6 bg-primary/10 text-primary p-4 rounded-xl font-bold text-sm flex items-center gap-2">
            <span class="material-symbols-outlined">check_circle</span> Settings saved successfully!
        </div>
    @endif

    <form wire:submit="save" class="space-y-8">
        @foreach($groups as $groupName => $groupSettings)
            <div class="bg-surface-container-lowest rounded-2xl shadow-ambient p-6">
                <h3 class="font-headline text-lg font-bold mb-6 capitalize">{{ str_replace('_', ' ', $groupName) }}</h3>
                <div class="space-y-4">
                    @foreach($groupSettings as $setting)
                        <div>
                            <label class="font-label text-xs uppercase tracking-widest text-outline font-bold block mb-2">
                                {{ str_replace('_', ' ', $setting->key) }}
                            </label>

                            @if($this->isImageSetting($setting->key))
                                {{-- Image Upload Field --}}
                                <div class="space-y-3">
                                    {{-- Current Image Preview --}}
                                    @if(!empty($settings[$setting->key]))
                                        <div class="relative inline-block">
                                            <img src="{{ $settings[$setting->key] }}" 
                                                 alt="Preview" 
                                                 class="w-40 h-32 object-cover rounded-xl border-2 border-outline-variant/20" />
                                            <button type="button" wire:click="removeImage('{{ $setting->key }}')"
                                                    class="absolute -top-2 -right-2 w-6 h-6 bg-error text-on-error rounded-full flex items-center justify-center text-xs hover:scale-110 transition-transform">
                                                <span class="material-symbols-outlined text-sm">close</span>
                                            </button>
                                        </div>
                                    @endif

                                    {{-- Upload Button --}}
                                    <div class="flex items-center gap-4">
                                        <label class="cursor-pointer inline-flex items-center gap-2 px-4 py-2.5 bg-primary/10 text-primary rounded-xl font-bold text-sm hover:bg-primary/20 transition-colors">
                                            <span class="material-symbols-outlined text-sm">upload</span>
                                            Upload Image
                                            <input type="file" wire:model="imageUploads.{{ $setting->key }}" accept="image/*" class="hidden" />
                                        </label>
                                        <span class="text-xs text-outline">or paste URL below</span>
                                    </div>

                                    {{-- Loading indicator --}}
                                    <div wire:loading wire:target="imageUploads.{{ $setting->key }}" class="text-xs text-primary font-medium flex items-center gap-2">
                                        <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                        Uploading...
                                    </div>

                                    {{-- URL input as fallback --}}
                                    <input wire:model="settings.{{ $setting->key }}" type="text" placeholder="Or paste image URL..."
                                        class="w-full bg-surface-container-low border-none rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary/20 outline-none text-sm" />
                                </div>
                            @elseif(strlen($setting->value ?? '') > 100)
                                <textarea wire:model="settings.{{ $setting->key }}" rows="3"
                                    class="w-full bg-surface-container-low border-none rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary/20 outline-none resize-none text-sm"></textarea>
                            @else
                                <input wire:model="settings.{{ $setting->key }}" type="text"
                                    class="w-full bg-surface-container-low border-none rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary/20 outline-none text-sm" />
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        <button type="submit" class="btn-primary-gradient px-8 py-3 rounded-xl font-bold text-sm">Save All Settings</button>
    </form>
</div>
