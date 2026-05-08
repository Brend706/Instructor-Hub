<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name', 'Instructor Hub'))</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @php
        $viteReady = file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot'));
    @endphp
    @if ($viteReady)
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
    @stack('styles') {{-- Para estilos específicos de cada vista --}}
</head>
<body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased">

    @hasSection('fullpage')
        @yield('fullpage')
    @else
        <main class="mx-auto max-w-4xl px-4 py-8">
            @yield('content')
        </main>
    @endif

    @stack('scripts')
</body>
</html>
