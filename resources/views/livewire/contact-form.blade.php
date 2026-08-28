<div>
    @if ($submitted)
        <div class="text-center py-8">
            <div class="inline-flex w-14 h-14 rounded-full items-center justify-center mb-5"
                style="background: rgba(16,185,129,0.12); color: #047857;">
                <x-icon name="check" :size="26" />
            </div>
            <h3 class="display display--md mb-2">Message sent.</h3>
            <p class="text-sm mb-6 max-w-sm mx-auto" style="color: var(--color-ink-4);">
                Thanks for writing in. I'll be in touch within a couple of business days.
            </p>
            <button wire:click="$set('submitted', false)" class="btn btn-light">
                <x-icon name="refresh" :size="16" />
                Send another
            </button>
        </div>
    @else
        <form wire:submit="submit">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="field !mb-0">
                    <label class="field-label" for="cf-name">Name</label>
                    <input id="cf-name" wire:model="name" type="text" placeholder="Your name" class="field-input"
                        autocomplete="name" />
                    @error('name')
                        <span class="micro mt-2 block" style="color: var(--color-error);">— {{ $message }}</span>
                    @enderror
                </div>
                <div class="field !mb-0">
                    <label class="field-label" for="cf-email">Email</label>
                    <input id="cf-email" wire:model="email" type="email" placeholder="you@studio.com"
                        class="field-input" autocomplete="email" />
                    @error('email')
                        <span class="micro mt-2 block" style="color: var(--color-error);">— {{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="field mt-4">
                <label class="field-label" for="cf-subject">Subject</label>
                <input id="cf-subject" wire:model="subject" type="text" placeholder="What's this about?"
                    class="field-input" />
                @error('subject')
                    <span class="micro mt-2 block" style="color: var(--color-error);">— {{ $message }}</span>
                @enderror
            </div>

            <div class="field">
                <label class="field-label" for="cf-message">Message</label>
                <textarea id="cf-message" wire:model="message" rows="6"
                    placeholder="Project, timeline, dreams, doubts — write freely." class="field-textarea"></textarea>
                @error('message')
                    <span class="micro mt-2 block" style="color: var(--color-error);">— {{ $message }}</span>
                @enderror
            </div>

            <div class="flex items-center justify-between gap-3 pt-2 flex-wrap">
                <p class="micro">Required · Won't be shared</p>
                <button type="submit" class="btn btn-embossed" wire:loading.attr="disabled">
                    <span wire:loading.remove>Send Message</span>
                    <span wire:loading>Sending…</span>
                    <x-icon name="arrow_outward" :size="16" wire:loading.remove />
                </button>
            </div>
        </form>
    @endif
</div>
