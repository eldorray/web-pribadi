{{-- Shared shell for /chat and the PIN screen. Never indexed, never linked
     from the public portfolio navigation. --}}
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, viewport-fit=cover, interactive-widget=resizes-content" />
    <meta name="robots" content="noindex, nofollow" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="color-scheme" content="light dark" />
    <meta name="theme-color" media="(prefers-color-scheme: light)" content="#f7f7f5" />
    <meta name="theme-color" media="(prefers-color-scheme: dark)" content="#151714" />
    <title>{{ $title ?? 'Chat' }} · {{ config('app.name') }}</title>

    @if (!empty($favicon))
        <link rel="icon" href="{{ $favicon }}" />
    @endif

    <script>
        // Called by the page's x-data too, to read the state back.
        window.chatSidebarCollapsed = () => {
            let collapsed = false;

            try {
                collapsed = localStorage.getItem('chat-sidebar') === 'collapsed';
            } catch (_) {}

            document.documentElement.classList.toggle('chat-sidebar-collapsed', collapsed);

            return collapsed;
        };

        {
            const restoreHtmlClasses = () => {
                try {
                    document.documentElement.classList.toggle('dark', localStorage.getItem('color-scheme') === 'dark');
                } catch (_) {}

                window.chatSidebarCollapsed();
            };

            // Before first paint, and again whenever wire:navigate replaces the
            // attributes on <html>, so neither dark mode nor the sidebar flickers.
            restoreHtmlClasses();
            document.addEventListener('livewire:navigating', (event) => event.detail.onSwap(restoreHtmlClasses));
        }
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="chat-body">
    {{ $slot }}

    @livewireScripts
</body>

</html>
