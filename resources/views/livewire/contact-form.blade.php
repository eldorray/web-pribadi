<div>
    @if($submitted)
        <div class="text-center py-16">
            <span class="material-symbols-outlined text-6xl text-primary mb-4 block" style="font-variation-settings: 'FILL' 1;">check_circle</span>
            <h3 class="font-headline text-2xl font-bold mb-2">Message Sent!</h3>
            <p class="text-on-surface-variant">Thank you for reaching out. I'll get back to you within 24-48 hours.</p>
            <button wire:click="$set('submitted', false)" class="mt-6 text-primary font-bold hover:underline">Send another message</button>
        </div>
    @else
        <form wire:submit="submit" class="space-y-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="space-y-2">
                    <label class="font-label text-xs uppercase tracking-widest text-outline font-bold">Name</label>
                    <input wire:model="name" type="text" placeholder="I.M. Pei"
                           class="w-full bg-surface-container-low border-none rounded-lg px-6 py-4 focus:ring-2 focus:ring-primary/20 focus:bg-surface-container-lowest transition-all outline-none"/>
                    @error('name') <span class="text-error text-xs">{{ $message }}</span> @enderror
                </div>
                <div class="space-y-2">
                    <label class="font-label text-xs uppercase tracking-widest text-outline font-bold">Email</label>
                    <input wire:model="email" type="email" placeholder="hello@architect.folio"
                           class="w-full bg-surface-container-low border-none rounded-lg px-6 py-4 focus:ring-2 focus:ring-primary/20 focus:bg-surface-container-lowest transition-all outline-none"/>
                    @error('email') <span class="text-error text-xs">{{ $message }}</span> @enderror
                </div>
            </div>
            <div class="space-y-2">
                <label class="font-label text-xs uppercase tracking-widest text-outline font-bold">Subject</label>
                <input wire:model="subject" type="text" placeholder="Project Inquiry"
                       class="w-full bg-surface-container-low border-none rounded-lg px-6 py-4 focus:ring-2 focus:ring-primary/20 focus:bg-surface-container-lowest transition-all outline-none"/>
                @error('subject') <span class="text-error text-xs">{{ $message }}</span> @enderror
            </div>
            <div class="space-y-2">
                <label class="font-label text-xs uppercase tracking-widest text-outline font-bold">Message</label>
                <textarea wire:model="message" placeholder="Tell me about your vision..." rows="6"
                          class="w-full bg-surface-container-low border-none rounded-lg px-6 py-4 focus:ring-2 focus:ring-primary/20 focus:bg-surface-container-lowest transition-all outline-none resize-none"></textarea>
                @error('message') <span class="text-error text-xs">{{ $message }}</span> @enderror
            </div>
            <button type="submit"
                    class="w-full md:w-auto btn-primary-gradient px-10 py-5 rounded-xl font-headline text-lg font-bold flex items-center justify-center gap-3"
                    wire:loading.attr="disabled">
                <span wire:loading.remove>Send Message</span>
                <span wire:loading>Sending...</span>
                <span class="material-symbols-outlined" wire:loading.remove>send</span>
            </button>
        </form>
    @endif
</div>
