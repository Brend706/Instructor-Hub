@extends('layouts.coordinator', ['title' => 'Dashboard'])

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/layouts/dashboardCoord.css') }}">
@endpush

@section('content')

<div class="cycle-header">
    <div>
        <h1 class="page-title">Buen día, {{ $coordinatorName }} 👋</h1>
        <p class="page-sub">
            Resumen de tu coordinación — {{ $coordinationName ?? '—' }}
        </p>
    </div>
    <form method="GET" action="{{ route('coordinator.dashboard') }}" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <select name="cycle" onchange="this.form.submit()" class="cycle-badge" style="cursor:pointer;border:none">
            @if($cycles->isEmpty())
                <option value="">Sin ciclos</option>
            @else
                @foreach($cycles as $cycle)
                    <option value="{{ $cycle }}" @selected($activeCycle === $cycle)>
                        Ciclo {{ $cycle }}
                    </option>
                @endforeach
            @endif
        </select>
    </form>
</div>

{{-- STATS --}}
<div class="stats-grid">

    <div class="stat-card">
        <div class="stat-icon" style="background:var(--accent-50)">
            <i class="ti ti-user-check" style="color:var(--accent)" aria-hidden="true"></i>
        </div>
        <div class="stat-label">Mis instructores</div>
        <div class="stat-value">{{ $stats['instructors_total'] }}</div>
        <div class="stat-footer">
            <span class="tag tag-success">{{ $stats['instructors_active'] }} activos</span>
            <span style="color:var(--text-muted)">este ciclo</span>
        </div>
        <div class="stat-bg"><i class="ti ti-user-check"></i></div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background:var(--primary-50)">
            <i class="ti ti-books" style="color:var(--primary)" aria-hidden="true"></i>
        </div>
        <div class="stat-label">Grupos activos</div>
        <div class="stat-value">{{ $stats['groups_active'] }}</div>
        <div class="stat-footer">
            <span class="tag tag-info">Ciclo {{ $activeCycle ?: '—' }}</span>
        </div>
        <div class="stat-bg"><i class="ti ti-books"></i></div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background:#EAF3DE">
            <i class="ti ti-calendar-event" style="color:#3B6D11" aria-hidden="true"></i>
        </div>
        <div class="stat-label">Instructorías del ciclo</div>
        <div class="stat-value">{{ $stats['sessions_total'] }}</div>
        <div class="stat-footer">
            <span class="tag tag-success">+{{ $stats['sessions_this_week'] }} esta semana</span>
        </div>
        <div class="stat-bg"><i class="ti ti-calendar-event"></i></div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background:#FEF9C3">
            <i class="ti ti-clipboard-check" style="color:#854D0E" aria-hidden="true"></i>
        </div>
        <div class="stat-label">Sesiones registradas</div>
        <div class="stat-value">{{ $stats['sessions_total'] }}</div>
        <div class="stat-footer">
            <span class="tag tag-warning">{{ $stats['sessions_pending'] }} pendientes</span>
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
            <a href="{{ route('coordinator.instructores.index') }}" class="panel-action">Ver todos ↗</a>
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
                @forelse($instructors as $inst)
                    @php
                        $initials = collect(explode(' ', $inst['name']))->filter()->map(fn($p) => mb_substr($p, 0, 1))->take(2)->join('');
                        $isActive = ($inst['status'] ?? 'Activo') === 'Activo';
                    @endphp
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px">
                                <div class="avatar-sm" style="background:var(--primary)">{{ strtoupper($initials ?: 'IN') }}</div>
                                <div class="td-main">{{ $inst['name'] }}</div>
                            </div>
                        </td>
                        <td>
                            @if(!empty($inst['group']))
                                {{ $inst['group'] }}
                            @else
                                <span class="no-assign">Sin asignar</span>
                            @endif
                        </td>
                        <td>
                            @if($isActive)
                                <span class="badge badge-success"><span class="status-dot dot-active"></span>Activo</span>
                            @else
                                <span class="badge badge-warning"><span class="status-dot dot-inactive"></span>Inactivo</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" style="padding:18px;color:var(--text-muted)">No hay instructores para mostrar.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Grupos del ciclo --}}
    <div class="panel">
        <div class="panel-head">
            <div>
                <div class="panel-title">Grupos del ciclo</div>
                <div class="panel-sub">Ciclo {{ $activeCycle ?: '—' }} activo</div>
            </div>
            <a href="{{ route('coordinator.groups.index', ['cycle' => $activeCycle]) }}" class="panel-action">Ver todos ↗</a>
        </div>

        @forelse($groups as $g)
            @php
                $isPresencial = ($g->modality ?? '') === 'Presencial';
            @endphp
            <div class="group-item">
                <div class="group-icon"><i class="ti ti-books" aria-hidden="true"></i></div>
                <div>
                    <div class="group-name">{{ $g->name }}</div>
                    <div class="group-meta">{{ $g->schedule }} · {{ $g->classroom }}</div>
                </div>
                <div class="group-right">
                    @if($isPresencial)
                        <span class="badge badge-info"><i class="ti ti-building" style="font-size:10px"></i>Presencial</span>
                    @else
                        <span class="badge badge-accent"><i class="ti ti-video" style="font-size:10px"></i>En línea</span>
                    @endif
                    <div class="group-students">{{ (int) $g->students_count }} estudiantes</div>
                </div>
            </div>
        @empty
            <div style="padding:14px;color:var(--text-muted)">No hay grupos para el ciclo seleccionado.</div>
        @endforelse

    </div>

</div>

@endsection