{{--
    Vista INSTRUCTOR: Sesión de instructoría.
    - Header condensado con info del grupo + badge de estado.
    - Columna izquierda: stats en vivo + detalles del grupo/asignación.
    - Columna derecha: QR grande + modo proyección + "Cómo funciona".
    - Modal de confirmación para finalizar.
    - Overlay pantalla completa para proyectar en clase.
    - La fecha/hora se toman automáticamente al generar el QR (sin inputs visibles).
--}}
@extends('layouts.instructor', ['title' => 'Sesión de instructoría'])

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/instructor/session.css') }}">
@endpush

@section('content')

@php
    $schedule      = $assignment?->schedule ?? $group?->schedule;
    $modality      = $assignment?->modality ?? $group?->modality;
    $location      = $assignment?->classroom ?? $assignment?->virtual_link ?? $group?->classroom;
    $studentCount  = $group ? $group->students()->count() : 0;
    $professor     = $group?->professor ?? '—';
    $instructorName = $assignment?->instructor?->user?->name ?? auth()->user()?->name ?? '—';
    $initials      = collect(explode(' ', $group?->name ?? 'G'))
        ->filter()->take(2)->map(fn($w) => mb_strtoupper(mb_substr($w,0,1)))->implode('');
    $subInfo       = collect([$schedule, $studentCount ? $studentCount.' estudiantes' : null, $group?->semester])
        ->filter()->implode(' · ');
@endphp

{{-- ── Header condensado ──────────────────────────────── --}}
<div class="sess-header">
    <div class="sess-header-left">
        <div class="sess-header-av">{{ $initials }}</div>
        <div>
            <div class="sess-header-name">{{ $group?->name ?? 'Sin grupo asignado' }}</div>
            <div class="sess-header-sub">{{ $subInfo ?: 'Sin datos de grupo' }}</div>
        </div>
    </div>
    <span class="sess-badge sess-badge-waiting" id="statusBadge">
        <span class="sess-dot sess-dot-waiting" id="statusDot"></span>
        <span id="statusText">En espera</span>
    </span>
</div>

{{-- ── Dos columnas ───────────────────────────────────── --}}
<div class="main-grid">

    {{-- ── Columna izquierda ──────────────────────────── --}}
    <div class="info-col">

        {{-- Tarjeta principal: stats en vivo + detalles --}}
        <div class="sess-card card-info">

            {{-- Stats (solo cuando hay sesión activa) --}}
            <div id="stateActive" style="display:none">
                <div class="sess-stats-row">
                    <div class="sess-stat-box">
                        <div class="sess-stat-val accent" id="liveAttendanceCount">{{ $openAttendanceCount ?? 0 }}</div>
                        <div class="sess-stat-lbl"><i class="ti ti-users"></i> Asistencias</div>
                    </div>
                    <div class="sess-stat-box">
                        <div class="sess-stat-val" id="livePending">—</div>
                        <div class="sess-stat-lbl"><i class="ti ti-user-exclamation"></i> Pendientes</div>
                    </div>
                    <div class="sess-stat-box">
                        <div class="sess-stat-val accent" id="liveElapsed">00:00</div>
                        <div class="sess-stat-lbl"><i class="ti ti-hourglass"></i> Transcurrido</div>
                    </div>
                </div>
                <div class="sess-card-divider"></div>
            </div>

            {{-- Detalles del grupo y asignación --}}
            <div class="sess-card-body detail-grid">
                <div>
                    <div class="detail-lbl"><i class="ti ti-clock"></i> Hora de inicio</div>
                    <div class="detail-val" id="liveStartTime">—</div>
                </div>
                <div>
                    <div class="detail-lbl"><i class="ti ti-calendar"></i> Hoy</div>
                    <div class="detail-val">{{ now()->translatedFormat('l j M, Y') }}</div>
                    <div class="detail-hint" id="liveClock">{{ now()->format('H:i:s') }}</div>
                </div>
                <div>
                    <div class="detail-lbl"><i class="ti ti-school"></i> Docente titular</div>
                    <div class="detail-val">{{ $professor }}</div>
                </div>
                <div>
                    <div class="detail-lbl"><i class="ti ti-user"></i> Instructor</div>
                    <div class="detail-val">{{ $instructorName }}</div>
                </div>
                <div>
                    <div class="detail-lbl"><i class="ti ti-device-laptop"></i> Modalidad</div>
                    <div class="detail-val">{{ $modality ?? '—' }}</div>
                    @if($location)
                        <div class="detail-hint">{{ $location }}</div>
                    @endif
                </div>
                <div>
                    <div class="detail-lbl"><i class="ti ti-users"></i> Estudiantes inscritos</div>
                    <div class="detail-val">{{ $studentCount }}</div>
                    <div class="detail-hint">Solo ellos pueden marcar asistencia</div>
                </div>
            </div>
        </div>

    </div>

    {{-- ── Columna derecha: QR ────────────────────────── --}}
    <div class="qr-col">
        <div class="sess-card card-qr">

            {{-- Header borgoña --}}
            <div class="qr-card-header">
                <div>
                    <div class="qr-card-title">Código QR de asistencia</div>
                    <div class="qr-card-sub">Los estudiantes escanean y registran su carnet</div>
                </div>
                <i class="ti ti-qrcode qr-card-indicator" id="qrIndicator"></i>
            </div>

            {{-- QR hero --}}
            <div class="qr-hero">
                <div class="qr-frame" id="qrFrame">
                    <div class="qr-placeholder" id="qrPlaceholder">
                        <i class="ti ti-qrcode" style="font-size:44px;color:var(--border);display:block;margin-bottom:10px"></i>
                        Inicia la sesión para generar el QR
                    </div>
                    <img id="qrImage" alt="Código QR de asistencia" hidden>
                    <canvas id="qrCanvas" hidden></canvas>
                </div>

                {{-- Código de sesión --}}
                <div class="sess-code-wrap">
                    <div class="sess-code-lbl">Código de sesión</div>
                    <div class="sess-code" id="sessionCode">—</div>
                </div>

                {{-- Enlace clicable --}}
                <div id="attendanceLinkWrap" hidden>
                    <a href="#" id="attendanceLink" class="att-link" target="_blank" rel="noopener">—</a>
                </div>

                {{-- Botón proyectar (solo cuando hay sesión activa) --}}
                <div id="projectWrap" style="display:none;flex-direction:column;align-items:center;width:100%;gap:6px">
                    <button class="btn-project" onclick="openFullscreen()">
                        <i class="ti ti-arrows-maximize"></i> Proyectar en pantalla completa
                    </button>
                    <div class="project-hint">
                        <i class="ti ti-info-circle"></i>
                        Muestra el QR grande para que todos puedan escanear
                    </div>
                </div>
            </div>

            {{-- Cómo funciona --}}
            <div class="how-strip">
                <div class="how-title"><i class="ti ti-info-circle"></i> ¿Cómo funciona?</div>
                <div class="how-steps">
                    <div class="how-step"><i class="ti ti-player-play"></i><span>Inicia sesión</span></div>
                    <div class="how-step"><i class="ti ti-qrcode"></i><span>Escanean el QR</span></div>
                    <div class="how-step"><i class="ti ti-id-badge"></i><span>Ingresan carnet</span></div>
                    <div class="how-step"><i class="ti ti-circle-check"></i><span>Se registra</span></div>
                </div>
            </div>

            {{-- Notice instructoría finalizada --}}
            @if($assignmentFinalized ?? false)
                <div class="sess-finalized-notice">
                    <i class="ti ti-lock"></i>
                    <span>Tu coordinador finalizó esta instructoría. No puedes generar QR hasta que la reactive.</span>
                </div>
            @endif

            {{-- Acciones --}}
            <div class="qr-actions">
                <button class="btn-start" id="btnStart" onclick="startSession()"
                        @disabled(!$group || ($assignmentFinalized ?? false))>
                    <i class="ti ti-player-play"></i> Generar QR e iniciar
                </button>
                <button class="btn-end" id="btnEnd" style="display:none" onclick="openEndModal()">
                    <i class="ti ti-player-stop"></i> Finalizar sesión
                </button>
            </div>

        </div>
    </div>

</div>

{{-- ══ Modal: confirmar finalizar ═══════════════════════ --}}
<div class="sess-modal-overlay" id="endModalOverlay">
    <div class="sess-modal">
        <div class="sess-modal-icon"><i class="ti ti-player-stop"></i></div>
        <h2 class="sess-modal-title">¿Finalizar la sesión?</h2>
        <p class="sess-modal-desc">
            Los estudiantes ya no podrán registrar asistencia mediante el QR de esta sesión.
        </p>
        <div class="sess-modal-attendees">
            <i class="ti ti-users"></i>
            <span id="endModalCount">0</span> asistencia(s) registradas
        </div>
        <div class="sess-modal-actions">
            <button class="sess-btn-cancel" onclick="closeEndModal()">
                <i class="ti ti-x"></i> Cancelar
            </button>
            <button class="sess-btn-confirm" id="btnConfirmEnd" onclick="endSession()">
                <i class="ti ti-player-stop"></i> Sí, finalizar
            </button>
        </div>
    </div>
</div>

{{-- ══ Overlay pantalla completa (proyección) ═══════════ --}}
<div class="fs-overlay" id="fsOverlay">
    <button class="fs-close" onclick="closeFullscreen()">
        <i class="ti ti-x"></i> Cerrar
    </button>
    <div class="fs-qr-box" id="fsQrBox">
        <div style="font-size:13px;color:#aaa;text-align:center;padding:1rem">
            Inicia la sesión para ver el QR
        </div>
    </div>
    <div class="fs-code" id="fsCode">—</div>
    <div class="fs-hint">
        Los estudiantes escanean el QR o abren el enlace:<br>
        <span id="fsLink">—</span>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
    const storeUrl      = @json(route('instructor.session.store'));
    const endUrl        = @json(route('instructor.session.end'));
    const pollUrl       = @json(route('instructor.session.attendance-count'));
    const csrf          = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const totalStudents = @json($studentCount);

    let currentSessionId = @json($openSession?->id);
    let attendanceUrl    = @json($openAttendanceUrl ?? null);
    let sessionCode      = @json($openSession?->session_code);
    const dbStartedAt    = @json(
        $openSession
            ? ($openSession->date->format('Y-m-d') . 'T' . $openSession->start_time)
            : null
    );

    let sessionStartTime = null;
    let elapsedInterval  = null;
    let pollInterval     = null;

    // ── QR ───────────────────────────────────────────────
    const placeholder = document.getElementById('qrPlaceholder');
    const qrImage     = document.getElementById('qrImage');
    const qrCanvas    = document.getElementById('qrCanvas');
    const linkWrap    = document.getElementById('attendanceLinkWrap');
    const linkEl      = document.getElementById('attendanceLink');

    function qrSrc(url, size) {
        return `https://api.qrserver.com/v1/create-qr-code/?size=${size}&margin=8&data=` + encodeURIComponent(url);
    }

    function setLink(url) {
        if (!url) { linkWrap.hidden = true; return; }
        linkWrap.hidden = false;
        linkEl.href = url;
        linkEl.textContent = url;
    }

    function showPlaceholder() {
        placeholder.hidden = false;
        qrImage.hidden = true;
        qrCanvas.hidden = true;
        setLink(null);
        document.getElementById('fsQrBox').innerHTML =
            '<div style="font-size:13px;color:#aaa;text-align:center;padding:1rem">Inicia la sesión para ver el QR</div>';
    }

    function renderQr(url) {
        if (!url) { showPlaceholder(); return; }
        placeholder.hidden = true;
        setLink(url);
        qrImage.onload  = () => { qrImage.hidden = false; qrCanvas.hidden = true; };
        qrImage.onerror = () => showPlaceholder();
        qrImage.src     = qrSrc(url, '200x200');
        // Fullscreen
        const fsImg = new Image(300, 300);
        fsImg.src = qrSrc(url, '300x300');
        fsImg.alt = 'QR proyección';
        fsImg.style.cssText = 'display:block;border-radius:8px';
        const fsBox = document.getElementById('fsQrBox');
        fsBox.innerHTML = '';
        fsBox.appendChild(fsImg);
    }

    // ── Reloj en vivo ─────────────────────────────────────
    setInterval(() => {
        const el = document.getElementById('liveClock');
        if (el) el.textContent = new Date().toLocaleTimeString('es-SV',
            { hour:'2-digit', minute:'2-digit', second:'2-digit' });
    }, 1000);

    // ── Cronómetro ────────────────────────────────────────
    function startElapsed(fromTime) {
        sessionStartTime = (fromTime instanceof Date && !isNaN(fromTime)) ? fromTime : new Date();
        document.getElementById('liveStartTime').textContent =
            sessionStartTime.toLocaleTimeString('es-SV', { hour:'2-digit', minute:'2-digit' });
        clearInterval(elapsedInterval);
        elapsedInterval = setInterval(() => {
            const diff = Math.max(0, Math.floor((Date.now() - sessionStartTime.getTime()) / 1000));
            const h = Math.floor(diff / 3600);
            const m = String(Math.floor((diff % 3600) / 60)).padStart(2,'0');
            const s = String(diff % 60).padStart(2,'0');
            document.getElementById('liveElapsed').textContent =
                h > 0 ? `${String(h).padStart(2,'0')}:${m}:${s}` : `${m}:${s}`;
        }, 1000);
    }
    function stopElapsed() { clearInterval(elapsedInterval); elapsedInterval = null; }

    // ── Polling de asistencias ────────────────────────────
    function updateCounts(count) {
        document.getElementById('liveAttendanceCount').textContent = count;
        document.getElementById('livePending').textContent = Math.max(0, totalStudents - count);
    }

    function startPolling(sessionId) {
        stopPolling();
        pollInterval = setInterval(async () => {
            try {
                const res = await fetch(`${pollUrl}?session_id=${sessionId}`, {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!res.ok) return;
                const data = await res.json();
                if (data.count !== undefined) updateCounts(data.count);
            } catch (_) {}
        }, 10_000);
    }
    function stopPolling() { clearInterval(pollInterval); pollInterval = null; }

    // ── UI activa / en espera ─────────────────────────────
    function setUiActive(active) {
        document.getElementById('btnStart').style.display    = active ? 'none' : 'flex';
        document.getElementById('btnEnd').style.display      = active ? 'flex' : 'none';
        document.getElementById('stateActive').style.display = active ? 'block' : 'none';
        document.getElementById('projectWrap').style.display = active ? 'flex' : 'none';

        const badge = document.getElementById('statusBadge');
        badge.className = 'sess-badge ' + (active ? 'sess-badge-active' : 'sess-badge-waiting');
        document.getElementById('statusDot').className  = 'sess-dot ' + (active ? 'sess-dot-active' : 'sess-dot-waiting');
        document.getElementById('statusText').textContent = active ? 'Sesión activa' : 'En espera';
        document.getElementById('qrFrame').classList.toggle('active', active);
        const ind = document.getElementById('qrIndicator');
        ind.className = 'ti qr-card-indicator ' + (active ? 'ti-circle-check active' : 'ti-qrcode');
    }

    // ── Iniciar sesión (fecha/hora automáticas) ───────────
    window.startSession = async function () {
        const now        = new Date();
        const date       = now.toISOString().split('T')[0];
        const start_time = now.toTimeString().slice(0,5);
        try {
            const res  = await fetch(storeUrl, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type':'application/json', 'Accept':'application/json',
                           'X-CSRF-TOKEN':csrf, 'X-Requested-With':'XMLHttpRequest' },
                body: JSON.stringify({ date, start_time }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) { alert(data.message || 'No se pudo iniciar la sesión.'); return; }

            currentSessionId = data.session_id;
            attendanceUrl    = data.attendance_url;
            sessionCode      = data.session_code;

            document.getElementById('sessionCode').textContent = sessionCode;
            document.getElementById('fsCode').textContent      = sessionCode;
            document.getElementById('fsLink').textContent      = attendanceUrl;
            renderQr(attendanceUrl);
            setUiActive(true);
            updateCounts(0);
            startElapsed(now);
            startPolling(currentSessionId);
        } catch (e) { alert('Error de conexión al iniciar la sesión.'); }
    };

    // ── Modal finalizar ───────────────────────────────────
    window.openEndModal = function () {
        document.getElementById('endModalCount').textContent =
            document.getElementById('liveAttendanceCount').textContent;
        document.getElementById('endModalOverlay').classList.add('open');
        document.body.style.overflow = 'hidden';
    };
    window.closeEndModal = function () {
        document.getElementById('endModalOverlay').classList.remove('open');
        document.body.style.overflow = '';
    };
    document.getElementById('endModalOverlay').addEventListener('click', e => {
        if (e.target === document.getElementById('endModalOverlay')) closeEndModal();
    });

    window.endSession = async function () {
        if (!currentSessionId) return;
        const btn = document.getElementById('btnConfirmEnd');
        btn.disabled = true;
        btn.innerHTML = '<i class="ti ti-loader-2" style="animation:spin .8s linear infinite"></i> Finalizando…';
        try {
            const res  = await fetch(endUrl, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type':'application/json', 'Accept':'application/json',
                           'X-CSRF-TOKEN':csrf, 'X-Requested-With':'XMLHttpRequest' },
                body: JSON.stringify({ session_id: currentSessionId }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                closeEndModal();
                alert(data.message || 'No se pudo finalizar.');
            } else {
                if (data.attendance_count !== undefined) updateCounts(data.attendance_count);
                currentSessionId = null; attendanceUrl = null; sessionCode = null;
                document.getElementById('sessionCode').textContent = '—';
                document.getElementById('fsCode').textContent      = '—';
                document.getElementById('liveStartTime').textContent = '—';
                showPlaceholder();
                setUiActive(false);
                stopElapsed();
                stopPolling();
                closeEndModal();
            }
        } catch (e) {
            closeEndModal();
            alert('Error de conexión al finalizar.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="ti ti-player-stop"></i> Sí, finalizar';
        }
    };

    // ── Pantalla completa ─────────────────────────────────
    window.openFullscreen  = () => document.getElementById('fsOverlay').classList.add('show');
    window.closeFullscreen = () => document.getElementById('fsOverlay').classList.remove('show');

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') { closeFullscreen(); closeEndModal(); }
    });

    // ── Restaurar estado al recargar ──────────────────────
    if (currentSessionId && attendanceUrl) {
        document.getElementById('sessionCode').textContent = sessionCode || '—';
        document.getElementById('fsCode').textContent      = sessionCode || '—';
        document.getElementById('fsLink').textContent      = attendanceUrl || '—';
        renderQr(attendanceUrl);
        setUiActive(true);
        updateCounts({{ $openAttendanceCount ?? 0 }});
        startElapsed(dbStartedAt ? new Date(dbStartedAt) : new Date());
        startPolling(currentSessionId);
    } else {
        showPlaceholder();
        setUiActive(false);
    }
})();
</script>
<style>@keyframes spin { to { transform: rotate(360deg); } }</style>
@endpush
