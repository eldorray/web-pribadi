<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Login | {{ config('app.name') }}</title>
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Epilogue:wght@400;500;700;800;900&family=Inter:wght@100..900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-surface text-on-surface antialiased min-h-screen flex items-center justify-center px-6">
    <div class="w-full max-w-md">
        <div class="text-center mb-10">
            <a href="{{ route('home') }}" class="text-2xl font-black font-headline text-on-surface">Architect.folio</a>
            <p class="text-on-surface-variant mt-2">Sign in to your admin panel</p>
        </div>

        <div class="bg-surface-container-lowest rounded-2xl p-8 shadow-ambient">
            @if($errors->any())
                <div class="mb-6 bg-error/10 text-error p-4 rounded-xl text-sm font-medium">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-6">
                @csrf
                <div>
                    <label class="font-label text-xs uppercase tracking-widest text-outline font-bold block mb-2">Email</label>
                    <input name="email" type="email" value="{{ old('email') }}" required autofocus
                           class="w-full bg-surface-container-low border-none rounded-lg px-6 py-4 focus:ring-2 focus:ring-primary/20 focus:bg-surface-container-lowest transition-all outline-none"/>
                </div>
                <div>
                    <label class="font-label text-xs uppercase tracking-widest text-outline font-bold block mb-2">Password</label>
                    <input name="password" type="password" required
                           class="w-full bg-surface-container-low border-none rounded-lg px-6 py-4 focus:ring-2 focus:ring-primary/20 focus:bg-surface-container-lowest transition-all outline-none"/>
                </div>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input name="remember" type="checkbox" class="w-4 h-4 rounded text-primary focus:ring-primary/20"/>
                    <span class="text-sm text-on-surface-variant">Remember me</span>
                </label>
                <button type="submit" class="w-full btn-primary-gradient px-6 py-4 rounded-xl font-headline font-bold text-lg">
                    Sign In
                </button>
            </form>
        </div>

        <p class="text-center text-outline text-sm mt-8">
            <a href="{{ route('home') }}" class="hover:text-primary transition-colors">← Back to website</a>
        </p>
    </div>
</body>
</html>
