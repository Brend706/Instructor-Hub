@extends('layouts.app')

@section('title', 'Iniciar sesión')

@section('content')
    <div class="mx-auto max-w-md rounded-xl border border-zinc-200 bg-white p-8 shadow-sm">
        <h1 class="mb-1 text-xl font-semibold text-zinc-900">Iniciar sesión</h1>
        <p class="mb-6 text-sm text-zinc-600">Ingresa con el correo y la contraseña registrados en el sistema.</p>

        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-4">
            @csrf
            <div class="flex flex-col gap-1">
                <label for="email" class="text-sm font-medium text-zinc-700">Correo electrónico</label>
                <input
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    required
                    autocomplete="username"
                    class="rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none ring-zinc-400 focus:border-zinc-500 focus:ring-2"
                >
            </div>
            <div class="flex flex-col gap-1">
                <label for="password" class="text-sm font-medium text-zinc-700">Contraseña</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    required
                    autocomplete="current-password"
                    class="rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none ring-zinc-400 focus:border-zinc-500 focus:ring-2"
                >
                <label class="mt-1 flex cursor-pointer items-center gap-2 text-sm text-zinc-600 select-none">
                    <input
                        type="checkbox"
                        id="show-password"
                        class="rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500"
                    >
                    Ver contraseña
                </label>
            </div>
            <label class="flex items-center gap-2 text-sm text-zinc-700">
                <input type="checkbox" name="remember" value="1" class="rounded border-zinc-300" @checked(old('remember'))>
                Recordarme en este equipo
            </label>
            <button
                type="submit"
                class="mt-2 rounded-lg bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800"
            >
                Entrar
            </button>
        </form>
    </div>
    <script>
        document.getElementById('show-password')?.addEventListener('change', function () {
            const input = document.getElementById('password');
            if (input) {
                input.type = this.checked ? 'text' : 'password';
            }
        });
    </script>
@endsection
