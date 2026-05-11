{{--
  Vista "Mi perfil": datos del usuario autenticado ($user, $memberSince, $lastUpdate, $breadcrumbs)
  desde ProfileController@index. Formularios envían a profile.update y profile.password (PUT).
--}}
@extends('layouts.admin', ['title' => 'Mi perfil'])

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/profile.css') }}">
@endpush

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">Mi perfil</h1>
        <p class="page-sub">Administra tu informacion personal y seguridad de la cuenta</p>
    </div>
</div>

@if(session('success'))
    <div class="alert-success" role="alert">
        <i class="ti ti-circle-check" aria-hidden="true"></i>
        {{ session('success') }}
    </div>
@endif

@if(session('error'))
    <div class="alert-error" role="alert">
        <i class="ti ti-alert-circle" aria-hidden="true"></i>
        {{ session('error') }}
    </div>
@endif

<div class="profile-grid">

    {{-- ══════════════════════
         CARD LATERAL
    ══════════════════════ --}}
    <div class="profile-card">
        <div class="profile-banner"></div>

        <div class="profile-avatar-wrap">
            {{-- Iniciales derivadas del nombre en User::initials() --}}
            <div class="profile-avatar">{{ $user->initials() }}</div>
        </div>

        <div class="profile-info">
            <div class="profile-name">{{ $user->name }}</div>
            <div class="profile-email">{{ $user->email }}</div>
            <span class="profile-role">
                <i class="ti ti-shield" style="font-size:12px" aria-hidden="true"></i>
                {{ $user->roleDisplayLabel() }}
            </span>
        </div>

        <div class="profile-details">
            <div class="detail-row">
                <div class="detail-icon">
                    <i class="ti ti-calendar" aria-hidden="true"></i>
                </div>
                <div>
                    <div class="detail-label">Miembro desde</div>
                    {{-- Texto generado desde users.created_at en el controlador --}}
                    <div class="detail-value">{{ $memberSince }}</div>
                </div>
            </div>
            <div class="detail-row">
                <div class="detail-icon">
                    <i class="ti ti-clock" aria-hidden="true"></i>
                </div>
                <div>
                    <div class="detail-label">Última actualización</div>
                    {{-- Texto generado desde users.updated_at en el controlador --}}
                    <div class="detail-value">{{ $lastUpdate }}</div>
                </div>
            </div>
        </div>
    </div>
    {{-- ══════════════════════
         FORMULARIOS
    ══════════════════════ --}}
    <div class="forms-col">

        {{-- Editar nombre --}}
        <div class="panel">
            <div class="panel-header">
                <div class="panel-header-icon">
                    <i class="ti ti-user-edit" aria-hidden="true"></i>
                </div>
                <div>
                    <div class="panel-header-title">Informacion personal</div>
                    <div class="panel-header-sub">Solo puedes editar tu nombre</div>
                </div>
            </div>

            {{-- Solo actualiza users.name; el correo no se envía ni se edita --}}
            <form method="POST" action="{{ route('profile.update') }}">
                @csrf
                @method('PUT')

                <div class="panel-body">
                    <div class="field">
                        <label class="field-label" for="name">Nombre completo</label>
                        <input
                            class="input @error('name') input-error @enderror"
                            id="name"
                            name="name"
                            type="text"
                            value="{{ old('name', $user->name) }}"
                            placeholder="Tu nombre completo"
                            required
                        >
                        @error('name')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="field">
                        <label class="field-label" for="email">Correo electronico</label>
                        {{-- Solo lectura: el cambio de correo no está expuesto en este formulario --}}
                        <input
                            class="input"
                            id="email"
                            type="email"
                            value="{{ $user->email }}"
                            disabled
                            readonly
                            autocomplete="email"
                        >
                        <span class="field-hint">
                            El correo no puede ser modificado. Contacta al administrador si necesitas cambiarlo.
                        </span>
                    </div>
                </div>

                <div class="panel-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-device-floppy" aria-hidden="true"></i>
                        Guardar cambios
                    </button>
                </div>
            </form>
        </div>

        {{-- Cambiar contrasena --}}
        <div class="panel">
            <div class="panel-header">
                <div class="panel-header-icon">
                    <i class="ti ti-lock" aria-hidden="true"></i>
                </div>
                <div>
                    <div class="panel-header-title">Cambiar contraseña</div>
                    <div class="panel-header-sub">Usa una contraseña segura de al menos 8 caracteres</div>
                </div>
            </div>

            {{-- Errores de validación se muestran con @error bajo cada campo; el envío no se bloquea en el cliente --}}
            <form method="POST" action="{{ route('profile.password') }}">
                @csrf
                @method('PUT')

                <div class="panel-body">
                    <div class="field">
                        <label class="field-label" for="current_password">Contraseña actual</label>
                        <input
                            class="input @error('current_password') input-error @enderror"
                            id="current_password"
                            name="current_password"
                            type="password"
                            placeholder="Tu contraseña actual"
                            autocomplete="current-password"
                        >
                        @error('current_password')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="pass-divider">Nueva contraseña</div>

                    <div class="field">
                        <label class="field-label" for="password">Nueva contraseña</label>
                        <input
                            class="input @error('password') input-error @enderror"
                            id="password"
                            name="password"
                            type="password"
                            placeholder="Mínimo 8 caracteres"
                            autocomplete="new-password"
                        >
                        @error('password')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="field" style="margin-bottom:0">
                        <label class="field-label" for="password_confirmation">Confirmar nueva contraseña</label>
                        <input
                            class="input @error('password_confirmation') input-error @enderror"
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            placeholder="Repite la nueva contraseña"
                            autocomplete="new-password"
                        >
                        @error('password_confirmation')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="panel-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-lock-check" aria-hidden="true"></i>
                        Actualizar contraseña
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>

@endsection