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

    {{-- Favicon (configurable from admin → settings → general) --}}
    @if (!empty($settings['site_favicon']))
        <link rel="icon" type="image/png" href="{{ $settings['site_favicon'] }}" />
        <link rel="apple-touch-icon" href="{{ $settings['site_favicon'] }}" />
    @else
        <link rel="icon"
            href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E✦%3C/text%3E%3C/svg%3E" />
    @endif

    {{-- JetBrains Mono via Google Fonts. Satoshi loaded by app.css via Fontshare --}}
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&display=swap"
        rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body>

    <div class="page-bg">
        <div class="shell">
            {{-- Topbar: "Hola!" (left) + nav links (right) --}}
            <header class="topbar animate-reveal delay-100">
                <a href="{{ route('home') }}" class="brand-hola">Assalamualaikum . . .</a>

                <nav class="topnav hidden md:inline-flex" aria-label="Primary">
                    <a href="{{ route('about') }}"
                        class="topnav__link {{ request()->routeIs('about') ? 'is-active' : '' }}">About</a>
                    <a href="{{ route('projects') }}"
                        class="topnav__link {{ request()->routeIs('projects') ? 'is-active' : '' }}">Projects</a>
                    <a href="{{ route('contact') }}"
                        class="topnav__link {{ request()->routeIs('contact') ? 'is-active' : '' }}">Contact</a>
                </nav>

                {{-- Mobile: just a link to contact --}}
                <a href="{{ route('contact') }}" class="md:hidden text-sm font-semibold"
                    style="color: var(--color-ink);">
                    Contact
                </a>
            </header>

            {{ $slot }}

            {{-- Footer (in-card) --}}
            <footer class="mt-24">
                <div class="border-t pt-8" style="border-color: var(--color-line);">
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
            <span class="material-symbols-outlined text-[20px]">waving_hand</span>
            <span class="dock-item__label">Hola!</span>
        </a>
        <a href="{{ route('projects') }}" class="dock-item {{ request()->routeIs('projects') ? 'is-active' : '' }}">
            <span class="material-symbols-outlined text-[20px]">grid_view</span>
            <span class="dock-item__label">Work</span>
        </a>
        <a href="{{ route('about') }}" class="dock-item {{ request()->routeIs('about') ? 'is-active' : '' }}">
            <span class="material-symbols-outlined text-[20px]">person</span>
            <span class="dock-item__label">About</span>
        </a>
        <a href="{{ route('contact') }}" class="dock-item {{ request()->routeIs('contact') ? 'is-active' : '' }}">
            <span class="material-symbols-outlined text-[20px]">mail</span>
            <span class="dock-item__label">Contact</span>
        </a>
    </nav>

    @livewireScripts
</body>

</html>
