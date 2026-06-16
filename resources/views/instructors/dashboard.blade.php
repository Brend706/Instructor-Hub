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

{{-- ── Sección: suspensión de instructoría ─────────────────── --}}
<div style="margin-top:28px;padding:18px 20px;background:var(--surface);
            border:1px solid var(--border);border-radius:12px;
            display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap">
    <div style="display:flex;align-items:center;gap:12px">
        <div style="width:36px;height:36px;border-radius:8px;background:var(--primary-50);
                    display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i class="ti ti-player-pause" style="color:var(--primary);font-size:17px"></i>
        </div>
        <div>
            <div style="font-size:13px;font-weight:600;color:var(--text)">Suspensión de instructoría</div>
            @if($pendingSuspension)
                <div style="font-size:11px;color:var(--warning-text);margin-top:2px">
                    <i class="ti ti-clock" style="font-size:12px"></i>
                    Tienes una solicitud pendiente de revisión desde {{ $pendingSuspension->requested_at?->format('d/m/Y') }}.
                </div>
            @else
                <div style="font-size:11px;color:var(--text-muted);margin-top:2px">
                    Si necesitas pausar tu instructoría por fuerza mayor u otra razón, envía una solicitud a tu coordinador.
                </div>
            @endif
        </div>
    </div>
    @if($pendingSuspension)
        <span style="font-size:11px;font-weight:500;padding:4px 12px;border-radius:20px;
                     background:var(--warning-bg);color:var(--warning-text)">
            <i class="ti ti-clock"></i> Solicitud en revisión
        </span>
    @else
        <button type="button" onclick="document.getElementById('modalSuspension').classList.add('open');document.body.style.overflow='hidden'"
            style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;
                   border:1px solid var(--primary);border-radius:8px;background:transparent;
                   color:var(--primary);font-size:12px;font-weight:500;cursor:pointer;
                   transition:background .15s;white-space:nowrap"
            onmouseover="this.style.background='var(--primary-50)'"
            onmouseout="this.style.background='transparent'">
            <i class="ti ti-player-pause"></i> Solicitar suspensión
        </button>
    @endif
</div>

@endunless

{{-- ── Alertas de sesión ────────────────────────────────────── --}}
@if(session('suspension_success'))
    <div style="position:fixed;bottom:24px;right:24px;z-index:2000;max-width:360px;
                padding:14px 18px;background:var(--success-bg);border:1px solid var(--success-text);
                border-radius:10px;color:var(--success-text);font-size:13px;
                display:flex;align-items:center;gap:10px;box-shadow:0 4px 20px rgba(0,0,0,.12)">
        <i class="ti ti-circle-check"></i>
        {{ session('suspension_success') }}
    </div>
@endif

{{-- ── Modal: solicitar suspensión ─────────────────────────── --}}
<div id="modalSuspension" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);
     z-index:1000;align-items:center;justify-content:center;padding:20px">
    <div style="background:var(--surface);border-radius:14px;width:100%;max-width:480px;
                box-shadow:0 20px 60px rgba(0,0,0,.18)">
        <div style="display:flex;align-items:center;justify-content:space-between;
                    padding:18px 20px 14px;border-bottom:1px solid var(--border)">
            <div style="font-size:15px;font-weight:600;color:var(--text)">
                <i class="ti ti-player-pause" style="color:var(--primary);margin-right:6px"></i>
                Solicitar suspensión de instructoría
            </div>
            <button onclick="document.getElementById('modalSuspension').classList.remove('open');document.getElementById('modalSuspension').style.display='none';document.body.style.overflow=''"
                style="background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:18px;padding:2px 5px;border-radius:5px">
                <i class="ti ti-x"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('instructor.suspension.store') }}">
            @csrf
            <div style="padding:20px;display:flex;flex-direction:column;gap:16px">

                <div style="padding:12px 14px;background:var(--primary-50);border:1px solid var(--primary-100);
                            border-radius:8px;font-size:12px;color:var(--text-soft);
                            display:flex;gap:8px;align-items:flex-start">
                    <i class="ti ti-info-circle" style="color:var(--primary);flex-shrink:0;margin-top:1px"></i>
                    <span>Tu solicitud será enviada a tu coordinador para revisión. Mientras esté pendiente, tu cuenta seguirá activa. Si es aprobada, no podrás iniciar sesión hasta que sea reactivada.</span>
                </div>

                @if($errors->has('suspension'))
                    <div style="padding:10px 14px;background:#FEF2F2;border:1px solid #FECACA;
                                border-radius:8px;font-size:12px;color:#B91C1C;
                                display:flex;gap:8px;align-items:center">
                        <i class="ti ti-alert-circle"></i>
                        {{ $errors->first('suspension') }}
                    </div>
                @endif

                <div style="display:flex;flex-direction:column;gap:5px">
                    <label style="font-size:12px;font-weight:600;color:var(--text-soft)">Tipo de solicitud</label>
                    <select name="type" required
                        style="padding:9px 12px;border:1px solid var(--border);border-radius:8px;
                               background:var(--bg);color:var(--text);font-size:13px;outline:none;
                               font-family:inherit">
                        <option value="">Seleccionar...</option>
                        <option value="voluntary">Solicitud voluntaria</option>
                        <option value="force_majeure">Fuerza mayor (salud, emergencia familiar, etc.)</option>
                        <option value="other">Otra razón</option>
                    </select>
                </div>

                <div style="display:flex;flex-direction:column;gap:5px">
                    <label style="font-size:12px;font-weight:600;color:var(--text-soft)">
                        Explica el motivo de tu solicitud
                    </label>
                    <textarea name="reason" required minlength="20" maxlength="2000" rows="4"
                        placeholder="Describe tu situación con el mayor detalle posible para que el coordinador pueda evaluarla..."
                        style="resize:vertical;font-family:inherit;font-size:13px;padding:9px 12px;
                               border:1px solid var(--border);border-radius:8px;background:var(--bg);
                               color:var(--text);outline:none;transition:border-color .15s"
                        onfocus="this.style.borderColor='var(--accent)'"
                        onblur="this.style.borderColor='var(--border)'">{{ old('reason') }}</textarea>
                    <span style="font-size:11px;color:var(--text-muted)">Mínimo 20 caracteres.</span>
                </div>

            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px;
                        padding:14px 20px;border-top:1px solid var(--border)">
                <button type="button"
                    onclick="document.getElementById('modalSuspension').classList.remove('open');document.getElementById('modalSuspension').style.display='none';document.body.style.overflow=''"
                    style="padding:7px 14px;border-radius:8px;border:1px solid var(--border);
                           background:transparent;color:var(--text-soft);font-size:13px;cursor:pointer">
                    Cancelar
                </button>
                <button type="submit"
                    style="display:inline-flex;align-items:center;gap:6px;padding:7px 16px;
                           border-radius:8px;border:none;background:var(--primary);
                           color:#fff;font-size:13px;font-weight:500;cursor:pointer">
                    <i class="ti ti-send"></i> Enviar solicitud
                </button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
// Abrir el modal con display:flex cuando se activa la clase 'open'
const ms = document.getElementById('modalSuspension');
if (ms) {
    const observer = new MutationObserver(() => {
        if (ms.classList.contains('open')) ms.style.display = 'flex';
        else ms.style.display = 'none';
    });
    observer.observe(ms, { attributes: true, attributeFilter: ['class'] });
    ms.addEventListener('click', e => {
        if (e.target === ms) { ms.classList.remove('open'); document.body.style.overflow = ''; }
    });

    @if($errors->has('suspension') || $errors->has('type') || $errors->has('reason'))
        document.addEventListener('DOMContentLoaded', () => ms.classList.add('open'));
    @endif
}
</script>
@endpush
