{{-- Shared shell for /chat and the PIN screen. Never indexed, never linked
     from the public portfolio navigation. --}}
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
    <meta name="robots" content="noindex, nofollow" />
    <meta name="color-scheme" content="light" />
    <meta name="theme-color" content="#f3f4f6" />
    <title>{{ $title ?? 'Chat' }} · {{ config('app.name') }}</title>

    @if (!empty($favicon))
        <link rel="icon" href="{{ $favicon }}" />
    @endif

    <script>
        try {
            const dark = localStorage.getItem('color-scheme') === 'dark';
            document.documentElement.classList.toggle('dark', dark);
        } catch (_) {}
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="chat-body">
    {{ $slot }}

    @livewireScripts
</body>

</html>
