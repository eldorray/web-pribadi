<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
    <meta name="theme-color" content="#f3f0eb" />
    <title>Admin · {{ config('app.name') }}</title>

    @php $favicon = \App\Models\SiteSetting::get('site_favicon', ''); @endphp
    @if ($favicon)
        <link rel="icon" type="image/png" href="{{ $favicon }}" />
        <link rel="apple-touch-icon" href="{{ $favicon }}" />
    @endif

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;0,900&family=Source+Serif+4:ital,wght@0,400;0,600&family=Inter+Tight:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap"
        rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="paper">
    <div class="flex min-h-screen">
        {{-- Sidebar --}}
        <aside class="admin-sidebar w-64 fixed top-0 left-0 h-full z-40 flex-col p-5 hidden lg:flex">
            <a href="{{ route('admin.dashboard') }}" class="brand mb-8">
                {{ config('app.name') }}
                <small>Admin</small>
            </a>

            <nav class="flex flex-col gap-1 flex-1">
                <a href="{{ route('admin.dashboard') }}"
                    class="admin-sidebar-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                    <span class="material-symbols-outlined text-[20px]">dashboard</span>
                    Dashboard
                </a>
                <a href="{{ route('admin.projects') }}"
                    class="admin-sidebar-link {{ request()->routeIs('admin.projects*') ? 'active' : '' }}">
                    <span class="material-symbols-outlined text-[20px]">grid_view</span>
                    Projects
                </a>
                <a href="{{ route('admin.skills') }}"
                    class="admin-sidebar-link {{ request()->routeIs('admin.skills') ? 'active' : '' }}">
                    <span class="material-symbols-outlined text-[20px]">bolt</span>
                    Skills
                </a>
                <a href="{{ route('admin.tools') }}"
                    class="admin-sidebar-link {{ request()->routeIs('admin.tools') ? 'active' : '' }}">
                    <span class="material-symbols-outlined text-[20px]">build</span>
                    Tools
                </a>
                <a href="{{ route('admin.experiences') }}"
                    class="admin-sidebar-link {{ request()->routeIs('admin.experiences') ? 'active' : '' }}">
                    <span class="material-symbols-outlined text-[20px]">timeline</span>
                    Experience
                </a>
                <a href="{{ route('admin.messages') }}"
                    class="admin-sidebar-link {{ request()->routeIs('admin.messages') ? 'active' : '' }}">
                    <span class="material-symbols-outlined text-[20px]">mail</span>
                    Messages
                    @php $unread = \App\Models\ContactMessage::unread()->count(); @endphp
                    @if ($unread > 0)
                        <span
                            class="ml-auto inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 text-[11px] font-bold rounded-full"
                            style="background: var(--color-mint); color: var(--color-ink);">
                            {{ $unread }}
                        </span>
                    @endif
                </a>
                <a href="{{ route('admin.settings') }}"
                    class="admin-sidebar-link {{ request()->routeIs('admin.settings') ? 'active' : '' }}">
                    <span class="material-symbols-outlined text-[20px]">tune</span>
                    Settings
                </a>
                <a href="{{ route('admin.social-links') }}"
                    class="admin-sidebar-link {{ request()->routeIs('admin.social-links') ? 'active' : '' }}">
                    <span class="material-symbols-outlined text-[20px]">share</span>
                    Social Links
                </a>
            </nav>

            <div class="border-t border-line pt-4 mt-4 space-y-1">
                <a href="{{ route('home') }}" class="admin-sidebar-link" target="_blank">
                    <span class="material-symbols-outlined text-[20px]">open_in_new</span>
                    View Site
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="admin-sidebar-link w-full text-left">
                        <span class="material-symbols-outlined text-[20px]">logout</span>
                        Logout
                    </button>
                </form>
            </div>
        </aside>

        {{-- Main --}}
        <div class="flex-1 lg:ml-64 w-full">
            <header
                class="bg-card border-b border-line px-5 lg:px-8 py-4 flex justify-between items-center sticky top-0 z-30"
                style="background: var(--color-card);">
                <h1 class="display display--md !text-xl">{{ $pageTitle ?? 'Dashboard' }}</h1>
                <div class="flex items-center gap-3">
                    <span class="micro hidden sm:inline">{{ auth()->user()->name ?? 'Admin' }}</span>
                    <div class="w-8 h-8 rounded-full bg-ink flex items-center justify-center text-white text-sm font-bold"
                        style="background: var(--color-ink);">
                        {{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}
                    </div>
                </div>
            </header>

            <main class="p-5 lg:p-8 pb-20 lg:pb-8">
                {{ $slot }}
            </main>
        </div>
    </div>

    @livewireScripts
</body>

</html>
