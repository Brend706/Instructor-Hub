@extends('layouts.coordinator', ['title' => 'Dashboard'])

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/layouts/dashboardCoord.css') }}">
@endpush

@section('content')

<div class="cycle-header">
    <div>
        <h1 class="page-title">Buen día, Karla 👋</h1>
        <p class="page-sub">Resumen de tu coordinación — Ing. Sistemas</p>
    </div>
    <span class="cycle-badge">
        <i class="ti ti-calendar" style="font-size:13px" aria-hidden="true"></i>
        Ciclo 01-2026
    </span>
</div>

{{-- STATS --}}
<div class="stats-grid">

    <div class="stat-card">
        <div class="stat-icon" style="background:var(--accent-50)">
            <i class="ti ti-user-check" style="color:var(--accent)" aria-hidden="true"></i>
        </div>
        <div class="stat-label">Mis instructores</div>
        <div class="stat-value">8</div>
        <div class="stat-footer">
            <span class="tag tag-success">6 activos</span>
            <span style="color:var(--text-muted)">este ciclo</span>
        </div>
        <div class="stat-bg"><i class="ti ti-user-check"></i></div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background:var(--primary-50)">
            <i class="ti ti-books" style="color:var(--primary)" aria-hidden="true"></i>
        </div>
        <div class="stat-label">Grupos activos</div>
        <div class="stat-value">5</div>
        <div class="stat-footer">
            <span class="tag tag-info">Ciclo 01-2026</span>
        </div>
        <div class="stat-bg"><i class="ti ti-books"></i></div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background:#EAF3DE">
            <i class="ti ti-calendar-event" style="color:#3B6D11" aria-hidden="true"></i>
        </div>
        <div class="stat-label">Instructorías del ciclo</div>
        <div class="stat-value">24</div>
        <div class="stat-footer">
            <span class="tag tag-success">+4 esta semana</span>
        </div>
        <div class="stat-bg"><i class="ti ti-calendar-event"></i></div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background:#FEF9C3">
            <i class="ti ti-clipboard-check" style="color:#854D0E" aria-hidden="true"></i>
        </div>
        <div class="stat-label">Sesiones registradas</div>
        <div class="stat-value">18</div>
        <div class="stat-footer">
            <span class="tag tag-warning">6 pendientes</span>
        </div>
        <div class="stat-bg"><i class="ti ti-clipboard-check"></i></div>
    </div>

</div>

{{-- FILA: instructores + grupos --}}
<div class="row-2">

    {{-- Mis instructores --}}
    <div class="panel">
        <div class="panel-head">
            <div>
                <div class="panel-title">Mis instructores</div>
                <div class="panel-sub">Asignados a tu coordinación</div>
            </div>
            <a href="#" class="panel-action">Ver todos ↗</a>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Instructor</th>
                    <th>Grupo asignado</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px">
                            <div class="avatar-sm" style="background:var(--accent)">AM</div>
                            <div class="td-main">Ana Mejía</div>
                        </div>
                    </td>
                    <td>Programación I</td>
                    <td><span class="badge badge-success"><span class="status-dot dot-active"></span>Activo</span></td>
                </tr>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px">
                            <div class="avatar-sm" style="background:var(--primary)">CR</div>
                            <div class="td-main">Carlos Rivas</div>
                        </div>
                    </td>
                    <td>Cálculo I</td>
                    <td><span class="badge badge-success"><span class="status-dot dot-active"></span>Activo</span></td>
                </tr>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px">
                            <div class="avatar-sm" style="background:var(--primary-400)">LP</div>
                            <div class="td-main">Luisa Pérez</div>
                        </div>
                    </td>
                    <td><span class="no-assign">Sin asignar</span></td>
                    <td><span class="badge badge-warning"><span class="status-dot dot-inactive"></span>Inactivo</span></td>
                </tr>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px">
                            <div class="avatar-sm" style="background:var(--primary-700)">MG</div>
                            <div class="td-main">Miguel García</div>
                        </div>
                    </td>
                    <td>Física I</td>
                    <td><span class="badge badge-success"><span class="status-dot dot-active"></span>Activo</span></td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Grupos del ciclo --}}
    <div class="panel">
        <div class="panel-head">
            <div>
                <div class="panel-title">Grupos del ciclo</div>
                <div class="panel-sub">Ciclo 01-2026 activo</div>
            </div>
            <a href="#" class="panel-action">Ver todos ↗</a>
        </div>

        <div class="group-item">
            <div class="group-icon"><i class="ti ti-code" aria-hidden="true"></i></div>
            <div>
                <div class="group-name">Programación I</div>
                <div class="group-meta">Lun y Mié 7-9am · Aula 204</div>
            </div>
            <div class="group-right">
                <span class="badge badge-info"><i class="ti ti-building" style="font-size:10px"></i>Presencial</span>
                <div class="group-students">28 estudiantes</div>
            </div>
        </div>

        <div class="group-item">
            <div class="group-icon"><i class="ti ti-math" aria-hidden="true"></i></div>
            <div>
                <div class="group-name">Cálculo I</div>
                <div class="group-meta">Mar y Jue 9-11am · En línea</div>
            </div>
            <div class="group-right">
                <span class="badge badge-accent"><i class="ti ti-video" style="font-size:10px"></i>En línea</span>
                <div class="group-students">35 estudiantes</div>
            </div>
        </div>

        <div class="group-item">
            <div class="group-icon"><i class="ti ti-atom" aria-hidden="true"></i></div>
            <div>
                <div class="group-name">Física I</div>
                <div class="group-meta">Vie 7-10am · Aula 101</div>
            </div>
            <div class="group-right">
                <span class="badge badge-info"><i class="ti ti-building" style="font-size:10px"></i>Presencial</span>
                <div class="group-students">22 estudiantes</div>
            </div>
        </div>

        <div class="group-item">
            <div class="group-icon" style="background:var(--accent-50)">
                <i class="ti ti-books" style="color:var(--accent)" aria-hidden="true"></i>
            </div>
            <div>
                <div class="group-name">Álgebra Lineal</div>
                <div class="group-meta">Lun 9-11am · En línea</div>
            </div>
            <div class="group-right">
                <span class="badge badge-accent"><i class="ti ti-video" style="font-size:10px"></i>En línea</span>
                <div class="group-students">30 estudiantes</div>
            </div>
        </div>

    </div>

</div>

@endsection