@extends('layouts.admin', ['title' => 'Resumen por Coordinación'])

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin/reports.css') }}">
@endpush

@section('content')

<div style="background:linear-gradient(135deg,var(--accent,#7F77DD) 0%,#5A50C8 100%);border-radius:14px;padding:28px 32px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
    <div style="display:flex;align-items:center;gap:16px">
        <div style="width:52px;height:52px;background:rgba(255,255,255,.15);border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i class="ti ti-building" style="font-size:26px;color:#fff"></i>
        </div>
        <div>
            <h1 style="font-size:20px;font-weight:700;color:#fff;margin:0 0 4px">Resumen por coordinadores</h1>
            <p style="font-size:13px;color:rgba(255,255,255,.7);margin:0">Instructores, instructorías y evaluaciones agrupados por coordinador</p>
        </div>
    </div>
    <div style="display:flex;gap:8px">
        <a href="{{ route('admin.reportes.instructores') }}"
           style="display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.25);padding:8px 14px;border-radius:8px;font-size:13px;text-decoration:none;transition:background .15s"
           onmouseover="this.style.background='rgba(255,255,255,.25)'"
           onmouseout="this.style.background='rgba(255,255,255,.15)'">
            <i class="ti ti-chart-bar"></i> Ver desempeño
        </a>
    </div>
</div>

{{-- Sub-navegación --}}
<nav class="rpt-nav">
    <a href="{{ route('admin.reportes.instructores') }}" class="rpt-nav-link">
        <i class="ti ti-chart-bar"></i> Desempeño de instructores
    </a>
    <a href="{{ route('admin.reportes.coordinaciones') }}" class="rpt-nav-link active">
        <i class="ti ti-building"></i> Coordinadores
    </a>
</nav>

{{-- Stats globales --}}
<div class="rpt-stats">
    <div class="rpt-stat">
        <div class="rpt-stat-icon primary"><i class="ti ti-users"></i></div>
        <div>
            <div class="rpt-stat-val">{{ $globalStats['total_instructors'] }}</div>
            <div class="rpt-stat-lbl">Instructores totales</div>
        </div>
    </div>
    <div class="rpt-stat">
        <div class="rpt-stat-icon success"><i class="ti ti-user-check"></i></div>
        <div>
            <div class="rpt-stat-val">{{ $globalStats['active_instructors'] }}</div>
            <div class="rpt-stat-lbl">Instructores activos</div>
        </div>
    </div>
    <div class="rpt-stat">
        <div class="rpt-stat-icon accent"><i class="ti ti-building"></i></div>
        <div>
            <div class="rpt-stat-val">{{ $globalStats['total_coordinators'] }}</div>
            <div class="rpt-stat-lbl">Coordinadores</div>
        </div>
    </div>
    <div class="rpt-stat">
        <div class="rpt-stat-icon info"><i class="ti ti-school"></i></div>
        <div>
            <div class="rpt-stat-val">{{ $globalStats['active_assignments'] }}</div>
            <div class="rpt-stat-lbl">Instructorías activas</div>
        </div>
    </div>
    <div class="rpt-stat">
        <div class="rpt-stat-icon warning"><i class="ti ti-clock"></i></div>
        <div>
            <div class="rpt-stat-val">{{ $globalStats['pending_suspensions'] }}</div>
            <div class="rpt-stat-lbl">Solicitudes pendientes</div>
        </div>
    </div>
    <div class="rpt-stat">
        <div class="rpt-stat-icon{{ $globalStats['avg_score_global'] >= 8 ? ' success' : ($globalStats['avg_score_global'] >= 6 ? ' warning' : ' danger') }}">
            <i class="ti ti-star"></i>
        </div>
        <div>
            <div class="rpt-stat-val">{{ $globalStats['avg_score_global'] > 0 ? number_format($globalStats['avg_score_global'], 1) : '—' }}</div>
            <div class="rpt-stat-lbl">Promedio global (1–10)</div>
        </div>
    </div>
</div>

@php
    $schools = $coordinators->map(fn($c) => $c->school_name ?? null)->filter()->unique()->sort()->values();
@endphp

{{-- Toolbar: filtro por escuela + contador --}}
<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px">
    <div style="position:relative;flex:1;min-width:180px;max-width:280px">
        <i class="ti ti-building" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:14px;pointer-events:none"></i>
        <select id="schoolFilter" onchange="coordFilter()"
            style="width:100%;border:1px solid var(--border);border-radius:8px;padding:8px 12px 8px 32px;
                   font-size:13px;color:var(--text-soft);font-family:inherit;background:var(--surface);
                   outline:none;cursor:pointer;appearance:none">
            <option value="">Todas las escuelas</option>
            @foreach($schools as $school)
                <option value="{{ $school }}">{{ $school }}</option>
            @endforeach
        </select>
    </div>
    <span id="coordCount" style="font-size:12px;color:var(--text-muted);margin-left:auto">
        {{ $coordinators->count() }} coordinador{{ $coordinators->count() !== 1 ? 'es' : '' }}
    </span>
</div>

@if($coordinators->isEmpty())
    <div class="rpt-empty" style="background:var(--surface);border:1px solid var(--border);border-radius:12px">
        <i class="ti ti-building-off"></i>
        No hay coordinadores registrados.
    </div>
@else
    <div class="rpt-table-card">
        <div class="rpt-table-wrap">
            <table class="rpt-table" id="coordTable">
                <thead>
                    <tr>
                        <th>Coordinador</th>
                        <th>Cátedra</th>
                        <th style="text-align:center">Total instr.</th>
                        <th style="text-align:center">Activos</th>
                        <th style="text-align:center">Suspendidos</th>
                        <th style="text-align:center">Bloqueados</th>
                        <th style="text-align:center">Instructorías activas</th>
                        <th style="text-align:center">Promedio eval.</th>
                        <th style="text-align:center">Sol. pendientes</th>
                    </tr>
                </thead>
                <tbody id="coordTableBody">
                    @foreach($coordinators as $coord)
                        @php
                            $stats    = $instructorStats[$coord->id]    ?? null;
                            $total    = $stats->total       ?? 0;
                            $activos  = $stats->activos     ?? 0;
                            $susp     = $stats->suspendidos ?? 0;
                            $bloq     = $stats->bloqueados  ?? 0;
                            $asign    = $activeAssignments[$coord->id]  ?? 0;
                            $avgScore = $avgScores[$coord->id]          ?? null;
                            $pendSusp = $pendingSuspensions[$coord->id] ?? 0;

                            $catedra  = $coord->catedra ?? $coord->coordination_name ?? '—';
                            $school   = $coord->school_name ?? '—';
                            $person   = $coord->user?->name ?? '—';

                            $scoreColor = match(true) {
                                $avgScore === null => 'none',
                                $avgScore >= 8.0   => 'high',
                                $avgScore >= 6.0   => 'mid',
                                default            => 'low',
                            };
                        @endphp
                        <tr data-school="{{ $school }}">
                            <td style="font-weight:600;color:var(--text)">{{ $person }}</td>
                            <td>
                                <div style="font-size:13px;color:var(--text-soft)">{{ $catedra }}</div>
                                <div style="font-size:11px;color:var(--text-muted);margin-top:1px">{{ $school }}</div>
                            </td>
                            <td style="text-align:center;font-weight:600">{{ $total }}</td>
                            <td style="text-align:center">
                                @if($activos > 0)
                                    <span class="status-dot activo">{{ $activos }}</span>
                                @else <span class="no-data">0</span>
                                @endif
                            </td>
                            <td style="text-align:center">
                                @if($susp > 0)
                                    <span class="status-dot suspendido">{{ $susp }}</span>
                                @else <span class="no-data">0</span>
                                @endif
                            </td>
                            <td style="text-align:center">
                                @if($bloq > 0)
                                    <span class="status-dot bloqueado">{{ $bloq }}</span>
                                @else <span class="no-data">0</span>
                                @endif
                            </td>
                            <td style="text-align:center;font-weight:600">{{ $asign }}</td>
                            <td style="text-align:center">
                                <span class="score-pill {{ $scoreColor }}">
                                    {{ $avgScore !== null ? number_format($avgScore, 1) : '—' }}
                                </span>
                            </td>
                            <td style="text-align:center">
                                @if($pendSusp > 0)
                                    <span style="font-size:12px;background:#FFFBEB;color:#854D0E;padding:2px 10px;border-radius:20px;font-weight:600">
                                        {{ $pendSusp }}
                                    </span>
                                @else <span class="no-data">0</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Leyenda --}}
    <div style="margin-top:14px;display:flex;gap:14px;flex-wrap:wrap;font-size:12px;color:var(--text-soft)">
        <span>Promedio evaluaciones (1–10):</span>
        <span><span class="score-pill high" style="display:inline-flex">≥ 8.0</span> Destacado</span>
        <span><span class="score-pill mid"  style="display:inline-flex">6.0–7.9</span> Aceptable</span>
        <span><span class="score-pill low"  style="display:inline-flex">< 6.0</span> Bajo</span>
        <span><span class="score-pill none" style="display:inline-flex">—</span> Sin evaluaciones</span>
    </div>
@endif

@push('scripts')
<script>
function coordFilter() {
    const school = document.getElementById('schoolFilter').value.toLowerCase();
    const rows   = document.querySelectorAll('#coordTableBody tr');
    let visible  = 0;
    rows.forEach(tr => {
        const match = !school || (tr.dataset.school ?? '').toLowerCase() === school;
        tr.style.display = match ? '' : 'none';
        if (match) visible++;
    });
    document.getElementById('coordCount').textContent =
        visible + ' coordinador' + (visible !== 1 ? 'es' : '');
}
</script>
@endpush

@endsection
