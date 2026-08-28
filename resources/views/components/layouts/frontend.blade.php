@props([
    'settings' => [],
    'socialLinks' => null,
    'title' => null,
    'metaDescription' => null,
])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
    <meta name="theme-color" content="#f3f4f6" />
    <title>{{ $title ?? ($settings['site_name'] ?? config('app.name')) }}</title>
    <meta name="description" content="{{ $metaDescription ?? ($settings['site_tagline'] ?? '') }}">

    {{-- Favicon (configurable from admin → settings → general).
         No type= — uploads are re-encoded to WebP, so the extension varies. --}}
    @if (!empty($settings['site_favicon']))
        <link rel="icon" href="{{ $settings['site_favicon'] }}" />
        <link rel="apple-touch-icon" href="{{ $settings['site_favicon'] }}" />
    @else
        <link rel="icon"
            href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E✦%3C/text%3E%3C/svg%3E" />
    @endif

    {{-- Satoshi + JetBrains Mono are self-hosted from public/fonts and declared
         in app.css. Only the two weights that paint above the fold are
         preloaded; the rest arrive with font-display: swap. --}}
    <link rel="preload" href="/fonts/satoshi-400.woff2" as="font" type="font/woff2" crossorigin />
    <link rel="preload" href="/fonts/satoshi-700.woff2" as="font" type="font/woff2" crossorigin />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <meta name="color-scheme" content="light dark">
    <script>
        {
            const savedTheme = localStorage.getItem('color-scheme') || 
                (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            
            if (savedTheme === 'dark') {
                document.documentElement.classList.add('dark');
                document.querySelector('meta[name="color-scheme"]').content = 'dark';
            } else {
                document.documentElement.classList.remove('dark');
                document.querySelector('meta[name="color-scheme"]').content = 'light';
            }
        }
    </script>
</head>

<body>

    <div class="page-bg">
        <div class="shell">
            {{-- Topbar: brand + (desktop nav OR mobile status badge) --}}
            <header class="topbar animate-reveal delay-100">
                <a href="{{ route('home') }}" class="brand-hola">{{ $settings['site_name'] ?? 'Hola!' }}</a>

                <div class="flex items-center gap-3 sm:gap-4">
                    {{-- Desktop nav --}}
                    <nav class="topnav hidden md:inline-flex" aria-label="Primary">
                        <a href="{{ route('about') }}"
                            class="topnav__link {{ request()->routeIs('about') ? 'is-active' : '' }}">About</a>
                        <a href="{{ route('projects') }}"
                            class="topnav__link {{ request()->routeIs('projects') ? 'is-active' : '' }}">Projects</a>
                        <a href="{{ route('contact') }}"
                            class="topnav__link {{ request()->routeIs('contact') ? 'is-active' : '' }}">Contact</a>
                    </nav>

                    {{-- Theme Toggle --}}
                    <button id="theme-toggle" class="theme-toggle-btn" aria-label="Toggle theme">
                        <x-icon name="dark_mode" :size="20" class="theme-toggle-icon-dark" />
                        <x-icon name="light_mode" :size="20" class="theme-toggle-icon-light" />
                    </button>

                    {{-- Mobile: subtle availability indicator (nav handled by bottom dock) --}}
                    <span class="md:hidden inline-flex items-center gap-1.5 text-[11px] font-medium"
                        style="color: var(--color-ink-4);">
                        <span class="w-1.5 h-1.5 rounded-full" style="background: #10b981;"></span>
                        Available
                    </span>
                </div>
            </header>

            {{ $slot }}

            {{-- Footer (in-card) --}}
            <footer class="mt-24">
                <div class="border-t pt-8" style="border-color: var(--color-line);">
                    <div class="mb-5">
                        <x-visitor-counter />
                    </div>
                    <div class="flex flex-col md:flex-row gap-4 justify-between items-start md:items-center">
                        <p class="micro">© {{ date('Y') }} {{ $settings['site_name'] ?? 'Studio' }}. All rights
                            reserved.</p>
                        @if ($socialLinks && $socialLinks->count() > 0)
                            <div class="flex flex-wrap gap-x-5 gap-y-2">
                                @foreach ($socialLinks as $link)
                                    <a href="{{ $link->url }}" target="_blank" rel="noopener"
                                        class="text-sm hover:underline" style="color: var(--color-ink-4);">
                                        {{ $link->platform }}
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </footer>
        </div>
    </div>

    {{-- Mobile bottom dock --}}
    <nav class="mobile-dock" aria-label="Mobile primary">
        <a href="{{ route('home') }}" class="dock-item {{ request()->routeIs('home') ? 'is-active' : '' }}">
            <x-icon name="waving_hand" :size="20" />
            <span class="dock-item__label">Hola!</span>
        </a>
        <a href="{{ route('projects') }}" class="dock-item {{ request()->routeIs('projects') ? 'is-active' : '' }}">
            <x-icon name="grid_view" :size="20" />
            <span class="dock-item__label">Work</span>
        </a>
        <a href="{{ route('about') }}" class="dock-item {{ request()->routeIs('about') ? 'is-active' : '' }}">
            <x-icon name="person" :size="20" />
            <span class="dock-item__label">About</span>
        </a>
        <a href="{{ route('contact') }}" class="dock-item {{ request()->routeIs('contact') ? 'is-active' : '' }}">
            <x-icon name="mail" :size="20" />
            <span class="dock-item__label">Contact</span>
        </a>
    </nav>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const themeToggleBtn = document.getElementById('theme-toggle');
            if (themeToggleBtn) {
                themeToggleBtn.addEventListener('click', () => {
                    const isDark = document.documentElement.classList.toggle('dark');
                    const theme = isDark ? 'dark' : 'light';
                    localStorage.setItem('color-scheme', theme);
                    
                    const meta = document.querySelector('meta[name="color-scheme"]');
                    if (meta) {
                        meta.content = theme;
                    }
                });
            }
            
            // Sync with system preferences dynamically if user hasn't explicitly set a preference
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
                if (!localStorage.getItem('color-scheme')) {
                    if (e.matches) {
                        document.documentElement.classList.add('dark');
                    } else {
                        document.documentElement.classList.remove('dark');
                    }
                }
            });
        });
    </script>
    {{-- @livewireStyles / @livewireScripts are deliberately absent: Livewire
         auto-injects them only on requests that actually render a component, so
         home/about/projects no longer download livewire.js at all. --}}
</body>

</html>
