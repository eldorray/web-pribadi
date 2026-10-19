@props(['settings' => [], 'socialLinks' => null, 'title' => null, 'metaDescription' => null])
@php
    $displayName = trim(explode(',', $settings['hero_title_2'] ?? $settings['site_name'] ?? 'Fahmie Al Khudhorie')[0]);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
    <meta name="theme-color" content="#eceee8" />
    <meta name="color-scheme" content="light" />
    <title>{{ $title ?? ($settings['site_name'] ?? config('app.name')) }}</title>
    <meta name="description" content="{{ $metaDescription ?? ($settings['site_tagline'] ?? 'Desain bermakna. Teknologi berguna.') }}" />
    @if (!empty($settings['site_favicon']))
        <link rel="icon" href="{{ $settings['site_favicon'] }}" />
        <link rel="apple-touch-icon" href="{{ $settings['site_favicon'] }}" />
    @else
        <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='24' fill='%232f392b'/%3E%3Ctext x='50' y='69' text-anchor='middle' font-family='sans-serif' font-size='60' fill='%23fff'%3EF%3C/text%3E%3C/svg%3E" />
    @endif
    <link rel="preload" href="/fonts/satoshi-400.woff2" as="font" type="font/woff2" crossorigin />
    <link rel="preload" href="/fonts/satoshi-700.woff2" as="font" type="font/woff2" crossorigin />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        // The reference's light palette is the first-visit default.
        try {
            const dark = localStorage.getItem('color-scheme') === 'dark';
            document.documentElement.classList.toggle('dark', dark);
            document.querySelector('meta[name="color-scheme"]').content = dark ? 'dark' : 'light';
            document.querySelector('meta[name="theme-color"]').content = dark ? '#1d241c' : '#eceee8';
        } catch (_) {}
    </script>
</head>
<body class="portfolio">
    <a class="pf-skip" href="#main-content">Lewati ke konten</a>
    <div class="pf-shell">
        <header class="pf-header">
            <a href="{{ route('home') }}" class="pf-brand" aria-label="{{ $displayName }} — Beranda">{{ $displayName }}</a>
            <div class="pf-header__right">
                <nav class="pf-nav" aria-label="Navigasi utama">
                    <a href="{{ route('projects') }}" @class(['is-active' => request()->routeIs('projects')]) @if(request()->routeIs('projects')) aria-current="page" @endif>Karya</a>
                    <a href="{{ route('about') }}" @class(['is-active' => request()->routeIs('about')]) @if(request()->routeIs('about')) aria-current="page" @endif>Tentang</a>
                </nav>
                <a href="{{ route('contact') }}" class="pf-contact-link" @if(request()->routeIs('contact')) aria-current="page" @endif>Mari bicara <x-icon name="arrow_outward" :size="15" /></a>
                @if(request()->routeIs('home'))
                    <span class="pf-availability"><span aria-hidden="true"></span>Terbuka untuk kolaborasi</span>
                @endif
                <details class="pf-mobile-menu">
                    <summary aria-label="Buka navigasi"><x-icon name="menu" :size="20" /></summary>
                    <nav aria-label="Navigasi seluler">
                        <a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif>Beranda</a>
                        <a href="{{ route('projects') }}" @if(request()->routeIs('projects')) aria-current="page" @endif>Karya</a>
                        <a href="{{ route('about') }}" @if(request()->routeIs('about')) aria-current="page" @endif>Tentang</a>
                        <a href="{{ route('contact') }}" @if(request()->routeIs('contact')) aria-current="page" @endif>Kontak</a>
                    </nav>
                </details>
            </div>
        </header>

        <main id="main-content" tabindex="-1">{{ $slot }}</main>

        <footer class="pf-footer">
            <div class="pf-footer__main">
                <p>© {{ date('Y') }} {{ $displayName }}</p>
                <p class="pf-footer__note">Dibuat dengan niat baik.</p>
                @if ($socialLinks && $socialLinks->isNotEmpty())
                    <div class="pf-socials">
                        @foreach ($socialLinks as $link)
                            <a href="{{ $link->url }}" target="_blank" rel="noopener noreferrer">{{ $link->platform }} <x-icon name="arrow_outward" :size="13" /></a>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="pf-footer__utility">
                <x-visitor-counter />
                <button type="button" id="theme-toggle" class="pf-theme" aria-label="Gunakan tampilan gelap" aria-pressed="false">
                    <x-icon name="dark_mode" :size="16" class="pf-theme__moon" />
                    <x-icon name="light_mode" :size="16" class="pf-theme__sun" />
                    <span class="pf-theme__label">Tampilan gelap</span>
                </button>
            </div>
        </footer>
    </div>
    <script>
        (() => {
            const button = document.getElementById('theme-toggle');
            const syncTheme = () => {
                const dark = document.documentElement.classList.contains('dark');
                button.setAttribute('aria-pressed', String(dark));
                button.setAttribute('aria-label', dark ? 'Gunakan tampilan terang' : 'Gunakan tampilan gelap');
                button.querySelector('.pf-theme__label').textContent = dark ? 'Tampilan terang' : 'Tampilan gelap';
                document.querySelector('meta[name="color-scheme"]').content = dark ? 'dark' : 'light';
                document.querySelector('meta[name="theme-color"]').content = dark ? '#1d241c' : '#eceee8';
            };
            button.addEventListener('click', () => {
                const dark = document.documentElement.classList.toggle('dark');
                try { localStorage.setItem('color-scheme', dark ? 'dark' : 'light'); } catch (_) {}
                syncTheme();
            });
            syncTheme();
            const menu = document.querySelector('.pf-mobile-menu');
            document.addEventListener('click', event => {
                if (!menu.contains(event.target)) menu.open = false;
            });
            document.addEventListener('keydown', event => {
                if (event.key === 'Escape' && menu.open) {
                    menu.open = false;
                    menu.querySelector('summary').focus();
                }
            });
        })();
    </script>
    {{-- Livewire assets are injected only when the contact component is present. --}}
</body>
</html>
