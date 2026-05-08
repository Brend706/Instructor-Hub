@extends('layouts.app')

@section('title', 'Iniciar sesión — Instructor Hub')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/auth/login.css') }}">
@endpush

@section('fullpage')

<div class="login-grid">

    {{-- ═══════════════════════════════════
         PANEL IZQUIERDO — Presentación
    ═══════════════════════════════════ --}}
    <div class="login-left">
        <div class="login-left-top">

            {{-- Marca --}}
            <div class="brand">
                <div class="brand-mark">IH</div>
                <div>
                    <div class="brand-name">Instructor Hub</div>
                    <div class="brand-sub">Sistema de gestión de instructorías</div>
                </div>
            </div>

            {{-- Titular --}}
            <h1 class="left-headline">
                Bienvenido al<br>sistema de <span>instructorías</span>
            </h1>
            <p class="left-desc">
                Gestiona instructores, coordina sesiones y da seguimiento
                a la asistencia desde un solo lugar.
            </p>

            {{-- Features --}}
            <div class="features">
                <div class="feat">
                    <div class="feat-icon">
                        <i class="ti ti-user-check" aria-hidden="true"></i>
                    </div>
                    <span class="feat-text">Registro y gestión de instructores</span>
                </div>
                <div class="feat">
                    <div class="feat-icon">
                        <i class="ti ti-calendar-event" aria-hidden="true"></i>
                    </div>
                    <span class="feat-text">Control de sesiones presenciales y en línea</span>
                </div>
                <div class="feat">
                    <div class="feat-icon">
                        <i class="ti ti-clipboard-check" aria-hidden="true"></i>
                    </div>
                    <span class="feat-text">Registro de asistencia en tiempo real</span>
                </div>
                <div class="feat">
                    <div class="feat-icon">
                        <i class="ti ti-chart-bar" aria-hidden="true"></i>
                    </div>
                    <span class="feat-text">Reportes y estadísticas para administración</span>
                </div>
            </div>
        </div>

        <div class="login-left-bottom">
            <div class="faculty-badge">
                <i class="ti ti-building" aria-hidden="true"></i>
                Facultad de Informática y Ciencias Aplicadas
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════
         PANEL DERECHO — Formulario
    ═══════════════════════════════════ --}}
    <div class="login-right">
        <div class="form-wrap">

            <div class="form-header">
                <h2 class="form-title">Iniciar sesión</h2>
                <p class="form-sub">
                    Ingresa con el correo y contraseña<br>registrados en el sistema.
                </p>
            </div>

            {{-- Error --}}
            @if ($errors->any())
                <div class="error-box" role="alert">
                    <i class="ti ti-alert-circle" aria-hidden="true"></i>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            {{-- Formulario --}}
            <form method="POST" action="{{ route('login') }}">
                @csrf

                {{-- Email --}}
                <div class="field">
                    <label class="field-label" for="email">Correo electrónico</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        placeholder="usuario@fica.edu.sv"
                        required
                        autocomplete="username"
                        class="input @error('email') input-error @enderror"
                    >
                </div>

                {{-- Contraseña --}}
                <div class="field">
                    <label class="field-label" for="password">Contraseña</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        placeholder="••••••••"
                        required
                        autocomplete="current-password"
                        class="input @error('password') input-error @enderror"
                    >
                    <label class="show-pass" for="showPassword">
                        <input type="checkbox" id="showPassword"> Mostrar contraseña
                    </label>
                </div>

                {{-- Recordarme --}}
                <label class="remember">
                    <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                    Recordarme en este equipo
                </label>

                {{-- Botón --}}
                <button type="submit" class="btn-submit">
                    <i class="ti ti-login" aria-hidden="true"></i>
                    Entrar al sistema
                </button>

            </form>

            {{-- Footer --}}
            <div class="footer-form">
                <strong>Instructor Hub</strong> · Desarrollado por <strong>Grupo CBA</strong><br>
                Facultad de Informática y Ciencias Aplicadas
            </div>

        </div>
    </div>

</div>

@endsection

@push('scripts')
<script>
    document.getElementById('showPassword')?.addEventListener('change', function () {
        const input = document.getElementById('password');
        if (input) input.type = this.checked ? 'text' : 'password';
    });
</script>
@endpush