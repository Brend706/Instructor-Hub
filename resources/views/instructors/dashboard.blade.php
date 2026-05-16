@extends('layouts.' . (auth()->user()->roleSlug() ?? 'admin'), ['title' => 'Dashboard'])

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/layouts/dashboardInstructor.css') }}">
@endpush

@section('content')

<div class="page-header">
    <div>
        <div class="page-title">Buen día, Lucia 👋</div>
        <div class="page-sub">Resumen de tu instructoría — Ciclo 01-2026</div>
    </div>
    <button class="btn btn-primary">
        <i class="ti ti-player-play"></i> Iniciar sesión
    </button>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background:var(--primary-50)">
            <i class="ti ti-users" style="color:var(--primary)"></i>
        </div>
        <div class="stat-label">Estudiantes en mi grupo</div>
        <div class="stat-value">28</div>
        <div><span class="tag tag-blue">Ciclo activo</span></div>
        <div class="stat-bg"><i class="ti ti-users"></i></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#EBF2FB">
            <i class="ti ti-calendar-check" style="color:#4C8FD4"></i>
        </div>
        <div class="stat-label">Sesiones realizadas</div>
        <div class="stat-value">12</div>
        <div><span class="tag tag-light">Este ciclo</span></div>
        <div class="stat-bg"><i class="ti ti-calendar-check"></i></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:var(--primary-50)">
            <i class="ti ti-clipboard-check" style="color:var(--primary)"></i>
        </div>
        <div class="stat-label">Asistencia promedio</div>
        <div class="stat-value">87%</div>
        <div><span class="tag tag-blue">Buen nivel</span></div>
        <div class="stat-bg"><i class="ti ti-clipboard-check"></i></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#FEF9C3">
            <i class="ti ti-calendar-x" style="color:#854D0E"></i>
        </div>
        <div class="stat-label">Sesiones pendientes</div>
        <div class="stat-value">3</div>
        <div><span class="tag tag-warning">Por registrar</span></div>
        <div class="stat-bg"><i class="ti ti-calendar-x"></i></div>
    </div>
</div>

<div class="active-group">
    <div class="active-group-header">
        <div>
            <span class="active-label"><span class="active-dot"></span>Grupo activo — 01-2026</span>
            <div class="group-name" style="margin-top:6px">Programación I</div>
            <div class="group-sub">Ing. Roberto Chávez · PRG101</div>
        </div>
        <button class="btn btn-primary" style="font-size:12px;padding:7px 14px">
            <i class="ti ti-player-play"></i> Iniciar sesión
        </button>
    </div>
    <div class="active-group-body">
        <div>
            <div class="detail-label">Horario</div>
            <div class="detail-value">Lun y Mié</div>
            <div class="detail-sub">7:00 — 9:00am</div>
        </div>
        <div>
            <div class="detail-label">Modalidad</div>
            <div class="detail-value">Presencial</div>
            <div class="detail-sub">Aula 204</div>
        </div>
        <div>
            <div class="detail-label">Estudiantes</div>
            <div class="detail-value">28 inscritos</div>
            <div class="detail-sub">Prom. 25 asistentes</div>
        </div>
        <div>
            <div class="detail-label">Última sesión</div>
            <div class="detail-value">Hace 2 días</div>
            <div class="detail-sub">26 asistentes</div>
        </div>
    </div>
</div>

<div class="row-2">
    <div class="panel">
        <div class="panel-head">
            <div>
                <div class="panel-title">Estudiantes del grupo</div>
                <div class="panel-sub">Programación I — 01-2026</div>
            </div>
            <a href="#" class="panel-action">Ver todos ↗</a>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Estudiante</th>
                    <th>Carné</th>
                    <th>Asistencia</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:7px">
                            <div class="avatar-xs">RA</div>
                            <span class="td-main">Roberto Alemán</span>
                        </div>
                    </td>
                    <td style="font-size:11px;font-family:monospace">2021001</td>
                    <td><span class="badge badge-blue">92%</span></td>
                </tr>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:7px">
                            <div class="avatar-xs" style="background:var(--primary)">SM</div>
                            <span class="td-main">Sofía Martínez</span>
                        </div>
                    </td>
                    <td style="font-size:11px;font-family:monospace">2021042</td>
                    <td><span class="badge badge-blue">88%</span></td>
                </tr>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:7px">
                            <div class="avatar-xs" style="background:var(--primary-700)">DP</div>
                            <span class="td-main">Diego Portillo</span>
                        </div>
                    </td>
                    <td style="font-size:11px;font-family:monospace">2020088</td>
                    <td><span class="badge badge-warn">61%</span></td>
                </tr>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:7px">
                            <div class="avatar-xs" style="background:#4C8FD4">FC</div>
                            <span class="td-main">Fernanda Cruz</span>
                        </div>
                    </td>
                    <td style="font-size:11px;font-family:monospace">2021015</td>
                    <td><span class="badge badge-blue">95%</span></td>
                </tr>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:7px">
                            <div class="avatar-xs" style="background:#854D0E">LH</div>
                            <span class="td-main">Luis Hernández</span>
                        </div>
                    </td>
                    <td style="font-size:11px;font-family:monospace">2022033</td>
                    <td><span class="badge badge-warn">58%</span></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="panel">
        <div class="panel-head">
            <div>
                <div class="panel-title">Historial de grupos</div>
                <div class="panel-sub">Ciclos anteriores</div>
            </div>
            <a href="#" class="panel-action">Ver todos ↗</a>
        </div>
        <div class="history-item">
            <div class="history-icon"><i class="ti ti-books"></i></div>
            <div>
                <div class="history-name">Programación I</div>
                <div class="history-meta">Lun y Mié · Presencial</div>
            </div>
            <div class="history-right">
                <span class="cycle-tag">02-2025</span>
                <div class="history-students">30 estudiantes</div>
            </div>
        </div>
        <div class="history-item">
            <div class="history-icon"><i class="ti ti-books"></i></div>
            <div>
                <div class="history-name">Programación I</div>
                <div class="history-meta">Mar y Jue · En línea</div>
            </div>
            <div class="history-right">
                <span class="cycle-tag">01-2025</span>
                <div class="history-students">25 estudiantes</div>
            </div>
        </div>
        <div class="history-item">
            <div class="history-icon"><i class="ti ti-books"></i></div>
            <div>
                <div class="history-name">Programación I</div>
                <div class="history-meta">Vie · Presencial</div>
            </div>
            <div class="history-right">
                <span class="cycle-tag">02-2024</span>
                <div class="history-students">22 estudiantes</div>
            </div>
        </div>
    </div>
</div>

@endsection
