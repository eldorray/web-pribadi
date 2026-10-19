<div class="pf-contact-form">
    @if ($submitted)
        <div class="pf-form-success" role="status" tabindex="-1">
            <span class="pf-form-success__icon"><x-icon name="check" :size="30" /></span>
            <h3>Pesan sudah terkirim.</h3>
            <p>Terima kasih sudah bercerita. Saya akan membalas melalui email yang Anda tuliskan.</p>
            <button type="button" wire:click="$set('submitted', false)" class="pf-button pf-button--small"><x-icon name="refresh" :size="17" /> Kirim pesan lagi</button>
        </div>
    @else
        <form wire:submit="submit">
            <div class="pf-form-row">
                <div class="pf-field">
                    <label for="cf-name">Nama <span aria-hidden="true">*</span></label>
                    <input id="cf-name" wire:model="name" type="text" placeholder="Nama Anda" autocomplete="name" required minlength="2" maxlength="255" @error('name') aria-invalid="true" aria-describedby="cf-name-error" @enderror />
                    @error('name')<p id="cf-name-error" class="pf-field-error" role="alert">{{ $message }}</p>@enderror
                </div>
                <div class="pf-field">
                    <label for="cf-email">Email <span aria-hidden="true">*</span></label>
                    <input id="cf-email" wire:model="email" type="email" placeholder="nama@email.com" autocomplete="email" required maxlength="255" @error('email') aria-invalid="true" aria-describedby="cf-email-error" @enderror />
                    @error('email')<p id="cf-email-error" class="pf-field-error" role="alert">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="pf-field">
                <label for="cf-subject">Subjek <span aria-hidden="true">*</span></label>
                <input id="cf-subject" wire:model="subject" type="text" placeholder="Apa yang ingin kita diskusikan?" required minlength="2" maxlength="255" @error('subject') aria-invalid="true" aria-describedby="cf-subject-error" @enderror />
                @error('subject')<p id="cf-subject-error" class="pf-field-error" role="alert">{{ $message }}</p>@enderror
            </div>
            <div class="pf-field">
                <label for="cf-message">Pesan <span aria-hidden="true">*</span></label>
                <textarea id="cf-message" wire:model="message" rows="6" placeholder="Ceritakan ide, kebutuhan, atau hal yang ingin Anda wujudkan…" required minlength="10" maxlength="5000" @error('message') aria-invalid="true" aria-describedby="cf-message-error" @enderror></textarea>
                @error('message')<p id="cf-message-error" class="pf-field-error" role="alert">{{ $message }}</p>@enderror
            </div>
            <div class="pf-form-actions">
                <p>* Semua kolom wajib diisi.<br />Informasi Anda tidak dipublikasikan.</p>
                <button type="submit" class="pf-button" wire:loading.attr="disabled" wire:target="submit">
                    <span wire:loading.remove wire:target="submit">Kirim pesan</span><span wire:loading wire:target="submit" role="status">Mengirim…</span><x-icon name="arrow_outward" :size="19" />
                </button>
            </div>
        </form>
    @endif
</div>
