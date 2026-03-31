@props(['settings' => [], 'socialLinks' => collect(), 'title' => null, 'metaDescription' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>{{ $title ?? ($settings['site_name'] ?? config('app.name')) }}</title>
    <meta name="description" content="{{ $metaDescription ?? ($settings['site_tagline'] ?? '') }}">

    <!-- Google Fonts: Epilogue + Inter -->
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Epilogue:ital,wght@0,100..900;1,100..900&family=Inter:wght@100..900&display=swap" rel="stylesheet"/>

    <!-- Material Symbols -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-surface text-on-surface antialiased overflow-x-hidden">

    <!-- TopNavBar (Glassmorphism Floating Pill) -->
    <header class="fixed top-0 left-0 right-0 z-50 flex justify-center w-full px-4 md:px-0">
        <nav class="glass-nav rounded-full px-4 sm:px-6 py-3 mt-4 sm:mt-6 mx-auto w-full md:w-fit flex items-center justify-between md:justify-start gap-4 md:gap-10">
            <a href="{{ route('home') }}" class="text-lg sm:text-xl font-black text-on-surface font-headline tracking-tight shrink-0">
                {{ $settings['site_name'] ?? 'My Portofolio' }}
            </a>
            <div class="hidden md:flex items-center gap-8 font-headline text-sm font-medium tracking-tight">
                <a class="{{ request()->routeIs('home') ? 'text-primary font-bold border-b-2 border-primary pb-1' : 'text-outline hover:text-on-surface' }} hover:scale-105 active:scale-95 transition-all duration-300" href="{{ route('home') }}">Home</a>
                <a class="{{ request()->routeIs('projects') ? 'text-primary font-bold border-b-2 border-primary pb-1' : 'text-outline hover:text-on-surface' }} hover:scale-105 active:scale-95 transition-all duration-300" href="{{ route('projects') }}">Projects</a>
                <a class="{{ request()->routeIs('about') ? 'text-primary font-bold border-b-2 border-primary pb-1' : 'text-outline hover:text-on-surface' }} hover:scale-105 active:scale-95 transition-all duration-300" href="{{ route('about') }}">About</a>
                <a class="{{ request()->routeIs('contact') ? 'text-primary font-bold border-b-2 border-primary pb-1' : 'text-outline hover:text-on-surface' }} hover:scale-105 active:scale-95 transition-all duration-300" href="{{ route('contact') }}">Contact</a>
            </div>
            <a href="{{ route('contact') }}" class="btn-primary-gradient px-5 py-2 rounded-full text-sm font-bold hidden sm:inline-flex">
                Get in Touch
            </a>
            <!-- Mobile Hamburger Button -->
            <button id="mobile-menu-btn" class="md:hidden flex items-center justify-center w-10 h-10 rounded-full hover:bg-surface-container-high transition-colors" aria-label="Open menu">
                <span class="material-symbols-outlined text-on-surface">menu</span>
            </button>
        </nav>
    </header>

    <!-- Mobile Drawer Overlay -->
    <div id="mobile-overlay" class="fixed inset-0 bg-on-surface/40 backdrop-blur-sm z-[60] opacity-0 pointer-events-none transition-opacity duration-300"></div>

    <!-- Mobile Drawer -->
    <div id="mobile-drawer" class="fixed top-0 right-0 h-full w-[85%] max-w-sm bg-surface z-[70] transform translate-x-full transition-transform duration-300 ease-[cubic-bezier(0.4,0,0.2,1)] shadow-2xl">
        <div class="flex flex-col h-full">
            <div class="flex items-center justify-between p-6 border-b border-outline-variant/20">
                <span class="font-headline font-bold text-lg text-on-surface">{{ $settings['site_name'] ?? 'My Portofolio' }}</span>
                <button id="mobile-menu-close" class="flex items-center justify-center w-10 h-10 rounded-full hover:bg-surface-container-high transition-colors" aria-label="Close menu">
                    <span class="material-symbols-outlined text-on-surface">close</span>
                </button>
            </div>
            <nav class="flex flex-col gap-2 p-6 flex-1">
                <a class="flex items-center gap-4 px-4 py-3.5 rounded-xl font-headline text-base font-medium transition-all {{ request()->routeIs('home') ? 'bg-primary/10 text-primary font-bold' : 'text-on-surface-variant hover:bg-surface-container-low' }}" href="{{ route('home') }}">
                    <span class="material-symbols-outlined text-[20px]">home</span> Home
                </a>
                <a class="flex items-center gap-4 px-4 py-3.5 rounded-xl font-headline text-base font-medium transition-all {{ request()->routeIs('projects') ? 'bg-primary/10 text-primary font-bold' : 'text-on-surface-variant hover:bg-surface-container-low' }}" href="{{ route('projects') }}">
                    <span class="material-symbols-outlined text-[20px]">work</span> Projects
                </a>
                <a class="flex items-center gap-4 px-4 py-3.5 rounded-xl font-headline text-base font-medium transition-all {{ request()->routeIs('about') ? 'bg-primary/10 text-primary font-bold' : 'text-on-surface-variant hover:bg-surface-container-low' }}" href="{{ route('about') }}">
                    <span class="material-symbols-outlined text-[20px]">person</span> About
                </a>
                <a class="flex items-center gap-4 px-4 py-3.5 rounded-xl font-headline text-base font-medium transition-all {{ request()->routeIs('contact') ? 'bg-primary/10 text-primary font-bold' : 'text-on-surface-variant hover:bg-surface-container-low' }}" href="{{ route('contact') }}">
                    <span class="material-symbols-outlined text-[20px]">mail</span> Contact
                </a>
            </nav>
            <div class="p-6 border-t border-outline-variant/20">
                <a href="{{ route('contact') }}" class="btn-primary-gradient w-full px-6 py-4 rounded-xl font-bold text-center block text-base">
                    Get in Touch
                </a>
            </div>
        </div>
    </div>

    <main>
        {{ $slot }}
    </main>

    <!-- Footer -->
    <footer class="bg-surface-container-low w-full pt-16 sm:pt-20 pb-8 sm:pb-10 px-6 sm:px-8">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center gap-6 border-t border-outline-variant/20 pt-8 sm:pt-10">
            <div class="flex flex-col gap-2 text-center md:text-left">
                <span class="font-headline font-bold text-on-surface text-xl">{{ $settings['site_name'] ?? 'My Portofolio' }}</span>
                <p class="font-body text-sm leading-relaxed text-outline max-w-xs">{{ $settings['site_tagline'] ?? '' }}</p>
            </div>
            <div class="flex flex-wrap justify-center gap-6 sm:gap-8">
                @foreach($socialLinks ?? [] as $link)
                    <a class="text-outline font-body text-sm hover:text-primary transition-all" href="{{ $link->url }}" target="_blank">{{ $link->platform }}</a>
                @endforeach
            </div>
            <div class="text-outline font-body text-xs sm:text-sm text-center md:text-right">
                © {{ date('Y') }} {{ $settings['site_name'] ?? 'My Portofolio' }}. All rights reserved.
            </div>
        </div>
    </footer>

    @livewireScripts

    <!-- Mobile Menu Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const btn = document.getElementById('mobile-menu-btn');
            const closeBtn = document.getElementById('mobile-menu-close');
            const overlay = document.getElementById('mobile-overlay');
            const drawer = document.getElementById('mobile-drawer');

            function openDrawer() {
                drawer.classList.remove('translate-x-full');
                drawer.classList.add('translate-x-0');
                overlay.classList.remove('opacity-0', 'pointer-events-none');
                overlay.classList.add('opacity-100');
                document.body.style.overflow = 'hidden';
            }

            function closeDrawer() {
                drawer.classList.add('translate-x-full');
                drawer.classList.remove('translate-x-0');
                overlay.classList.add('opacity-0', 'pointer-events-none');
                overlay.classList.remove('opacity-100');
                document.body.style.overflow = '';
            }

            if (btn) btn.addEventListener('click', openDrawer);
            if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
            if (overlay) overlay.addEventListener('click', closeDrawer);
        });
    </script>
</body>
</html>
