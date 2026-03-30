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
    <header class="fixed top-0 left-0 right-0 z-50 flex justify-center w-full">
        <nav class="glass-nav rounded-full px-6 py-3 mt-6 mx-auto w-fit flex items-center gap-10">
            <a href="{{ route('home') }}" class="text-xl font-black text-on-surface font-headline tracking-tight">
                {{ $settings['site_name'] ?? 'Architect.folio' }}
            </a>
            <div class="hidden md:flex items-center gap-8 font-headline text-sm font-medium tracking-tight">
                <a class="{{ request()->routeIs('home') ? 'text-primary font-bold border-b-2 border-primary pb-1' : 'text-outline hover:text-on-surface' }} hover:scale-105 active:scale-95 transition-all duration-300" href="{{ route('home') }}">Home</a>
                <a class="{{ request()->routeIs('projects') ? 'text-primary font-bold border-b-2 border-primary pb-1' : 'text-outline hover:text-on-surface' }} hover:scale-105 active:scale-95 transition-all duration-300" href="{{ route('projects') }}">Projects</a>
                <a class="{{ request()->routeIs('about') ? 'text-primary font-bold border-b-2 border-primary pb-1' : 'text-outline hover:text-on-surface' }} hover:scale-105 active:scale-95 transition-all duration-300" href="{{ route('about') }}">About</a>
                <a class="{{ request()->routeIs('contact') ? 'text-primary font-bold border-b-2 border-primary pb-1' : 'text-outline hover:text-on-surface' }} hover:scale-105 active:scale-95 transition-all duration-300" href="{{ route('contact') }}">Contact</a>
            </div>
            <a href="{{ route('contact') }}" class="btn-primary-gradient px-5 py-2 rounded-full text-sm font-bold">
                Get in Touch
            </a>
        </nav>
    </header>

    <main>
        {{ $slot }}
    </main>

    <!-- Footer -->
    <footer class="bg-surface-container-low w-full pt-20 pb-10 px-8">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center gap-6 border-t border-outline-variant/20 pt-10">
            <div class="flex flex-col gap-2">
                <span class="font-headline font-bold text-on-surface text-xl">{{ $settings['site_name'] ?? 'Architect.folio' }}</span>
                <p class="font-body text-sm leading-relaxed text-outline max-w-xs">{{ $settings['site_tagline'] ?? '' }}</p>
            </div>
            <div class="flex flex-wrap justify-center gap-8">
                @foreach($socialLinks ?? [] as $link)
                    <a class="text-outline font-body text-sm hover:text-primary transition-all" href="{{ $link->url }}" target="_blank">{{ $link->platform }}</a>
                @endforeach
            </div>
            <div class="text-outline font-body text-sm">
                © {{ date('Y') }} {{ $settings['site_name'] ?? 'Architect.folio' }}. All rights reserved.
            </div>
        </div>
    </footer>

    @livewireScripts
</body>
</html>
