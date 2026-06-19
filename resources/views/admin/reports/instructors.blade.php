@extends('layouts.admin', ['title' => 'Reporte de Desempeño'])

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin/reports.css') }}">
@endpush

@section('content')

<div style="background:linear-gradient(135deg,var(--primary,#5A1533) 0%,#8B1E4E 100%);border-radius:14px;padding:28px 32px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
    <div style="display:flex;align-items:center;gap:16px">
        <div style="width:52px;height:52px;background:rgba(255,255,255,.15);border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i class="ti ti-chart-bar" style="font-size:26px;color:#fff"></i>
        </div>
        <div>
            <h1 style="font-size:20px;font-weight:700;color:#fff;margin:0 0 4px">Desempeño de instructores</h1>
            <p style="font-size:13px;color:rgba(255,255,255,.7);margin:0">Evaluaciones, sesiones y asistencia por instructor</p>
        </div>
    </div>
    <div style="display:flex;gap:8px">
        <a href="{{ route('admin.reportes.coordinaciones') }}"
           style="display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.25);padding:8px 14px;border-radius:8px;font-size:13px;text-decoration:none;transition:background .15s"
           onmouseover="this.style.background='rgba(255,255,255,.25)'"
           onmouseout="this.style.background='rgba(255,255,255,.15)'">
            <i class="ti ti-building"></i> Ver coordinadores
        </a>
    </div>
</div>

{{-- Sub-navegación entre reportes --}}
<nav class="rpt-nav">
    <a href="{{ route('admin.reportes.instructores') }}"
       class="rpt-nav-link active">
        <i class="ti ti-chart-bar"></i> Desempeño de instructores
    </a>
    <a href="{{ route('admin.reportes.coordinaciones') }}"
       class="rpt-nav-link">
        <i class="ti ti-building"></i> Coordinadores
    </a>
</nav>

{{-- Filtros --}}
<form method="GET" action="{{ route('admin.reportes.instructores') }}" class="rpt-filters">
    <label>Coordinación:</label>
    <select name="coordinator_id" onchange="this.form.submit()">
        <option value="">Todas</option>
        @foreach($coordinators as $coord)
            @php
                $coordLabel  = $coord->school_name ?? $coord->catedra ?? $coord->coordination_name ?? null;
                $coordPerson = $coord->user?->name ?? 'Coordinador '.$coord->id;
            @endphp
            <option value="{{ $coord->id }}"
                @selected(request('coordinator_id') == $coord->id)>
                {{ $coordPerson }}{{ $coordLabel ? ' — '.$coordLabel : '' }}
            </option>
        @endforeach
    </select>

    <label>Estado:</label>
    <select name="status" onchange="this.form.submit()">
        <option value="">Todos</option>
        <option value="Activo"     @selected(request('status') === 'Activo')>Activo</option>
        <option value="Suspendido" @selected(request('status') === 'Suspendido')>Suspendido</option>
        <option value="Bloqueado"  @selected(request('status') === 'Bloqueado')>Bloqueado</option>
        <option value="Inactivo"   @selected(request('status') === 'Inactivo')>Inactivo</option>
    </select>

    <label>Ordenar sesiones:</label>
    <select name="sort" onchange="this.form.submit()">
        <option value="name"          @selected($sort === 'name')>Por nombre</option>
        <option value="sessions_desc" @selected($sort === 'sessions_desc')>Más sesiones primero ↓</option>
        <option value="sessions_asc"  @selected($sort === 'sessions_asc')>Menos sesiones primero ↑</option>
    </select>

    @if(request()->hasAny(['coordinator_id', 'status']) || $sort !== 'name')
        <a href="{{ route('admin.reportes.instructores') }}" class="btn btn-ghost btn-sm">
            <i class="ti ti-x"></i> Limpiar
        </a>
    @endif
    <span class="rpt-filter-badge" style="margin-left:auto">
        {{ $instructors->total() }} resultado{{ $instructors->total() !== 1 ? 's' : '' }}
    </span>
</form>

{{-- Tabla principal --}}
<div class="rpt-table-card">
    <div class="rpt-table-wrap">
        <table class="rpt-table" id="rptTable">
            <thead>
                <tr>
                    <th>Instructor</th>
                    <th>Coordinación</th>
                    <th>Estado</th>
                    <th title="Sesiones impartidas">Sesiones</th>
                    <th title="Promedio de asistentes por clase">Asist./clase</th>
                    <th title="Autoevaluación">Self</th>
                    <th title="Evaluación del coordinador">Coord.</th>
                    <th title="Evaluación de estudiantes">Estud.</th>
                    <th title="Evaluación del docente titular">Docente</th>
                    <th title="Promedio general de todas las evaluaciones">Promedio</th>
                </tr>
            </thead>
            <tbody>
                @forelse($instructors as $inst)
                    @php
                        $evals  = $evalAvgs[$inst->id] ?? collect();
                        $self   = $evals['self']        ?? null;
                        $coord  = $evals['coordinator'] ?? null;
                        $stud   = $evals['student']     ?? null;
                        $teach  = $evals['teacher']     ?? null;

                        $scores = collect([$self?->avg_score, $coord?->avg_score,
                                           $stud?->avg_score, $teach?->avg_score])
                                    ->filter()->values();
                        $overall = $scores->isNotEmpty()
                            ? round($scores->average(), 2)
                            : null;

                        $scoreCss = fn(?float $v) => match(true) {
                            $v === null => 'none',
                            $v >= 8.0  => 'high',
                            $v >= 6.0  => 'mid',
                            default    => 'low',
                        };

                        $sessions   = (int) ($inst->sessions_count ?? 0);
                        $avgAtt     = $avgAttendees[$inst->id] ?? null;

                        $statusCss = match($inst->status) {
                            'Activo'     => 'activo',
                            'Suspendido' => 'suspendido',
                            'Bloqueado'  => 'bloqueado',
                            default      => 'inactivo',
                        };
                    @endphp
                    <tr data-name="{{ strtolower($inst->name) }}">
                        <td>
                            <div style="font-weight:600;font-size:13px">{{ $inst->name }}</div>
                            <div style="font-size:11.5px;color:var(--text-muted)">{{ $inst->email }}</div>
                            <div style="font-size:11px;color:var(--text-soft);margin-top:1px">{{ $inst->major }}</div>
                        </td>
                        <td style="font-size:12px;color:var(--text-soft)">
                            @if($inst->coord_label)
                                <div style="font-weight:500;color:var(--text-main)">{{ $inst->coord_label }}</div>
                                @if($inst->coord_person && $inst->coord_person !== $inst->coord_label)
                                    <div style="font-size:11px;color:var(--text-muted)">{{ $inst->coord_person }}</div>
                                @endif
                            @else
                                <span class="no-data">Sin asignar</span>
                            @endif
                        </td>
                        <td>
                            <span class="status-dot {{ $statusCss }}">{{ $inst->status }}</span>
                        </td>
                        <td style="text-align:center;font-weight:600">
                            {{ $sessions > 0 ? $sessions : '—' }}
                        </td>
                        <td style="text-align:center">
                            @if($avgAtt !== null)
                                <span style="font-size:13px;font-weight:600;color:var(--text-main)">{{ $avgAtt }}</span>
                                <span style="font-size:11px;color:var(--text-muted)"> est.</span>
                            @else
                                <span class="no-data">—</span>
                            @endif
                        </td>
                        <td style="text-align:center">
                            <span class="score-pill {{ $scoreCss($self?->avg_score) }}">
                                {{ $self ? number_format($self->avg_score, 1) : '—' }}
                            </span>
                        </td>
                        <td style="text-align:center">
                            <span class="score-pill {{ $scoreCss($coord?->avg_score) }}">
                                {{ $coord ? number_format($coord->avg_score, 1) : '—' }}
                            </span>
                        </td>
                        <td style="text-align:center">
                            <span class="score-pill {{ $scoreCss($stud?->avg_score) }}">
                                {{ $stud ? number_format($stud->avg_score, 1) : '—' }}
                            </span>
                        </td>
                        <td style="text-align:center">
                            <span class="score-pill {{ $scoreCss($teach?->avg_score) }}">
                                {{ $teach ? number_format($teach->avg_score, 1) : '—' }}
                            </span>
                        </td>
                        <td style="text-align:center">
                            <span class="score-pill {{ $scoreCss($overall) }}"
                                  style="font-size:13px;padding:3px 10px">
                                {{ $overall !== null ? number_format($overall, 1) : '—' }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="rpt-empty">
                            <i class="ti ti-chart-bar-off"></i>
                            No hay instructores que coincidan con los filtros aplicados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Paginación --}}
    @if($instructors->hasPages())
        <div style="padding:14px 16px;border-top:1px solid var(--border)">
            {{ $instructors->links() }}
        </div>
    @endif
</div>

{{-- Leyenda de colores --}}
<div style="margin-top:14px;display:flex;gap:14px;flex-wrap:wrap;font-size:12px;color:var(--text-soft)">
    <span>Escala de evaluaciones (1–10):</span>
    <span><span class="score-pill high" style="display:inline-flex">≥ 8.0</span> Destacado</span>
    <span><span class="score-pill mid"  style="display:inline-flex">6.0–7.9</span> Aceptable</span>
    <span><span class="score-pill low"  style="display:inline-flex">< 6.0</span> Bajo</span>
    <span><span class="score-pill none" style="display:inline-flex">—</span> Sin evaluaciones</span>
</div>

@endsection

@push('scripts')
<script>
// Búsqueda rápida en tabla (client-side)
const searchInput = document.createElement('input');
searchInput.type = 'search';
searchInput.placeholder = 'Filtrar por nombre...';
searchInput.style.cssText = 'height:36px;border:1px solid var(--border);border-radius:8px;padding:0 12px;font-size:13px;margin-right:auto';

const filterForm = document.querySelector('.rpt-filters');
if (filterForm) filterForm.prepend(searchInput);

searchInput.addEventListener('input', () => {
    const q = searchInput.value.toLowerCase();
    document.querySelectorAll('#rptTable tbody tr[data-name]').forEach(row => {
        row.style.display = row.dataset.name.includes(q) ? '' : 'none';
    });
});
</script>
@endpush
