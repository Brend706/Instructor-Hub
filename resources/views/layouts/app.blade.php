<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name', 'Instructor Hub'))</title>
    @php
        $viteReady = file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot'));
    @endphp
    @if ($viteReady)
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
</head>
<body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased">
    <header class="border-b border-zinc-200 bg-white">
        <div class="mx-auto flex max-w-4xl items-center justify-between px-4 py-3">
            <a href="{{ url('/') }}" class="font-semibold text-zinc-900">Instructor Hub</a>
            @auth
                <form method="POST" action="{{ route('logout') }}" class="m-0">
                    @csrf
                    <button type="submit" class="cursor-pointer text-sm font-medium text-red-600 hover:text-red-700">
                        Cerrar sesión
                    </button>
                </form>
            @endauth
        </div>
    </header>
    <main class="mx-auto max-w-4xl px-4 py-8">
        @yield('content')
    </main>
</body>
</html>
