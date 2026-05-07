@extends('layouts.admin', ['title' => 'Dashboard'])

@php
    $semanasData   = $semanas ?? [6,9,7,12,10,8,11,13];
    $labelsData    = $semanasLabels ?? ['S1','S2','S3','S4','S5','S6','S7','S8'];
    $presencial    = $pctPresencial ?? 62;
    $enLinea       = $pctEnLinea ?? 38;
    $totalSesiones = $totalInstructoriasmes ?? 48;
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/layouts/dashboardAdmin.css') }}">
@endpush

@section('content')

{{-- ═══════════════════════════════════
     ENCABEZADO
═══════════════════════════════════ --}}
<div class="page-title">Buen día, {{ explode(' ', auth()->user()->name ?? 'Administrador')[0] }} 👋</div>
<p class="page-sub">Resumen general del sistema — {{ now()->translatedFormat('F Y') }}</p>

{{-- ═══════════════════════════════════
     TARJETAS DE ESTADÍSTICAS
═══════════════════════════════════ --}}
<div class="stats-grid">

    <div class="stat-card">
        <div class="stat-icon" style="background:var(--primary-50)">
            <i class="ti ti-calendar-event" style="color:var(--primary)" aria-hidden="true"></i>
        </div>
        <div class="stat-label">Instructorías este mes</div>
        <div class="stat-value">{{ $totalInstructoriasmes ?? 0 }}</div>
        <div class="stat-footer">
            <span class="trend {{ ($pctInstructorias ?? 0) >= 0 ? 'up' : 'down' }}">
                <i class="ti ti-trending-{{ ($pctInstructorias ?? 0) >= 0 ? 'up' : 'down' }}" style="font-size:11px"></i>
                {{ ($pctInstructorias ?? 0) > 0 ? '+' : '' }}{{ $pctInstructorias ?? 0 }}%
            </span>
            <span style="color:var(--text-muted)">vs mes anterior</span>
        </div>
        <div class="stat-bg"><i class="ti ti-calendar-event"></i></div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background:var(--accent-50)">
            <i class="ti ti-user-check" style="color:var(--accent)" aria-hidden="true"></i>
        </div>
        <div class="stat-label">Instructores activos</div>
        <div class="stat-value">{{ $totalInstructores ?? 0 }}</div>
        <div class="stat-footer">
            <span class="trend neu">{{ $nuevosInstructores ?? 0 }} nuevos</span>
            <span style="color:var(--text-muted)">este mes</span>
        </div>
        <div class="stat-bg"><i class="ti ti-user-check"></i></div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background:#EAF3DE">
            <i class="ti ti-users" style="color:#3B6D11" aria-hidden="true"></i>
        </div>
        <div class="stat-label">Coordinadores</div>
        <div class="stat-value">{{ $totalCoordinadores ?? 0 }}</div>
        <div class="stat-footer">
            <span class="trend neu" style="background:#EAF3DE;color:#3B6D11">Todos activos</span>
        </div>
        <div class="stat-bg"><i class="ti ti-users"></i></div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background:#FEF9C3">
            <i class="ti ti-chart-pie" style="color:#854D0E" aria-hidden="true"></i>
        </div>
        <div class="stat-label">Asistencia promedio</div>
        <div class="stat-value">{{ $asistenciaPromedio ?? 0 }}%</div>
        <div class="stat-footer">
            <span class="trend {{ ($pctAsistencia ?? 0) >= 0 ? 'up' : 'down' }}">
                <i class="ti ti-trending-{{ ($pctAsistencia ?? 0) >= 0 ? 'up' : 'down' }}" style="font-size:11px"></i>
                {{ ($pctAsistencia ?? 0) > 0 ? '+' : '' }}{{ $pctAsistencia ?? 0 }}%
            </span>
            <span style="color:var(--text-muted)">vs mes anterior</span>
        </div>
        <div class="stat-bg"><i class="ti ti-chart-pie"></i></div>
    </div>

</div>

{{-- ═══════════════════════════════════
     FILA 1: Gráfica + Modalidad + Actividad
═══════════════════════════════════ --}}
<div class="dash-row dash-row-3">

    {{-- Instructorías por semana --}}
    <div class="panel">
        <div class="panel-head">
            <div>
                <div class="panel-title">Instructorías por semana</div>
                <div class="panel-sub">{{ now()->translatedFormat('F Y') }} — semanas activas</div>
            </div>
            <a href="" class="panel-action">Ver reporte ↗</a>
        </div>
        <div class="bar-chart" id="barChart"></div>
        <div class="bar-labels" id="barLabels"></div>
    </div>

    {{-- Modalidad --}}
    <div class="panel">
        <div class="panel-head">
            <div>
                <div class="panel-title">Modalidad</div>
                <div class="panel-sub">Presencial vs en línea</div>
            </div>
        </div>
        <div class="donut-wrap">
            <canvas id="donutChart" width="90" height="90"></canvas>
            <div class="donut-legend">
                <div class="legend-item">
                    <div class="legend-dot" style="background:var(--primary)"></div>
                    <span class="legend-label">Presencial</span>
                    <span class="legend-val">{{ $presencial }}%</span>
                </div>
                <div class="legend-item">
                    <div class="legend-dot" style="background:var(--accent)"></div>
                    <span class="legend-label">En línea</span>
                    <span class="legend-val">{{ $enLinea }}%</span>
                </div>
            </div>
        </div>
        <div class="inline-stats" style="margin-top:14px">
            <div class="mini-stat">
                <div class="mini-label">Presenciales</div>
                <div class="mini-val" style="color:var(--primary)">{{ $totalPresencial ?? 0 }}</div>
            </div>
            <div class="mini-stat">
                <div class="mini-label">En línea</div>
                <div class="mini-val" style="color:var(--accent)">{{ $totalEnLinea ?? 0 }}</div>
            </div>
        </div>
    </div>

    {{-- Actividad reciente --}}
    <div class="panel">
        <div class="panel-head">
            <div class="panel-title">Actividad reciente</div>
            <a href="#" class="panel-action">Ver todo ↗</a>
        </div>
        @forelse($actividad ?? [] as $item)
            <div class="activity-item">
                <div class="act-icon" style="background:{{ $item['bg'] }}">
                    <i class="ti ti-{{ $item['icon'] }}" style="color:{{ $item['color'] }};font-size:14px" aria-hidden="true"></i>
                </div>
                <div class="act-text">
                    <strong>{{ $item['usuario'] }}</strong> {{ $item['accion'] }}<br>
                    <span class="act-time">
                        <i class="ti ti-clock" style="font-size:10px"></i>
                        {{ $item['tiempo'] }} — {{ $item['contexto'] }}
                    </span>
                </div>
            </div>
        @empty
            <p style="font-size:12px;color:var(--text-muted);text-align:center;padding:16px 0">Sin actividad reciente</p>
        @endforelse
    </div>

</div>

{{-- ═══════════════════════════════════
     FILA 2: Tabla instructores + Coordinadores
═══════════════════════════════════ --}}
<div class="dash-row dash-row-2">

    {{-- Tabla instructores recientes --}}
    <div class="panel">
        <div class="panel-head">
            <div>
                <div class="panel-title">Instructores registrados recientemente</div>
                <div class="panel-sub">Últimos registros del sistema</div>
            </div>
            <a href="#" class="panel-action">Ver todos ↗</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Instructor</th>
                        <th>Carrera</th>
                        <th>Coordinador</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($instructoresRecientes ?? [] as $instructor)
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:8px">
                                    <div class="avatar-sm" style="background:var(--primary)">
                                        {{ strtoupper(substr($instructor->name, 0, 2)) }}
                                    </div>
                                    <span class="td-name">{{ $instructor->name }}</span>
                                </div>
                            </td>
                            <td>{{ $instructor->carrera }}</td>
                            <td>{{ $instructor->coordinador->name ?? '—' }}</td>
                            <td>
                                <span class="badge badge-{{ $instructor->estado === 'activo' ? 'success' : ($instructor->estado === 'pendiente' ? 'warning' : 'info') }}">
                                    {{ ucfirst($instructor->estado) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="text-align:center;color:var(--text-muted);padding:20px">
                                Sin instructores registrados aún
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Coordinadores --}}
    <div class="panel">
        <div class="panel-head">
            <div>
                <div class="panel-title">Coordinadores — actividad</div>
                <div class="panel-sub">Instructores y sesiones a cargo</div>
            </div>
            <a href="" class="panel-action">Ver todos ↗</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Coordinador</th>
                        <th>Instructores</th>
                        <th>Sesiones</th>
                        <th>Asistencia</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($coordinadores ?? [] as $coord)
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:8px">
                                    <div class="avatar-sm" style="background:var(--primary)">
                                        {{ strtoupper(substr($coord->name, 0, 2)) }}
                                    </div>
                                    <span class="td-name">{{ $coord->name }}</span>
                                </div>
                            </td>
                            <td><span class="badge badge-info">{{ $coord->instructores_count ?? 0 }}</span></td>
                            <td>{{ $coord->sesiones_count ?? 0 }}</td>
                            <td>
                                <span style="font-weight:600;color:{{ ($coord->asistencia ?? 0) >= 85 ? '#166534' : '#854D0E' }}">
                                    {{ $coord->asistencia ?? 0 }}%
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="text-align:center;color:var(--text-muted);padding:20px">
                                Sin coordinadores registrados aún
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

@endsection

@push('scripts')
<script>
    // Variables PHP resueltas antes del DOMContentLoaded
    const semanas       = @json($semanasData);
    const labels        = @json($labelsData);
    const pctPresencial = {{ $presencial }} / 100;
    const pctEnLinea    = {{ $enLinea }} / 100;
    const totalSesiones = {{ $totalSesiones }};

    document.addEventListener('DOMContentLoaded', function () {

        // ── Gráfica de barras ──────────────────────────────
        const maxVal   = Math.max(...semanas);
        const chart    = document.getElementById('barChart');
        const labelsEl = document.getElementById('barLabels');

        semanas.forEach((v, i) => {
            const col = document.createElement('div');
            col.className = 'bar-col';

            const val = document.createElement('div');
            val.className = 'bar-val';
            val.textContent = v;

            const bar = document.createElement('div');
            bar.className = 'bar' + (i === semanas.length - 1 ? ' accent' : '');
            bar.style.height = Math.round(v / maxVal * 90) + 'px';
            bar.title = v + ' instructorías';

            col.appendChild(val);
            col.appendChild(bar);
            chart.appendChild(col);

            const lbl = document.createElement('div');
            lbl.className = 'bar-lbl';
            lbl.textContent = labels[i];
            labelsEl.appendChild(lbl);
        });

        // ── Donut de modalidad ─────────────────────────────
        const canvas = document.getElementById('donutChart');
        if (canvas) {
            const ctx    = canvas.getContext('2d');
            const cx     = 45, cy = 45, r = 36, stroke = 10;

            const segments = [
                { v: pctPresencial, c: '#1B4E8B' },
                { v: pctEnLinea,    c: '#0B7080' },
            ];

            let angle = -Math.PI / 2;
            segments.forEach(d => {
                const end = angle + d.v * 2 * Math.PI;
                ctx.beginPath();
                ctx.arc(cx, cy, r, angle, end);
                ctx.strokeStyle = d.c;
                ctx.lineWidth   = stroke;
                ctx.lineCap     = 'butt';
                ctx.stroke();
                angle = end;
            });

            ctx.beginPath();
            ctx.arc(cx, cy, r - stroke / 2, 0, 2 * Math.PI);
            ctx.fillStyle = '#fff';
            ctx.fill();

            ctx.fillStyle    = '#1E293B';
            ctx.font         = '600 13px "DM Sans", system-ui';
            ctx.textAlign    = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(totalSesiones, cx, cy - 6);

            ctx.fillStyle = '#94A3B8';
            ctx.font      = '400 9px "DM Sans", system-ui';
            ctx.fillText('sesiones', cx, cy + 7);
        }

    });
</script>
@endpush