@extends('layouts.' . (auth()->user()->roleSlug() ?? 'admin'), ['title' => 'Dashboard'])

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/layouts/dashboardInstructor.css') }}">
@endpush

@section('content')

{{-- Saludo personalizado por hora y nombre real del instructor logueado. --}}
<div class="page-header">
    <div>
        <div class="page-title">{{ $greeting }}, {{ $instructorName }} 👋</div>
        <div class="page-sub">
            @if($hasGroups)
                Resumen de tu instructoría — Ciclo {{ $semester }}
            @else
                Bienvenido a Instructor Hub
            @endif
        </div>
    </div>
    @if($hasGroups)
        <a href="{{ route('instructor.session') }}" class="btn btn-primary" style="text-decoration:none">
            <i class="ti ti-player-play"></i> Iniciar instructoría
        </a>
    @endif
</div>

{{-- Estado vacío: el instructor todavía no tiene grupos asignados por el coordinador. --}}
@unless($hasGroups)
    <div class="panel" style="text-align:center;padding:40px 24px">
        <div style="width:60px;height:60px;border-radius:50%;background:var(--primary-50);display:flex;align-items:center;justify-content:center;margin:0 auto 14px">
            <i class="ti ti-school" style="font-size:28px;color:var(--primary)"></i>
        </div>
        <div style="font-size:15px;font-weight:600;margin-bottom:4px">Aún no tenés grupos asignados</div>
        <div style="font-size:12px;color:var(--text-muted);max-width:380px;margin:0 auto">
            Cuando tu coordinador te asigne un grupo de clase, vas a ver acá el resumen, la lista de
            estudiantes y vas a poder iniciar las sesiones con código QR.
        </div>
    </div>
@else

{{-- 4 stats reales del instructor. --}}
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background:var(--primary-50)">
            <i class="ti ti-users" style="color:var(--primary)"></i>
        </div>
        <div class="stat-label">Estudiantes en mi grupo</div>
        <div class="stat-value">{{ $stats['students'] }}</div>
        <div><span class="tag tag-blue">Ciclo activo</span></div>
        <div class="stat-bg"><i class="ti ti-users"></i></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#EBF2FB">
            <i class="ti ti-calendar-check" style="color:#4C8FD4"></i>
        </div>
        <div class="stat-label">Sesiones realizadas</div>
        <div class="stat-value">{{ $stats['sessions'] }}</div>
        <div><span class="tag tag-light">Total acumulado</span></div>
        <div class="stat-bg"><i class="ti ti-calendar-check"></i></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:var(--primary-50)">
            <i class="ti ti-clipboard-check" style="color:var(--primary)"></i>
        </div>
        <div class="stat-label">Asistencia promedio</div>
        <div class="stat-value">{{ $stats['attendance_avg'] }}%</div>
        <div>
            @php
                $pct = (int) $stats['attendance_avg'];
                $tagClass = $pct >= 75 ? 'tag-blue' : ($pct >= 50 ? 'tag-light' : 'tag-warning');
                $tagText = $pct >= 75 ? 'Buen nivel' : ($pct >= 50 ? 'Aceptable' : 'Atención');
            @endphp
            <span class="tag {{ $tagClass }}">{{ $tagText }}</span>
        </div>
        <div class="stat-bg"><i class="ti ti-clipboard-check"></i></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#FEF9C3">
            <i class="ti ti-school" style="color:#854D0E"></i>
        </div>
        <div class="stat-label">Grupos a tu cargo</div>
        <div class="stat-value">{{ $stats['active_groups'] }}</div>
        <div><span class="tag tag-warning">{{ $stats['active_groups'] === 1 ? 'Un grupo' : 'Varios grupos' }}</span></div>
        <div class="stat-bg"><i class="ti ti-school"></i></div>
    </div>
</div>

{{-- Tarjeta del grupo activo. --}}
@if($active)
<div class="active-group">
    <div class="active-group-header">
        <div>
            <span class="active-label"><span class="active-dot"></span>Grupo activo — {{ $active['semester'] ?? '—' }}</span>
            <div class="group-name" style="margin-top:6px">{{ $active['group_name'] }}</div>
            <div class="group-sub">
                @if(!empty($active['professor']))Ing. {{ $active['professor'] }}@endif
            </div>
        </div>
        <a href="{{ route('instructor.session') }}" class="btn btn-primary" style="font-size:12px;padding:7px 14px;text-decoration:none">
            <i class="ti ti-player-play"></i> Iniciar instructoría
        </a>
    </div>
    <div class="active-group-body">
        <div>
            <div class="detail-label">Horario</div>
            <div class="detail-value">{{ $active['schedule'] }}</div>
            <div class="detail-sub">&nbsp;</div>
        </div>
        <div>
            <div class="detail-label">Modalidad</div>
            <div class="detail-value">{{ $active['modality'] }}</div>
            <div class="detail-sub">{{ $active['classroom'] !== '—' ? $active['classroom'] : '' }}&nbsp;</div>
        </div>
        <div>
            <div class="detail-label">Estudiantes</div>
            <div class="detail-value">{{ $active['enrolled'] }} inscritos</div>
            <div class="detail-sub">
                @if($active['avg_attendees'] > 0)
                    Prom. {{ $active['avg_attendees'] }} asistentes
                @else
                    Sin sesiones aún
                @endif
            </div>
        </div>
        <div>
            <div class="detail-label">Última sesión</div>
            <div class="detail-value">
                {{ $active['last_session_human'] ?? 'Sin registros' }}
            </div>
            <div class="detail-sub">
                @if($active['last_session_count'] > 0)
                    {{ $active['last_session_count'] }} {{ $active['last_session_count'] === 1 ? 'asistente' : 'asistentes' }}
                @else
                    &nbsp;
                @endif
            </div>
        </div>
    </div>
</div>
@endif

{{-- Estudiantes del grupo activo + historial de grupos. --}}
<div class="row-2">
    <div class="panel">
        <div class="panel-head">
            <div>
                <div class="panel-title">Estudiantes del grupo</div>
                <div class="panel-sub">{{ $active['group_name'] ?? '—' }} — {{ $active['semester'] ?? '—' }}</div>
            </div>
            <a href="{{ route('instructor.groups.index') }}" class="panel-action">Ver todos ↗</a>
        </div>
        @if($studentRows->isEmpty())
            <div style="padding:18px 0;text-align:center;color:var(--text-muted);font-size:12px">
                Este grupo todavía no tiene estudiantes inscritos.
            </div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Estudiante</th>
                        <th>Carné</th>
                        <th>Asistencia</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($studentRows->take(5) as $row)
                        @php
                            $rowPct = (int) $row['attendance_pct'];
                            $badgeClass = $rowPct >= 75 ? 'badge-blue' : ($rowPct >= 50 ? 'badge-light' : 'badge-warn');
                        @endphp
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:7px">
                                    <div class="avatar-xs">{{ $row['initials'] }}</div>
                                    <span class="td-main">{{ $row['name'] }}</span>
                                </div>
                            </td>
                            <td style="font-size:11px;font-family:monospace">{{ $row['carnet'] }}</td>
                            <td><span class="badge {{ $badgeClass }}">{{ $rowPct }}%</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="panel">
        <div class="panel-head">
            <div>
                <div class="panel-title">Historial de grupos</div>
                <div class="panel-sub">Otras instructorías</div>
            </div>
            <a href="{{ route('instructor.groups.index') }}" class="panel-action">Ver todos ↗</a>
        </div>
        @if($history->isEmpty())
            <div style="padding:18px 0;text-align:center;color:var(--text-muted);font-size:12px">
                Aún no tenés otros grupos en el historial.
            </div>
        @else
            @foreach($history as $h)
                <div class="history-item">
                    <div class="history-icon"><i class="ti ti-books"></i></div>
                    <div>
                        <div class="history-name">{{ $h['name'] }}</div>
                        <div class="history-meta">{{ $h['schedule'] }} · {{ $h['modality'] }}</div>
                    </div>
                    <div class="history-right">
                        <span class="cycle-tag">{{ $h['semester'] }}</span>
                        <div class="history-students">{{ $h['students'] }} estudiantes</div>
                    </div>
                </div>
            @endforeach
        @endif
    </div>
</div>

@endunless

@endsection
