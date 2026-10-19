<x-layouts.frontend :settings="$settings" :socialLinks="$socialLinks" :title="'Kontak — ' . ($settings['site_name'] ?? 'Portfolio')">
    <section class="pf-panel pf-page-intro">
        <a href="{{ route('home') }}" class="pf-back"><x-icon name="arrow_back" :size="16" /> Kembali ke beranda</a>
        <div class="pf-page-intro__row"><h1>Mulai dari<br class="pf-mobile-break" /> sebuah obrolan.</h1><p>Punya ide, tantangan, atau sekadar ingin menyapa? Saya senang mendengarnya.</p></div>
    </section>

    <div class="pf-contact-grid">
        <section class="pf-panel pf-form-panel" aria-labelledby="contact-form-title">
            <p class="pf-eyebrow">Mari berkolaborasi</p>
            <h2 id="contact-form-title">Ceritakan ide Anda.</h2>
            <p class="pf-form-panel__intro">Tidak harus sudah sempurna. Kita bisa memikirkannya bersama.</p>
            @livewire('contact-form')
        </section>
        <aside class="pf-contact-aside">
            <section class="pf-panel pf-contact-info">
                <span class="pf-contact-info__icon"><x-icon name="mail" :size="24" /></span>
                <p class="pf-eyebrow">Lebih nyaman lewat email?</p>
                @if (!empty($settings['contact_email']))
                    <a class="pf-email" href="mailto:{{ $settings['contact_email'] }}">{{ $settings['contact_email'] }} <x-icon name="arrow_outward" :size="18" /></a>
                @else
                    <p>Kirim pesan melalui formulir di samping.</p>
                @endif
                @if (!empty($settings['contact_availability']))
                    <p class="pf-contact-availability"><span aria-hidden="true"></span>{{ $settings['contact_availability'] }}</p>
                @endif
            </section>

            @if (!empty($settings['contact_address_1']) || ($socialLinks && $socialLinks->isNotEmpty()))
                <section class="pf-panel pf-contact-info">
                    @if (!empty($settings['contact_address_1']))
                        <p class="pf-eyebrow">Berbasis di</p>
                        <address>{{ $settings['contact_address_1'] }}<br />{{ $settings['contact_address_2'] ?? '' }}<br />{{ $settings['contact_address_3'] ?? '' }}</address>
                    @endif
                    @if ($socialLinks && $socialLinks->isNotEmpty())
                        <div class="pf-contact-socials"><p class="pf-eyebrow">Temukan saya juga di</p><div class="pf-socials">
                            @foreach ($socialLinks as $link)
                                <a href="{{ $link->url }}" target="_blank" rel="noopener noreferrer">{{ $link->platform }} <x-icon name="arrow_outward" :size="14" /></a>
                            @endforeach
                        </div></div>
                    @endif
                </section>
            @endif

            <section class="pf-contact-note">
                <x-icon name="chat" :size="22" /><div><h3>Hal baik dimulai dari percakapan.</h3><p>Ceritakan kebutuhan dan tujuan Anda. Kita cari langkah yang paling masuk akal untuk mewujudkannya.</p></div>
            </section>
        </aside>
    </div>
</x-layouts.frontend>
