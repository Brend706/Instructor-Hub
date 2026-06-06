@extends('layouts.instructor', ['title' => 'Detalle del grupo'])

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/instructor/groups.css') }}">
@endpush

@section('content')

<a href="{{ route('instructor.groups.index') }}" class="back-link">
    <i class="ti ti-arrow-left" aria-hidden="true"></i> Volver a mis grupos
</a>

<div class="page-header">
    <div>
        <h1 class="page-title">{{ $group->name }}</h1>
        <p class="page-sub">{{ $group->professor }} · Ciclo {{ $group->semester }}</p>
    </div>
    <span class="students-pill">
        <i class="ti ti-users" aria-hidden="true"></i>
        {{ $students->count() }} estudiantes
    </span>
</div>

@if(session('success'))
    <div class="alert-success" role="alert">
        <i class="ti ti-circle-check" aria-hidden="true"></i>
        {{ session('success') }}
    </div>
@endif

@if($errors->any())
    <div class="alert-error" role="alert">
        <i class="ti ti-alert-triangle"></i>
        <div>
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    </div>
@endif

{{-- ── Dos tarjetas: Grupo de clase | Mi instructoría ─── --}}
<div class="info-cards-grid" id="instructoria">

    {{-- Grupo de clase —— datos del classGroup --}}
    <div class="info-card info-card-primary">
        <div class="info-card-header">
            <span style="display:flex;align-items:center;gap:6px">
                <i class="ti ti-school"></i> Grupo de clase
            </span>
        </div>
        <div class="info-card-body">
            <div class="info-item">
                <div class="info-label">Materia</div>
                <div class="info-value">{{ $group->name }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Docente titular</div>
                <div class="info-value">{{ $group->professor ?? '—' }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Ciclo</div>
                <div class="info-value">{{ $group->semester ?? '—' }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Horario del grupo</div>
                <div class="info-value">{{ $group->schedule ?? '—' }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Modalidad</div>
                <div class="info-value">{{ $group->modality ?? '—' }}</div>
                @if($group->classroom)
                    <div class="info-sub">{{ $group->classroom }}</div>
                @endif
            </div>
            <div class="info-item">
                <div class="info-label">Estudiantes inscritos</div>
                <div class="info-value">{{ $students->count() }}</div>
            </div>
        </div>
    </div>

    {{-- Mi instructoría —— datos del InstructorAssignment --}}
    <div class="info-card info-card-accent">
        <div class="info-card-header">
            <span style="display:flex;align-items:center;gap:6px">
                <i class="ti ti-user-check"></i> Mi instructoría
            </span>
            <button class="btn btn-lavanda btn-sm" onclick="openIgModal()">
                <i class="ti ti-edit"></i>
                {{ ($assignment->schedule || $assignment->modality) ? 'Editar' : 'Completar datos' }}
            </button>
        </div>
        <div class="info-card-body">
            <div class="info-item">
                <div class="info-label">Estado</div>
                <div class="info-value">
                    @php $s = strtolower($assignment->status ?? 'activo'); @endphp
                    <span style="display:inline-flex;align-items:center;gap:5px;font-size:12px;
                                 padding:3px 10px;border-radius:20px;font-weight:500;
                                 background:{{ $s === 'activo' ? 'var(--success-bg)' : 'var(--warning-bg)' }};
                                 color:{{ $s === 'activo' ? 'var(--success-text)' : 'var(--warning-text)' }}">
                        <span style="width:6px;height:6px;border-radius:50%;
                                     background:{{ $s === 'activo' ? 'var(--success-text)' : 'var(--warning-text)' }}"></span>
                        {{ ucfirst($assignment->status ?? 'Activo') }}
                    </span>
                </div>
            </div>
            <div class="info-item">
                <div class="info-label">Mi horario</div>
                @if($assignment->schedule)
                    <div class="info-value">{{ $assignment->schedule }}</div>
                @else
                    <div class="info-value pending">Sin definir</div>
                @endif
            </div>
            <div class="info-item">
                <div class="info-label">Modalidad</div>
                @if($assignment->modality)
                    <div class="info-value">{{ $assignment->modality }}</div>
                @else
                    <div class="info-value pending">Sin definir</div>
                @endif
            </div>
            <div class="info-item">
                <div class="info-label">Aula / Enlace</div>
                @if($assignment->classroom)
                    <div class="info-value">{{ $assignment->classroom }}</div>
                @elseif($assignment->virtual_link)
                    <a href="{{ $assignment->virtual_link }}" class="info-link" target="_blank" rel="noopener">
                        {{ $assignment->virtual_link }}
                    </a>
                @else
                    <div class="info-value pending">Sin definir</div>
                @endif
            </div>
        </div>
    </div>

</div>

{{-- ── Tabla de estudiantes ────────────────────────────── --}}
<div class="table-card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Carnet</th>
                    <th>Nombre completo</th>
                    <th>Correo</th>
                </tr>
            </thead>
            <tbody>
                @forelse($students as $i => $student)
                    <tr>
                        <td style="color:var(--text-muted)">{{ $i + 1 }}</td>
                        <td style="font-family:monospace;font-size:12px">{{ $student->carnet }}</td>
                        <td class="td-name">{{ $student->name }}</td>
                        <td style="font-size:12px">{{ $student->email }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="empty-state">
                            <i class="ti ti-users-off" aria-hidden="true"></i>
                            <p>No hay estudiantes inscritos en este grupo.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ══ Modal: editar datos de la instructoría ════════════ --}}
<div class="ig-modal-overlay" id="igModalOverlay">
    <div class="ig-modal">
        <div class="ig-modal-header">
            <div class="ig-modal-title">
                <i class="ti ti-user-check" style="color:var(--accent);margin-right:6px"></i>
                Datos de mi instructoría
            </div>
            <button class="ig-modal-close" onclick="closeIgModal()" aria-label="Cerrar">
                <i class="ti ti-x"></i>
            </button>
        </div>

        <form method="POST"
              action="{{ route('instructor.groups.update', $assignment) }}"
              id="igForm">
            @csrf
            @method('PUT')

            <div class="ig-modal-body">

                {{-- Horario estructurado --}}
                <div class="ig-field">
                    <label class="ig-label">Días de clases</label>
                    <div class="ig-day-toggles" id="igDayToggles">
                        @foreach(['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'] as $day)
                            <button type="button" class="ig-day-toggle" data-day="{{ $day }}"
                                    onclick="igToggleDay(this)">
                                {{ mb_substr($day, 0, 3) }}
                            </button>
                        @endforeach
                    </div>
                </div>
                <div class="ig-field-row">
                    <div class="ig-field">
                        <label class="ig-label" for="igTimeStart">Hora de inicio</label>
                        <input class="ig-input" id="igTimeStart" type="time" oninput="igUpdatePreview()">
                    </div>
                    <div class="ig-field">
                        <label class="ig-label" for="igTimeEnd">Hora de fin</label>
                        <input class="ig-input" id="igTimeEnd" type="time" oninput="igUpdatePreview()">
                    </div>
                </div>
                <div class="ig-schedule-preview" id="igSchedulePreview" style="display:none">
                    <i class="ti ti-clock"></i>
                    <span id="igScheduleText"></span>
                </div>
                <input type="hidden" id="igSchedule" name="schedule"
                       value="{{ $assignment->schedule }}">

                {{-- Modalidad --}}
                <div class="ig-field">
                    <label class="ig-label" for="igModality">Modalidad</label>
                    <select class="ig-input" id="igModality" name="modality"
                            onchange="igToggleLocation()">
                        <option value="">Seleccionar...</option>
                        <option value="Presencial" @selected($assignment->modality === 'Presencial')>Presencial</option>
                        <option value="En línea"   @selected($assignment->modality === 'En línea')>En línea</option>
                    </select>
                </div>

                <div class="ig-field" id="igClassroomField" style="display:none">
                    <label class="ig-label" for="igClassroom">Aula física</label>
                    <input class="ig-input" id="igClassroom" name="classroom" type="text"
                           placeholder="Ej. Aula 204 — Edificio A"
                           value="{{ $assignment->classroom }}">
                </div>

                <div class="ig-field" id="igLinkField" style="display:none">
                    <label class="ig-label" for="igLink">Enlace virtual</label>
                    <input class="ig-input" id="igLink" name="virtual_link" type="url"
                           placeholder="https://meet.google.com/..."
                           value="{{ $assignment->virtual_link }}">
                    <span class="ig-hint">Google Meet, Teams, Zoom u otro enlace.</span>
                </div>

                {{-- Nota informativa --}}
                <div style="padding:10px 12px;background:var(--primary-50);border:1px solid var(--primary-100);border-radius:8px;font-size:12px;color:var(--text-soft);display:flex;gap:8px;align-items:flex-start">
                    <i class="ti ti-info-circle" style="color:var(--primary);font-size:14px;flex-shrink:0;margin-top:1px"></i>
                    <span>Estos datos son específicos de tu instructoría y pueden diferir del horario del grupo de clase.</span>
                </div>

            </div>

            <div class="ig-modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeIgModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <i class="ti ti-device-floppy"></i> Guardar
                </button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
// ── Modal ─────────────────────────────────────────────────
function openIgModal() {
    igToggleLocation();
    igRestoreSchedule(@json($assignment->schedule));
    document.getElementById('igModalOverlay').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeIgModal() {
    document.getElementById('igModalOverlay').classList.remove('open');
    document.body.style.overflow = '';
}
document.getElementById('igModalOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeIgModal();
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeIgModal(); });

// ── Modalidad ─────────────────────────────────────────────
function igToggleLocation() {
    const val = document.getElementById('igModality').value;
    document.getElementById('igClassroomField').style.display = val === 'Presencial' ? 'flex' : 'none';
    document.getElementById('igLinkField').style.display      = val === 'En línea'   ? 'flex' : 'none';
}

// ── Widget de horario ─────────────────────────────────────
function igToggleDay(btn) {
    btn.classList.toggle('active');
    igUpdatePreview();
}

function igUpdatePreview() {
    const days  = Array.from(document.querySelectorAll('.ig-day-toggle.active')).map(b => b.dataset.day);
    const start = document.getElementById('igTimeStart').value;
    const end   = document.getElementById('igTimeEnd').value;

    if (!days.length || !start || !end) {
        document.getElementById('igSchedulePreview').style.display = 'none';
        document.getElementById('igSchedule').value = '';
        return;
    }

    const daysStr = days.length === 1
        ? days[0]
        : days.slice(0,-1).join(', ') + ' y ' + days[days.length - 1];

    const fmt = t => t.slice(0, 5);
    const str = `${daysStr} ${fmt(start)} - ${fmt(end)}`;
    document.getElementById('igSchedule').value     = str;
    document.getElementById('igScheduleText').textContent = str;
    document.getElementById('igSchedulePreview').style.display = 'flex';
}

function igRestoreSchedule(str) {
    // Limpiar
    document.querySelectorAll('.ig-day-toggle').forEach(b => b.classList.remove('active'));
    document.getElementById('igTimeStart').value = '';
    document.getElementById('igTimeEnd').value   = '';
    document.getElementById('igSchedulePreview').style.display = 'none';

    if (!str) return;
    const timeRe = /(\d{1,2}:\d{2})\s*[-–]\s*(\d{1,2}:\d{2})/;
    const m = str.match(timeRe);
    if (!m) return;

    document.getElementById('igTimeStart').value = m[1].padStart(5,'0');
    document.getElementById('igTimeEnd').value   = m[2].padStart(5,'0');

    const daysPart = str.substring(0, str.indexOf(m[0])).trim();
    ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'].forEach(d => {
        if (daysPart.includes(d)) {
            document.querySelectorAll('.ig-day-toggle').forEach(btn => {
                if (btn.dataset.day === d) btn.classList.add('active');
            });
        }
    });
    igUpdatePreview();
}

// Abrir el modal automáticamente si viene con errors (del back)
@if($errors->any())
    document.addEventListener('DOMContentLoaded', () => openIgModal());
@endif

// Abrir si viene con hash #instructoria (desde el index)
if (window.location.hash === '#instructoria' && !@json($assignment->schedule || $assignment->modality)) {
    document.addEventListener('DOMContentLoaded', () => openIgModal());
}
</script>
@endpush
