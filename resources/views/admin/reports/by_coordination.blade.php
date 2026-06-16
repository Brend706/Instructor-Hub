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
            <h1 style="font-size:20px;font-weight:700;color:#fff;margin:0 0 4px">Resumen por coordinación</h1>
            <p style="font-size:13px;color:rgba(255,255,255,.7);margin:0">Instructores, instructorías y evaluaciones agrupados por coordinación</p>
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
        <i class="ti ti-building"></i> Resumen por coordinación
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
            <div class="rpt-stat-lbl">Coordinaciones</div>
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

{{-- Tarjetas por coordinación --}}
<div class="rpt-section-header">
    <div class="rpt-section-title">
        <i class="ti ti-layout-grid"></i>
        Detalle por coordinación
    </div>
    <span style="font-size:12px;color:var(--text-muted)">{{ $coordinators->count() }} coordinación{{ $coordinators->count() !== 1 ? 'es' : '' }}</span>
</div>

@if($coordinators->isEmpty())
    <div class="rpt-empty" style="background:#fff;border:1px solid var(--border);border-radius:12px">
        <i class="ti ti-building-off"></i>
        No hay coordinaciones registradas.
    </div>
@else
    <div class="coord-grid">
        @foreach($coordinators as $coord)
            @php
                $stats     = $instructorStats[$coord->id]     ?? null;
                $total     = $stats->total       ?? 0;
                $activos   = $stats->activos     ?? 0;
                $susp      = $stats->suspendidos ?? 0;
                $bloq      = $stats->bloqueados  ?? 0;
                $asign     = $activeAssignments[$coord->id]   ?? 0;
                $avgScore  = $avgScores[$coord->id]           ?? null;
                $pendSusp  = $pendingSuspensions[$coord->id]  ?? 0;

                $areaName  = $coord->school_name ?? $coord->catedra ?? $coord->coordination_name ?? '—';
                $person    = $coord->user?->name ?? 'Sin coordinador';

                $scoreColor = match(true) {
                    $avgScore === null => 'none',
                    $avgScore >= 8.0   => 'high',
                    $avgScore >= 6.0   => 'mid',
                    default            => 'low',
                };
            @endphp
            <div class="coord-card">
                <div class="coord-card-name">{{ $areaName }}</div>
                <div class="coord-card-person">
                    <i class="ti ti-user" style="font-size:11px"></i> {{ $person }}
                </div>

                <div class="coord-card-stats">
                    <div class="coord-mini-stat">
                        <div class="coord-mini-stat-val">{{ $total }}</div>
                        <div class="coord-mini-stat-lbl">Instructores</div>
                    </div>
                    <div class="coord-mini-stat">
                        <div class="coord-mini-stat-val">{{ $asign }}</div>
                        <div class="coord-mini-stat-lbl">Instructorías activas</div>
                    </div>
                </div>

                {{-- Estado de instructores --}}
                <div class="coord-status-row">
                    @if($activos > 0)
                        <span class="status-dot activo">{{ $activos }} activo{{ $activos !== 1 ? 's' : '' }}</span>
                    @endif
                    @if($susp > 0)
                        <span class="status-dot suspendido">{{ $susp }} susp.</span>
                    @endif
                    @if($bloq > 0)
                        <span class="status-dot bloqueado">{{ $bloq }} bloq.</span>
                    @endif
                    @if($total === 0)
                        <span class="no-data">Sin instructores</span>
                    @endif
                </div>

                {{-- Promedio de evaluaciones --}}
                <div class="coord-score-row">
                    <span>Promedio evaluaciones</span>
                    <div style="display:flex;align-items:center;gap:8px">
                        @if($pendSusp > 0)
                            <span style="font-size:11px;background:#FFFBEB;color:#854D0E;padding:1px 7px;border-radius:20px">
                                <i class="ti ti-clock" style="font-size:10px"></i> {{ $pendSusp }} pend.
                            </span>
                        @endif
                        <span class="score-pill {{ $scoreColor }} coord-score-val">
                            {{ $avgScore !== null ? number_format($avgScore, 1) : '—' }}
                        </span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Tabla comparativa --}}
    <div class="rpt-section-header" style="margin-top:8px">
        <div class="rpt-section-title">
            <i class="ti ti-table"></i>
            Vista comparativa
        </div>
    </div>

    <div class="rpt-table-card">
        <div class="rpt-table-wrap">
            <table class="rpt-table">
                <thead>
                    <tr>
                        <th>Coordinación</th>
                        <th>Coordinador</th>
                        <th style="text-align:center">Total instr.</th>
                        <th style="text-align:center">Activos</th>
                        <th style="text-align:center">Suspendidos</th>
                        <th style="text-align:center">Bloqueados</th>
                        <th style="text-align:center">Instructorías activas</th>
                        <th style="text-align:center">Promedio eval.</th>
                        <th style="text-align:center">Solicitudes pend.</th>
                    </tr>
                </thead>
                <tbody>
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

                            $areaName = $coord->school_name ?? $coord->catedra ?? $coord->coordination_name ?? '—';
                            $person   = $coord->user?->name ?? '—';

                            $scoreColor = match(true) {
                                $avgScore === null => 'none',
                                $avgScore >= 8.0   => 'high',
                                $avgScore >= 6.0   => 'mid',
                                default            => 'low',
                            };
                        @endphp
                        <tr>
                            <td style="font-weight:600">{{ $areaName }}</td>
                            <td style="font-size:12px;color:var(--text-soft)">{{ $person }}</td>
                            <td style="text-align:center;font-weight:600">{{ $total }}</td>
                            <td style="text-align:center">
                                @if($activos > 0)
                                    <span class="status-dot activo">{{ $activos }}</span>
                                @else
                                    <span class="no-data">0</span>
                                @endif
                            </td>
                            <td style="text-align:center">
                                @if($susp > 0)
                                    <span class="status-dot suspendido">{{ $susp }}</span>
                                @else
                                    <span class="no-data">0</span>
                                @endif
                            </td>
                            <td style="text-align:center">
                                @if($bloq > 0)
                                    <span class="status-dot bloqueado">{{ $bloq }}</span>
                                @else
                                    <span class="no-data">0</span>
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
                                @else
                                    <span class="no-data">0</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

{{-- Leyenda --}}
<div style="margin-top:14px;display:flex;gap:14px;flex-wrap:wrap;font-size:12px;color:var(--text-soft)">
    <span>Promedio evaluaciones (1–10):</span>
    <span><span class="score-pill high" style="display:inline-flex">≥ 8.0</span> Destacado</span>
    <span><span class="score-pill mid"  style="display:inline-flex">6.0–7.9</span> Aceptable</span>
    <span><span class="score-pill low"  style="display:inline-flex">< 6.0</span> Bajo</span>
    <span><span class="score-pill none" style="display:inline-flex">—</span> Sin evaluaciones</span>
</div>

@endsection
