<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
    <meta name="theme-color" content="#f3f4f6" />
    <title>Sign in — {{ config('app.name') }}</title>

    @php $favicon = \App\Models\SiteSetting::get('site_favicon', ''); @endphp
    @if ($favicon)
        <link rel="icon" type="image/png" href="{{ $favicon }}" />
        <link rel="apple-touch-icon" href="{{ $favicon }}" />
    @endif
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&display=swap"
        rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-[440px]">
        <div class="text-center mb-8 animate-reveal delay-100">
            <a href="{{ route('home') }}" class="inline-flex items-center gap-2.5">
                <div class="brand-mark__avatar">
                    <div class="w-full h-full flex items-center justify-center font-bold"
                        style="background: var(--color-card-soft); color: var(--color-ink);">
                        {{ strtoupper(substr(config('app.name'), 0, 1)) }}
                    </div>
                </div>
                <span class="font-semibold text-base" style="color: var(--color-ink);">{{ config('app.name') }}</span>
            </a>
            <p class="micro mt-3">Studio Panel</p>
        </div>

        <div class="surface p-8 sm:p-10 animate-reveal delay-200">
            <h1 class="display display--md mb-2">Welcome back.</h1>
            <p class="text-sm mb-7" style="color: var(--color-ink-4);">Sign in to manage your studio.</p>

            @if ($errors->any())
                <div class="rounded-lg p-3 mb-5 text-sm border"
                    style="background: var(--color-error-container); color: var(--color-on-error-container); border-color: rgba(185,28,28,0.2);">
                    — {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div class="field">
                    <label class="field-label" for="login-email">Email</label>
                    <input id="login-email" name="email" type="email" value="{{ old('email') }}"
                        class="field-input" required autofocus autocomplete="email" placeholder="you@studio.com" />
                </div>
                <div class="field">
                    <label class="field-label" for="login-password">Password</label>
                    <input id="login-password" name="password" type="password" class="field-input" required
                        autocomplete="current-password" placeholder="••••••••" />
                </div>

                <label class="flex items-center gap-2 cursor-pointer select-none mb-6 mt-2">
                    <input name="remember" type="checkbox" class="w-4 h-4 rounded" />
                    <span class="text-sm" style="color: var(--color-ink-3);">Remember me on this device</span>
                </label>

                <button type="submit" class="btn btn-embossed w-full">
                    Sign in
                    <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                </button>
            </form>
        </div>

        <p class="text-center mt-6">
            <a href="{{ route('home') }}" class="inline-flex items-center gap-1 text-sm hover:underline"
                style="color: var(--color-ink-4);">
                <span class="material-symbols-outlined text-[14px]">arrow_back</span>
                Back to website
            </a>
        </p>
    </div>
</body>

</html>
