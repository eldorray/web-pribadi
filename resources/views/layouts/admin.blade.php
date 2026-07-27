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

<body class="paper" x-data="{ sidebarOpen: false }">
    <div class="flex min-h-screen">
        {{-- Mobile drawer overlay --}}
        <div x-show="sidebarOpen" x-transition.opacity @click="sidebarOpen = false"
            class="fixed inset-0 bg-black/40 z-30 lg:hidden" style="display: none;"></div>

        {{-- Sidebar (mobile drawer + desktop static) --}}
        <aside
            class="admin-sidebar w-64 fixed top-0 left-0 h-full z-40 flex flex-col p-5 transition-transform -translate-x-full lg:translate-x-0 lg:transform-none"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">
            <div class="flex items-center justify-between mb-8">
                <a href="{{ route('admin.dashboard') }}" class="brand">
                    {{ config('app.name') }}
                    <small>Admin</small>
                </a>
                <button @click="sidebarOpen = false" class="lg:hidden p-1 -mr-1" aria-label="Close menu">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <nav class="flex flex-col gap-1 flex-1 overflow-y-auto">
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
        <div class="flex-1 lg:ml-64 w-full min-w-0">
            <header
                class="bg-card border-b border-line px-4 sm:px-5 lg:px-8 py-3 sm:py-4 flex justify-between items-center sticky top-0 z-20"
                style="background: var(--color-card);">
                <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                    <button @click="sidebarOpen = true" class="lg:hidden p-1 -ml-1 shrink-0" aria-label="Open menu">
                        <span class="material-symbols-outlined">menu</span>
                    </button>
                    <h1 class="display display--md !text-base sm:!text-xl truncate">{{ $pageTitle ?? 'Dashboard' }}
                    </h1>
                </div>
                <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                    <span class="micro hidden sm:inline">{{ auth()->user()->name ?? 'Admin' }}</span>
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-sm font-bold shrink-0"
                        style="background: var(--color-ink);">
                        {{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}
                    </div>
                </div>
            </header>

            <main class="p-4 sm:p-5 lg:p-8 pb-20 lg:pb-8">
                {{ $slot }}
            </main>
        </div>
    </div>

    {{-- Alpine comes bundled with Livewire; loading the CDN build too gives you
         two Alpine instances and breaks x-data bindings. --}}
    @livewireScripts
</body>

</html>
