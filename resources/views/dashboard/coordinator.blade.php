@extends('layouts.app')

@section('title', 'Panel coordinador')

@section('content')
    <div class="rounded-xl border border-zinc-200 bg-white p-8 shadow-sm">
        <p class="mb-1 text-xs font-medium uppercase tracking-wide text-zinc-500">Rol</p>
        <h1 class="mb-2 text-2xl font-semibold text-zinc-900">Coordinador</h1>
        <p class="text-zinc-600">
            Sesión iniciada como <strong>{{ auth()->user()->name }}</strong> ({{ auth()->user()->email }}).
        </p>
    </div>
@endsection
