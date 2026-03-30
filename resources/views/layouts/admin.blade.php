<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin | {{ config('app.name') }}</title>

    <link href="https://fonts.googleapis.com" rel="preconnect" />
    <link crossorigin href="https://fonts.gstatic.com" rel="preconnect" />
    <link
        href="https://fonts.googleapis.com/css2?family=Epilogue:wght@400;500;700;800;900&family=Inter:wght@100..900&display=swap"
        rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="bg-surface text-on-surface antialiased">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside class="admin-sidebar w-64 fixed top-0 left-0 h-full z-40 flex flex-col p-6">
            <a href="{{ route('admin.dashboard') }}"
                class="text-xl font-black font-headline text-on-surface mb-10 block">
                My Portofolio
                <span class="block text-xs font-normal text-outline font-body">Admin Panel</span>
            </a>

            <nav class="flex flex-col gap-1 flex-1">
                <a href="{{ route('admin.dashboard') }}"
                    class="admin-sidebar-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                    <span class="material-symbols-outlined text-xl">dashboard</span>
                    Dashboard
                </a>
                <a href="{{ route('admin.projects') }}"
                    class="admin-sidebar-link {{ request()->routeIs('admin.projects*') ? 'active' : '' }}">
                    <span class="material-symbols-outlined text-xl">work</span>
                    Projects
                </a>
                <a href="{{ route('admin.skills') }}"
                    class="admin-sidebar-link {{ request()->routeIs('admin.skills') ? 'active' : '' }}">
                    <span class="material-symbols-outlined text-xl">psychology</span>
                    Skills
                </a>
                <a href="{{ route('admin.experiences') }}"
                    class="admin-sidebar-link {{ request()->routeIs('admin.experiences') ? 'active' : '' }}">
                    <span class="material-symbols-outlined text-xl">timeline</span>
                    Experience
                </a>
                <a href="{{ route('admin.messages') }}"
                    class="admin-sidebar-link {{ request()->routeIs('admin.messages') ? 'active' : '' }}">
                    <span class="material-symbols-outlined text-xl">mail</span>
                    Messages
                    @php $unread = \App\Models\ContactMessage::unread()->count(); @endphp
                    @if ($unread > 0)
                        <span
                            class="ml-auto bg-error text-on-error text-xs font-bold px-2 py-0.5 rounded-full">{{ $unread }}</span>
                    @endif
                </a>
                <a href="{{ route('admin.settings') }}"
                    class="admin-sidebar-link {{ request()->routeIs('admin.settings') ? 'active' : '' }}">
                    <span class="material-symbols-outlined text-xl">settings</span>
                    Settings
                </a>
                <a href="{{ route('admin.social-links') }}"
                    class="admin-sidebar-link {{ request()->routeIs('admin.social-links') ? 'active' : '' }}">
                    <span class="material-symbols-outlined text-xl">share</span>
                    Social Links
                </a>
            </nav>

            <div class="border-t border-outline-variant/20 pt-4 mt-4">
                <a href="{{ route('home') }}" class="admin-sidebar-link" target="_blank">
                    <span class="material-symbols-outlined text-xl">open_in_new</span>
                    View Site
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="admin-sidebar-link w-full text-left">
                        <span class="material-symbols-outlined text-xl">logout</span>
                        Logout
                    </button>
                </form>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 ml-64">
            <header
                class="bg-surface-container-lowest border-b border-outline-variant/20 px-8 py-5 flex justify-between items-center sticky top-0 z-30">
                <h1 class="font-headline text-xl font-bold">{{ $pageTitle ?? 'Dashboard' }}</h1>
                <div class="flex items-center gap-4">
                    <span class="text-sm text-outline">{{ auth()->user()->name ?? 'Admin' }}</span>
                    <div
                        class="w-8 h-8 bg-primary rounded-full flex items-center justify-center text-on-primary text-sm font-bold">
                        {{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}
                    </div>
                </div>
            </header>

            <main class="p-8">
                {{ $slot }}
            </main>
        </div>
    </div>

    @livewireScripts
</body>

</html>
