{{--
    Vista INSTRUCTOR: "Iniciar sesión de instructoría".
    - Izquierda: datos del grupo, fecha/hora, estadísticas, contador de asistencias en sesión activa.
    - Derecha: QR centrado, enlace debajo del QR, código de sesión (PROGRAMA-2026-004), iniciar/finalizar.
    El JavaScript llama a instructor.session.store / instructor.session.end y pinta el QR con la URL pública.
--}}
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

    <div class="info-col">

        @php
            $schedule = $assignment?->schedule ?? $group?->schedule;
            $modality = $assignment?->modality ?? $group?->modality;
            $location = $assignment?->classroom ?? $assignment?->virtual_link ?? $group?->classroom;
            $studentCount = $group ? $group->students()->count() : 0;
        @endphp
        <div class="group-card">
            <div class="group-card-header">
                <div>
                    <div class="group-name">{{ $group->name ?? 'Sin grupo asignado' }}</div>
                    <div class="group-sub">
                        {{ $group ? $group->professor : 'No tienes una instructoría asignada' }}
                    </div>
                </div>
                <span class="cycle-tag">{{ $group->semester ?? 'N/A' }}</span>
            </div>
            <div class="group-card-body">
                @if($group)
                    <div>
                        <div class="detail-label">Horario</div>
                        <div class="detail-value">{{ $schedule ?? 'No disponible' }}</div>
                    </div>
                    <div>
                        <div class="detail-label">Modalidad</div>
                        <div class="detail-value">{{ $modality ?? 'N/A' }}</div>
                        <div class="detail-sub">{{ $location ?? 'Sin ubicación' }}</div>
                    </div>
                    <div>
                        <div class="detail-label">Estudiantes</div>
                        <div class="detail-value">{{ $studentCount }} inscritos</div>
                        <div class="detail-sub">Solo ellos pueden marcar asistencia</div>
                    </div>
                    <div>
                        <div class="detail-label">Asistencias en sesión</div>
                        <div class="detail-value" id="liveAttendanceCount">{{ $openAttendanceCount ?? 0 }}</div>
                        <div class="detail-sub">Registros con carnet válido</div>
                    </div>
                @else
                    <div style="padding:24px 12px; color:var(--text-muted);">
                        No hay instructoría asignada. Contacta a tu coordinador.
                    </div>
                @endif
            </div>
        </div>

        <div class="session-card">
            <div class="session-title">
                <i class="ti ti-clipboard-list" aria-hidden="true"></i>
                Datos de la sesion
            </div>
            <div class="session-grid">
                <div class="session-field">
                    <label class="session-label" for="session-date">Fecha</label>
                    <input class="session-input" id="session-date" type="date" value="{{ now()->format('Y-m-d') }}">
                </div>
                <div class="session-field">
                    <label class="session-label" for="session-time">Hora de inicio</label>
                    <input class="session-input" id="session-time" type="time" value="{{ now()->format('H:i') }}">
                </div>
            </div>
        </div>

        <div class="stats-row">
            <div class="mini-stat">
                <div class="mini-val" style="color:var(--primary)">{{ $stats['sessions_count'] }}</div>
                <div class="mini-label">Sesiones realizadas</div>
            </div>
            <div class="mini-stat">
                <div class="mini-val" style="color:#4C8FD4">{{ $stats['attendance_avg'] }}%</div>
                <div class="mini-label">Asistencia promedio</div>
            </div>
            <div class="mini-stat">
                <div class="mini-val" style="color:var(--primary)">{{ $stats['total_attendances'] }}</div>
                <div class="mini-label">Total asistencias</div>
            </div>
        </div>

    </div>

    {{-- Columna QR: tarjeta azul "Código QR de asistencia" + bloque "Cómo funciona" --}}
    <div class="qr-col">
        <div class="qr-card">
            <div class="qr-card-header">
                <div>
                    <div class="qr-card-title">Codigo QR de asistencia</div>
                    <div class="qr-card-sub">Los estudiantes escanean y registran su carnet</div>
                </div>
                <div class="qr-status-indicator" id="qrIndicator">
                    <i class="ti ti-qrcode" style="font-size:20px;color:rgba(255,255,255,.4)"></i>
                </div>
            </div>
            <div class="qr-body">
                {{-- Contenedor del QR: antes de iniciar muestra texto; al iniciar, imagen centrada --}}
                <div class="qr-wrap" id="qrWrap">
                    <div class="qr-placeholder" id="qrPlaceholder">QR no generado<br>Inicia la sesion</div>
                    <img id="qrImage" alt="Codigo QR de asistencia" width="190" height="190" hidden>
                    <canvas id="qrCanvas" width="190" height="190" hidden></canvas>
                </div>
                {{-- Enlace que el estudiante puede abrir si no escanea el QR (misma URL que el código) --}}
                <div class="attendance-link-wrap" id="attendanceLinkWrap" hidden>
                    <a href="#" id="attendanceLink" class="attendance-link" target="_blank" rel="noopener noreferrer">
                        Abrir enlace de asistencia
                    </a>
                </div>
                <div class="qr-code-label">Codigo de sesion</div>
                <div class="qr-code-value" id="sessionCode">—</div>
                <p class="qr-hint">
                    El QR enlaza a un formulario donde el estudiante ingresa su carnet.<br>
                    Solo se acepta si está inscrito en esta clase.
                </p>
                <div class="qr-actions">
                    <button type="button" class="btn btn-primary" id="btnStart" onclick="startSession()" @disabled(!$group)>
                        <i class="ti ti-player-play" aria-hidden="true"></i> Generar QR e iniciar
                    </button>
                    <button type="button" class="btn btn-danger" id="btnEnd" style="display:none" onclick="endSession()">
                        <i class="ti ti-player-stop" aria-hidden="true"></i> Finalizar sesion
                    </button>
                </div>
            </div>
        </div>

        <div class="qr-info">
            <div class="qr-info-title"><i class="ti ti-info-circle"></i> Como funciona?</div>
            <div class="qr-info-item"><i class="ti ti-player-play"></i> Inicia la sesión y se genera el QR</div>
            <div class="qr-info-item"><i class="ti ti-qrcode"></i> El estudiante escanea y escribe su carnet</div>
            <div class="qr-info-item"><i class="ti ti-check"></i> Si está en el grupo, se guarda la asistencia</div>
            <div class="qr-info-item"><i class="ti ti-player-stop"></i> Finaliza la sesión al terminar la clase</div>
        </div>
    </div>

</div>

@endsection

@push('scripts')
<script>
(function () {
    // Rutas AJAX del instructor (crear sesión / cerrar sesión).
    const storeUrl = @json(route('instructor.session.store'));
    const endUrl = @json(route('instructor.session.end'));
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    // Si recargó la página con sesión abierta, el backend ya envió id, URL y código.
    let currentSessionId = @json($openSession?->id);
    let attendanceUrl = @json($openAttendanceUrl ?? null);
    let sessionCode = @json($openSession?->session_code);

    const placeholder = document.getElementById('qrPlaceholder');
    const qrImage = document.getElementById('qrImage');
    const qrCanvas = document.getElementById('qrCanvas');
    const linkWrap = document.getElementById('attendanceLinkWrap');
    const linkEl = document.getElementById('attendanceLink');

    // Genera la imagen del QR apuntando a /asistencia/{token} (formulario de carnet del estudiante).
    function qrImageSrc(url) {
        return 'https://api.qrserver.com/v1/create-qr-code/?size=190x190&margin=10&data=' + encodeURIComponent(url);
    }

    // Muestra el enlace clicable debajo del QR con la URL completa.
    function setAttendanceLink(url) {
        if (!url) {
            linkWrap.hidden = true;
            linkEl.removeAttribute('href');
            linkEl.textContent = 'Abrir enlace de asistencia';
            return;
        }
        linkWrap.hidden = false;
        linkEl.href = url;
        linkEl.textContent = url;
    }

    function showPlaceholder() {
        placeholder.hidden = false;
        qrImage.hidden = true;
        qrCanvas.hidden = true;
        setAttendanceLink(null);
    }

    // Pinta el QR centrado y activa el enlace; si falla la imagen externa, intenta canvas.
    function renderQr(url) {
        if (!url) {
            showPlaceholder();
            return;
        }

        placeholder.hidden = true;
        setAttendanceLink(url);

        qrImage.onload = function () {
            qrImage.hidden = false;
            qrCanvas.hidden = true;
        };
        qrImage.onerror = function () {
            if (typeof QRCode !== 'undefined') {
                QRCode.toCanvas(qrCanvas, url, {
                    width: 190,
                    margin: 1,
                    color: { dark: '#1B4E8B', light: '#ffffff' },
                }, function (err) {
                    if (err) {
                        showPlaceholder();
                        return;
                    }
                    qrCanvas.hidden = false;
                    qrImage.hidden = true;
                });
            } else {
                showPlaceholder();
            }
        };
        qrImage.src = qrImageSrc(url);
    }

    function setUiActive(active) {
        document.getElementById('btnStart').style.display = active ? 'none' : 'flex';
        document.getElementById('btnEnd').style.display = active ? 'flex' : 'none';
        const badge = document.getElementById('statusBadge');
        badge.className = active ? 'status-badge status-active' : 'status-badge status-waiting';
        document.getElementById('statusDot').className = active ? 'status-dot dot-active' : 'status-dot dot-waiting';
        document.getElementById('statusText').textContent = active ? 'Sesion activa' : 'En espera';
        document.getElementById('qrIndicator').innerHTML = active
            ? '<i class="ti ti-circle-check" style="font-size:20px;color:rgba(255,255,255,.9)"></i>'
            : '<i class="ti ti-qrcode" style="font-size:20px;color:rgba(255,255,255,.4)"></i>';
        document.getElementById('session-date').disabled = active;
        document.getElementById('session-time').disabled = active;
    }

    // Botón "Generar QR e iniciar": POST crea class_sessions y devuelve attendance_url + session_code.
    window.startSession = async function () {
        const date = document.getElementById('session-date').value;
        const start_time = document.getElementById('session-time').value;
        try {
            const res = await fetch(storeUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ date, start_time }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                alert(data.message || 'No se pudo iniciar la sesión.');
                return;
            }
            currentSessionId = data.session_id;
            attendanceUrl = data.attendance_url;
            sessionCode = data.session_code;
            document.getElementById('sessionCode').textContent = sessionCode;
            renderQr(attendanceUrl);
            setUiActive(true);
        } catch (e) {
            alert('Error de conexión al iniciar la sesión.');
        }
    };

    // Botón "Finalizar sesión": is_open = false; estudiantes ya no pueden enviar carnet.
    window.endSession = async function () {
        if (!currentSessionId) return;
        if (!confirm('¿Finalizar la sesión? Ya no se podrán registrar más asistencias.')) return;
        try {
            const res = await fetch(endUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ session_id: currentSessionId }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                alert(data.message || 'No se pudo finalizar la sesión.');
                return;
            }
            if (data.attendance_count !== undefined) {
                document.getElementById('liveAttendanceCount').textContent = data.attendance_count;
            }
            currentSessionId = null;
            attendanceUrl = null;
            sessionCode = null;
            document.getElementById('sessionCode').textContent = '—';
            showPlaceholder();
            setUiActive(false);
        } catch (e) {
            alert('Error de conexión al finalizar.');
        }
    };

    if (currentSessionId && attendanceUrl) {
        document.getElementById('sessionCode').textContent = sessionCode || '—';
        renderQr(attendanceUrl);
        setUiActive(true);
    } else {
        showPlaceholder();
        setUiActive(false);
    }
})();
</script>
@endpush
