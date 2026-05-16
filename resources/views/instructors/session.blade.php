@extends('layouts.instructor', ['title' => 'Iniciar sesion'])

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/instructor/session.css') }}">
@endpush

@section('content')

<a href="{{ route('instructor.dashboard') }}" class="back-link">
    <i class="ti ti-arrow-left" aria-hidden="true"></i> Volver al dashboard
</a>

<div class="page-header">
    <div>
        <h1 class="page-title">Iniciar sesion de instructoria</h1>
        <p class="page-sub">Confirma los datos y genera el QR para el registro de asistencia</p>
    </div>
    <span class="status-badge status-waiting" id="statusBadge">
        <span class="status-dot dot-waiting" id="statusDot"></span>
        <span id="statusText">En espera</span>
    </span>
</div>

<div class="main-grid">

    {{-- COLUMNA IZQUIERDA --}}
    <div class="info-col">

        {{-- Info del grupo --}}
        {{-- Al integrar backend: reemplazar con $group->subject, $group->cycle, etc. --}}
        <div class="group-card">
            <div class="group-card-header">
                <div>
                    <div class="group-name">
                        {{ $group->name ?? 'Sin grupo asignado' }}
                    </div>
                    <div class="group-sub">
                        {{ $group ? ($group->professor . ' · ' . ($group->code ?? '')) : 'No tienes una instructoría asignada' }}
                    </div>
                </div>
                <span class="cycle-tag">
                    {{ $group->semester ?? 'N/A' }}
                </span>
            </div>
            <div class="group-card-body">
                @if($group)
                    <div>
                        <div class="detail-label">Horario</div>
                        <div class="detail-value">{{ $group->schedule ?? 'No disponible' }}</div>
                        <div class="detail-sub">Sesión activa</div>
                    </div>
                    <div>
                        <div class="detail-label">Modalidad</div>
                        <div class="detail-value">{{ $group->modality ?? 'N/A' }}</div>
                        <div class="detail-sub">{{ $group->classroom ?? $group->virtual_link ?? 'Sin ubicación' }}</div>
                    </div>
                    <div>
                        <div class="detail-label">Estudiantes</div>
                        <div class="detail-value">{{ $group->students()->count() }} inscritos</div>
                        <div class="detail-sub">Prom. 25 asistentes</div>
                    </div>
                    <div>
                        <div class="detail-label">Última sesión</div>
                        <div class="detail-value">Hace 2 días</div>
                        <div class="detail-sub">26 asistentes</div>
                    </div>
                @else
                    <div style="padding:24px 12px; color:var(--text-muted);">
                        No hay instructoría asignada a este usuario. Contacta a tu coordinador para que te asigne un grupo.
                    </div>
                @endif
            </div>
        </div>

        {{-- Datos de la sesion --}}
        <div class="session-card">
            <div class="session-title">
                <i class="ti ti-clipboard-list" aria-hidden="true"></i>
                Datos de la sesion
            </div>
            <div class="session-grid">
                <div class="session-field">
                    <label class="session-label" for="session-date">Fecha</label>
                    <input class="session-input" id="session-date" type="date"
                           value="{{ now()->format('Y-m-d') }}"
                           {{-- Al integrar backend: name="date" --}}>
                </div>
                <div class="session-field">
                    <label class="session-label" for="session-time">Hora de inicio</label>
                    <input class="session-input" id="session-time" type="time"
                           value="{{ now()->format('H:i') }}"
                           {{-- Al integrar backend: name="start_time" --}}>
                </div>
            </div>
        </div>

        {{-- Stats mini --}}
        <div class="stats-row">
            <div class="mini-stat">
                <div class="mini-val" style="color:var(--primary)">12</div>
                <div class="mini-label">Sesiones realizadas</div>
            </div>
            <div class="mini-stat">
                <div class="mini-val" style="color:#4C8FD4">87%</div>
                <div class="mini-label">Asistencia promedio</div>
            </div>
            <div class="mini-stat">
                <div class="mini-val" style="color:var(--primary)">268</div>
                <div class="mini-label">Total asistencias</div>
            </div>
        </div>

    </div>

    {{-- COLUMNA DERECHA — QR --}}
    <div class="qr-col">
        <div class="qr-card">
            <div class="qr-card-header">
                <div>
                    <div class="qr-card-title">Codigo QR de asistencia</div>
                    <div class="qr-card-sub">Los estudiantes deben escanear este codigo</div>
                </div>
                <div class="qr-status-indicator" id="qrIndicator">
                    <i class="ti ti-qrcode" style="font-size:20px;color:rgba(255,255,255,.4)"></i>
                </div>
            </div>
            <div class="qr-body">

                <div class="qr-wrap" id="qrWrap">
                    <canvas id="qrCanvas" width="190" height="190"></canvas>
                </div>

                <div class="qr-code-label">Codigo de sesion</div>
                <div class="qr-code-value" id="sessionCode">PRG101-2026-013</div>

                <p class="qr-hint">
                    El QR se genera al iniciar la sesion y permanece activo<br>
                    hasta que finalices la clase manualmente.
                </p>

                <div class="qr-actions">
                    <button type="button" class="btn btn-primary" id="btnStart" onclick="startSession()">
                        <i class="ti ti-player-play" aria-hidden="true"></i> Generar QR e iniciar
                    </button>
                    <button type="button" class="btn btn-danger" id="btnEnd" style="display:none" onclick="endSession()">
                        <i class="ti ti-player-stop" aria-hidden="true"></i> Finalizar sesion
                    </button>
                </div>
            </div>
        </div>

        {{-- Info como funciona --}}
        <div class="qr-info">
            <div class="qr-info-title">
                <i class="ti ti-info-circle" aria-hidden="true"></i> Como funciona?
            </div>
            <div class="qr-info-item">
                <i class="ti ti-player-play" aria-hidden="true"></i>
                Haz clic en "Generar QR e iniciar" para comenzar
            </div>
            <div class="qr-info-item">
                <i class="ti ti-qrcode" aria-hidden="true"></i>
                Muestra el QR a tus estudiantes para que lo escaneen
            </div>
            <div class="qr-info-item">
                <i class="ti ti-clock" aria-hidden="true"></i>
                El QR permanece activo durante toda la sesion
            </div>
            <div class="qr-info-item">
                <i class="ti ti-player-stop" aria-hidden="true"></i>
                Presiona "Finalizar sesion" cuando termines la clase
            </div>
        </div>

    </div>

</div>

@endsection

@push('scripts')
<script>
    // ── Dibujar QR en canvas ───────────────────────────────
    function drawQR(canvas, active) {
        const ctx  = canvas.getContext('2d');
        const size = canvas.width;
        ctx.clearRect(0, 0, size, size);

        if (!active) {
            ctx.fillStyle = '#F1F4F8';
            ctx.fillRect(0, 0, size, size);
            ctx.fillStyle = '#94A3B8';
            ctx.font = '13px "DM Sans", system-ui';
            ctx.textAlign = 'center';
            ctx.fillText('QR no generado', size / 2, size / 2 - 8);
            ctx.font = '11px "DM Sans", system-ui';
            ctx.fillText('Inicia la sesion para verlo', size / 2, size / 2 + 12);
            return;
        }

        // QR simulado — el backend generara el real con una libreria como SimpleSoftwareIO/simple-qrcode
        const cell = 6;
        const pattern = [
            [1,1,1,1,1,1,1,0,1,0,1,1,1,0,1,1,1,1,1,1,1,0,0,0,1,0,0,1,1,0],
            [1,0,0,0,0,0,1,0,0,1,0,0,0,0,1,0,0,0,0,0,1,0,1,0,0,1,0,0,1,0],
            [1,0,1,1,1,0,1,0,1,0,1,1,1,0,1,0,1,1,1,0,1,0,0,1,0,0,1,0,0,0],
            [1,0,1,1,1,0,1,0,0,1,1,0,0,0,1,0,1,1,1,0,1,0,1,0,1,1,0,1,1,0],
            [1,0,1,1,1,0,1,0,1,1,0,1,0,1,1,0,1,1,1,0,1,0,0,0,1,0,1,1,0,0],
            [1,0,0,0,0,0,1,0,0,0,1,0,1,0,1,0,0,0,0,0,1,0,1,1,0,1,1,0,0,0],
            [1,1,1,1,1,1,1,0,1,0,1,0,1,0,1,1,1,1,1,1,1,0,1,0,1,0,1,0,1,0],
            [0,0,0,0,0,0,0,0,1,1,0,1,1,1,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0],
            [1,0,1,1,0,1,1,0,0,0,1,0,0,0,1,1,0,1,0,1,0,1,0,0,0,1,0,1,1,0],
            [0,1,1,0,0,1,0,0,1,1,0,1,1,0,0,1,1,0,1,0,1,0,1,1,0,0,1,0,1,0],
            [1,0,0,1,0,0,1,0,0,0,0,1,1,0,1,0,0,0,1,1,0,1,0,0,1,1,0,1,0,0],
            [0,1,0,1,1,0,0,1,1,0,1,0,0,1,0,1,0,1,0,1,1,0,1,0,0,1,1,0,1,0],
            [1,1,1,0,1,0,1,1,0,1,0,1,1,0,0,0,1,0,0,0,1,1,0,1,0,0,1,0,0,0],
            [0,0,0,0,0,0,0,1,1,1,0,0,1,0,1,1,0,1,0,1,0,0,1,0,1,1,0,1,1,0],
            [1,1,1,1,1,1,1,0,0,0,1,0,0,1,1,0,1,0,0,1,1,0,0,0,0,1,0,0,1,0],
            [1,0,0,0,0,0,1,0,1,1,0,1,1,0,0,1,0,1,1,0,0,1,0,1,0,0,1,1,0,0],
            [1,0,1,1,1,0,1,0,0,0,1,0,0,1,1,0,0,0,1,1,0,0,1,0,1,1,0,0,1,0],
            [1,0,1,1,1,0,1,0,1,0,1,1,0,0,0,1,0,1,0,0,1,1,0,1,0,0,1,0,1,0],
            [1,0,1,1,1,0,1,0,0,1,0,0,1,0,1,0,1,0,0,1,1,0,1,0,1,1,0,1,0,0],
            [1,0,0,0,0,0,1,0,1,0,1,0,0,1,0,1,0,1,1,0,0,1,0,1,0,0,1,0,1,0],
            [1,1,1,1,1,1,1,0,0,1,1,0,1,0,1,0,1,0,0,1,1,0,1,0,1,1,0,1,0,0],
        ];

        ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, size, size);
        pattern.forEach((row, r) => {
            row.forEach((val, c) => {
                if (val) {
                    ctx.fillStyle = '#1B4E8B';
                    ctx.fillRect(c * cell + 5, r * cell + 5, cell - 1, cell - 1);
                }
            });
        });
    }

    // ── Estado inicial ─────────────────────────────────────
    drawQR(document.getElementById('qrCanvas'), false);

    // ── Iniciar sesion ─────────────────────────────────────
    function startSession() {
        drawQR(document.getElementById('qrCanvas'), true);

        document.getElementById('btnStart').style.display = 'none';
        document.getElementById('btnEnd').style.display   = 'flex';

        const badge = document.getElementById('statusBadge');
        badge.className = 'status-badge status-active';
        document.getElementById('statusDot').className = 'status-dot dot-active';
        document.getElementById('statusText').textContent = 'Sesion activa';

        document.getElementById('qrIndicator').innerHTML =
            '<i class="ti ti-circle-check" style="font-size:20px;color:rgba(255,255,255,.9)"></i>';

        // Al integrar backend: hacer POST para crear la sesion y obtener el QR real
        // fetch("{{ route('instructor.session.store') }}", { method:'POST', ... })
    }

    // ── Finalizar sesion ───────────────────────────────────
    function endSession() {
        if (!confirm('Finalizar la sesion? Ya no se podran registrar mas asistencias.')) return;

        drawQR(document.getElementById('qrCanvas'), false);
        document.getElementById('btnStart').style.display = 'flex';
        document.getElementById('btnEnd').style.display   = 'none';

        const badge = document.getElementById('statusBadge');
        badge.className = 'status-badge status-waiting';
        document.getElementById('statusDot').className = 'status-dot dot-waiting';
        document.getElementById('statusText').textContent = 'En espera';

        document.getElementById('qrIndicator').innerHTML =
            '<i class="ti ti-qrcode" style="font-size:20px;color:rgba(255,255,255,.4)"></i>';

        // Al integrar backend: hacer POST para cerrar la sesion
        // fetch("{{ route('instructor.session.end') }}", { method:'POST', ... })
    }
</script>
@endpush