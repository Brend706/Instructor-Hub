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
            <div class="profile-avatar">JM</div>
        </div>

        <div class="profile-info">
            <div class="profile-name">José Martínez</div>
            <div class="profile-email">j.martinez@fica.edu.sv</div>
            <span class="profile-role">
                <i class="ti ti-shield" style="font-size:12px" aria-hidden="true"></i>
                Administrador
            </span>
        </div>

        <div class="profile-details">
            <div class="detail-row">
                <div class="detail-icon">
                    <i class="ti ti-calendar" aria-hidden="true"></i>
                </div>
                <div>
                    <div class="detail-label">Miembro desde</div>
                    <div class="detail-value">Enero 2024</div>
                </div>
            </div>
            <div class="detail-row">
                <div class="detail-icon">
                    <i class="ti ti-clock" aria-hidden="true"></i>
                </div>
                <div>
                    <div class="detail-label">Ultimo actualizacion</div>
                    <!-- esta fecha y hora seria el valor de updated_at de la tabla de usuarios -->
                    <div class="detail-value">Hoy, 9:42 AM</div>
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

            {{-- Al integrar backend: action="{{ route('admin.profile.update') }}" --}}
            <form method="POST" action="#">
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
                            value="{{ old('name', auth()->user()->name ?? '') }}"
                            placeholder="Tu nombre completo"
                            required
                        >
                        @error('name')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="field">
                        <label class="field-label" for="email">Correo electronico</label>
                        <input
                            class="input"
                            id="email"
                            type="email"
                            value="{{ auth()->user()->email ?? '' }}"
                            disabled
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
                    <div class="panel-header-title">Cambiar contrasena</div>
                    <div class="panel-header-sub">Usa una contrasena segura de al menos 8 caracteres</div>
                </div>
            </div>

            {{-- Al integrar backend: action="{{ route('admin.profile.password') }}" --}}
            <form method="POST" action="#">
                @csrf
                @method('PUT')

                <div class="panel-body">
                    <div class="field">
                        <label class="field-label" for="current_password">Contrasena actual</label>
                        <input
                            class="input @error('current_password') input-error @enderror"
                            id="current_password"
                            name="current_password"
                            type="password"
                            placeholder="Tu contrasena actual"
                            required
                        >
                        @error('current_password')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="pass-divider">Nueva contrasena</div>

                    <div class="field">
                        <label class="field-label" for="password">Nueva contrasena</label>
                        <input
                            class="input @error('password') input-error @enderror"
                            id="password"
                            name="password"
                            type="password"
                            placeholder="Minimo 8 caracteres"
                            required
                        >
                        @error('password')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="field" style="margin-bottom:0">
                        <label class="field-label" for="password_confirmation">Confirmar nueva contrasena</label>
                        <input
                            class="input"
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            placeholder="Repite la nueva contrasena"
                            required
                        >
                    </div>
                </div>

                <div class="panel-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-lock-check" aria-hidden="true"></i>
                        Actualizar contrasena
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>

@endsection