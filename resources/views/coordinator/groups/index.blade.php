@extends('layouts.coordinator', ['title' => 'Grupos de clase'])

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/coordinator/groups.css') }}">
@endpush

{{-- $groups, $cycles, $instructors y $filters vienen de ClassGroupController@index --}}

@section('content')

{{-- HEADER --}}
<div class="page-header">
    <div>
        <h1 class="page-title">Grupos de clase</h1>
        <p class="page-sub">Gestión de grupos, instructores y estudiantes</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="openCreateModal()">
        <i class="ti ti-plus" aria-hidden="true"></i> Nuevo grupo
    </button>
</div>

@if(session('success'))
    <div class="alert-success" role="alert">
        <i class="ti ti-circle-check" aria-hidden="true"></i>
        {{ session('success') }}
    </div>
@endif

{{-- Errores del flujo de asignación de instructor (named bag 'assign') --}}
@if($errors->assign->any())
    <div class="alert-error" role="alert" id="assignErrorAlert">
        <i class="ti ti-alert-triangle" aria-hidden="true"></i>
        <div>
            <strong>No se pudo asignar el instructor</strong>
            {{ $errors->assign->first() }}
        </div>
    </div>
@endif

{{-- Filtros por GET (columnas `name`, `professor`, `semester`, `modality`) --}}
<form method="GET" action="{{ route('coordinator.groups.index') }}" class="toolbar" id="filterForm">
    <div class="search-wrap">
        <i class="ti ti-search" aria-hidden="true"></i>
        <input class="search-input" type="search" name="search" placeholder="Buscar por materia, docente..."
               value="{{ $filters['search'] }}" id="searchInput">
    </div>
    <button type="submit" class="btn btn-ghost" style="font-size:13px;padding:8px 12px">Filtrar</button>
    <select class="filter-select" name="cycle" id="filterCycle" onchange="this.form.submit()">
        <option value="">Todos los ciclos</option>
        @foreach($cycles as $cycle)
            <option value="{{ $cycle }}" @selected($filters['cycle'] === $cycle)>{{ $cycle }}</option>
        @endforeach
    </select>
    <select class="filter-select" name="modality" id="filterModality" onchange="this.form.submit()">
        <option value="">Todas las modalidades</option>
        <option value="Presencial" @selected($filters['modality'] === 'Presencial')>Presencial</option>
        <option value="En línea" @selected($filters['modality'] === 'En línea')>En línea</option>
    </select>
</form>

{{-- TABLA --}}
<div class="table-card">
    <div class="table-wrap">
        <table id="groupsTable">
            <thead>
                <tr>
                    <th>Materia</th>
                    <th>Instructor</th>
                    <th>Ciclo</th>
                    <th>Horario</th>
                    <th>Modalidad</th>
                    <th>Aula</th>
                    <th>Estudiantes</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($groups as $group)
                    <tr
                        data-subject="{{ strtolower($group['subject']) }}"
                        data-teacher="{{ strtolower($group['teacher']) }}"
                        data-cycle="{{ $group['cycle'] }}"
                        data-modality="{{ $group['modality'] }}"
                    >
                        <td>
                            <div class="td-main">{{ $group['subject'] }}</div>
                            <div class="td-sub">{{ $group['teacher'] }}</div>
                        </td>
                        <td>
                            @if($group['instructor'])
                                <div style="display:flex;align-items:center;gap:7px">
                                    <div class="avatar-xs">
                                        {{ strtoupper(substr($group['instructor'], 0, 2)) }}
                                    </div>
                                    <span style="font-size:12px;color:var(--text)">{{ $group['instructor'] }}</span>
                                </div>
                            @else
                                <span class="no-instructor">Sin asignar</span>
                            @endif
                        </td>
                        <td>
                            <span class="cycle-tag">{{ $group['cycle'] }}</span>
                        </td>
                        <td style="font-size:12px">{{ $group['schedule'] }}</td>
                        <td>
                            <span class="badge {{ $group['modality'] === 'Presencial' ? 'badge-presencial' : 'badge-linea' }}">
                                <i class="ti {{ $group['modality'] === 'Presencial' ? 'ti-building' : 'ti-video' }}" style="font-size:11px" aria-hidden="true"></i>
                                {{ $group['modality'] }}
                            </span>
                        </td>
                        <td class="td-classroom">{{ $group['classroom'] }}</td>
                        <td>
                            <a href="{{ route('coordinator.groups.enrolled', $group['id']) }}"
                               class="badge badge-students badge-students-link"
                               title="Ver estudiantes inscritos">
                                <i class="ti ti-users" style="font-size:11px" aria-hidden="true"></i>
                                {{ $group['students'] }}
                            </a>
                        </td>
                        <td>
                            <div class="dropdown">
                                <button class="dropdown-btn" onclick="toggleDropdown(this)">
                                    Acciones <i class="ti ti-chevron-down" style="font-size:12px" aria-hidden="true"></i>
                                </button>
                                <div class="dropdown-menu">
                                    {{-- Pasar true si el grupo ya tiene instructor asignado --}}
                                    <button class="dropdown-item"
                                        onclick="openInstructorModal({{ $group['id'] }})">
                                        <i class="ti ti-user-check" style="color:var(--primary)"></i>
                                        {{ $group['instructor'] ? 'Reasignar instructor' : 'Asignar instructor' }}
                                    </button>
                                    <a class="dropdown-item" href="{{ route('coordinator.groups.enrolled', $group['id']) }}">
                                        <i class="ti ti-users" style="color:var(--primary)"></i>
                                        Ver estudiantes
                                    </a>
                                    <a class="dropdown-item" href="{{ route('coordinator.groups.students', $group['id']) }}">
                                        <i class="ti ti-users-plus" style="color:var(--primary-400)"></i>
                                        Agregar estudiantes
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <button type="button" class="dropdown-item"
                                        onclick="openEditModal({{ $group['id'] }})">
                                        <i class="ti ti-pencil" aria-hidden="true"></i>
                                        Editar grupo
                                    </button>
                                    <button type="button" class="dropdown-item danger"
                                        onclick="openDeleteModal({{ $group['id'] }})">
                                        <i class="ti ti-trash" aria-hidden="true"></i>
                                        Eliminar grupo
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="empty-state">
                            <i class="ti ti-books-off" aria-hidden="true"></i>
                            <p>No hay grupos registrados aún</p>
                            <button type="button" class="btn btn-primary" onclick="openCreateModal()" style="margin-top:10px">
                                <i class="ti ti-plus"></i> Crear el primero
                            </button>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- MODAL CREAR / EDITAR --}}
<div class="modal-overlay" id="modalForm" role="dialog" aria-modal="true">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title" id="modalTitle">Nuevo grupo</div>
            <button class="modal-close" onclick="closeModal('modalForm')" aria-label="Cerrar">
                <i class="ti ti-x" aria-hidden="true"></i>
            </button>
        </div>
        <form method="POST" id="groupForm" action="{{ route('coordinator.groups.store') }}">
            @csrf
            <span id="methodSpoof"></span>
            <div class="modal-body">

                @if ($errors->any())
                    <div class="alert-error" role="alert" style="margin-bottom:12px">
                        <ul style="margin:0;padding-left:18px;font-size:13px">
                            @foreach ($errors->all() as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="field">
                    <label class="field-label" for="subject">Materia</label>
                    <input class="input" id="subject" name="subject" type="text" placeholder="Ej. Programación I" required
                           value="{{ old('subject') }}">
                </div>

                <div class="field">
                    <label class="field-label" for="teacher">Docente</label>
                    <input class="input" id="teacher" name="teacher" type="text" placeholder="Ej. Ing. Roberto Chávez" required
                           value="{{ old('teacher') }}">
                </div>

                <div class="field-row">
                    <div class="field">
                        <label class="field-label" for="cycle">Ciclo</label>
                        <input
                            class="input" id="cycle" name="cycle" type="text"
                            placeholder="Ej. 01-2026"
                            pattern="(01|02)-[0-9]{4}"
                            title="Formato: 01-YYYY o 02-YYYY (Ej. 01-2026)"
                            required
                            value="{{ old('cycle') }}"
                            oninput="validateCycle(this)"
                        >
                        <span class="field-error" id="cycleError"></span>
                        <span class="field-hint">Ciclo 1 → 01-2026 · Ciclo 2 → 02-2026</span>
                    </div>
                    <div class="field">
                        <label class="field-label" for="modality">Modalidad</label>
                        <select class="input" id="modality" name="modality" onchange="toggleClassroom()" required>
                            <option value="">Seleccionar...</option>
                            <option value="Presencial" @selected(old('modality') === 'Presencial')>Presencial</option>
                            <option value="En línea" @selected(old('modality') === 'En línea')>En línea</option>
                        </select>
                    </div>
                </div>

                {{-- Widget de horario estructurado --}}
                <div class="field">
                    <label class="field-label">Días de clases</label>
                    <div class="day-toggles" id="dayToggles">
                        @foreach(['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'] as $day)
                            <button type="button" class="day-toggle" data-day="{{ $day }}"
                                    onclick="toggleDay(this)">
                                {{ mb_substr($day, 0, 3) }}
                            </button>
                        @endforeach
                    </div>
                </div>
                <div class="field-row">
                    <div class="field">
                        <label class="field-label" for="timeStart">Hora de inicio</label>
                        <input class="input" id="timeStart" type="time" oninput="updateSchedulePreview()">
                    </div>
                    <div class="field">
                        <label class="field-label" for="timeEnd">Hora de fin</label>
                        <input class="input" id="timeEnd" type="time" oninput="updateSchedulePreview()">
                    </div>
                </div>
                <div class="schedule-preview" id="schedulePreview" style="display:none">
                    <i class="ti ti-clock" aria-hidden="true"></i>
                    <span id="schedulePreviewText"></span>
                </div>
                {{-- Input oculto que recibe el valor formateado --}}
                <input type="hidden" id="schedule" name="schedule" value="{{ old('schedule') }}">

                <div class="field" id="fieldClassroom" style="display:none">
                    <label class="field-label" for="classroom" id="classroomLabel">Aula física</label>
                    <input class="input" id="classroom" name="classroom" type="text" placeholder="Ej. Aula 204 — Edificio A"
                           value="{{ old('classroom') }}">
                </div>

                <div class="field" id="fieldLink" style="display:none">
                    <label class="field-label" for="link">Enlace virtual</label>
                    <input class="input" id="link" name="link" type="url" placeholder="Ej. https://meet.google.com/abc-xyz"
                           value="{{ old('link') }}">
                    <span class="field-hint">El enlace será visible para el instructor al iniciar la sesión.</span>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('modalForm')">Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <i class="ti ti-device-floppy" aria-hidden="true"></i>
                    <span id="btnText">Guardar</span>
                </button>
            </div>
        </form>
    </div>
</div>

{{-- MODAL GESTIONAR INSTRUCTORIA * Asigna y desasigna instructor del grupo y registra los detalles de la instructoria --}}
<div class="modal-overlay" id="modalInstructor" role="dialog" aria-modal="true">
    <div class="modal" style="max-width:460px">
        <div class="modal-header" style="flex-direction:column;align-items:stretch;gap:0;padding-bottom:0">
            <div style="display:flex;align-items:center;justify-content:space-between;padding-bottom:14px">
                <div class="modal-title">Gestionar instructor</div>
                <button class="modal-close" onclick="closeModal('modalInstructor')" aria-label="Cerrar">
                    <i class="ti ti-x" aria-hidden="true"></i>
                </button>
            </div>
            {{-- Chip del grupo seleccionado --}}
            <div id="instructorGroupChip" style="display:flex;align-items:center;gap:10px;background:var(--primary-50);border:1px solid var(--primary-100);border-radius:8px;padding:10px 14px;margin-bottom:14px">
                <i class="ti ti-books" style="color:var(--primary);font-size:16px;flex-shrink:0"></i>
                <div>
                    <div style="font-size:13px;font-weight:600;color:var(--primary)" id="instructorGroupName">—</div>
                    <div style="font-size:11px;color:var(--text-muted)" id="instructorGroupCycle">—</div>
                </div>
                <div id="instructorCurrentBadge" style="margin-left:auto;font-size:11px;color:var(--text-muted)"></div>
            </div>
        </div>

        <form method="POST" id="assignInstructorForm" action="#">
            @csrf

            <div style="padding:20px">

                {{-- Buscador --}}
                <div style="position:relative;margin-bottom:10px">
                    <i class="ti ti-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:15px;pointer-events:none"></i>
                    <input
                        type="search"
                        id="instructorSearch"
                        class="input"
                        style="padding-left:34px"
                        placeholder="Buscar por nombre o especialidad..."
                        oninput="filterInstructors()"
                    >
                </div>

                {{-- Contador --}}
                <div style="font-size:11px;color:var(--text-muted);margin-bottom:10px">
                    Mostrando <span id="visibleCount">0</span> de
                    <span id="totalCount">0</span> instructores
                </div>

                {{-- Lista con scroll --}}
                <div style="max-height:240px;overflow-y:auto;padding-right:2px">
                    <div id="instructorList">
                        @forelse($instructors as $instructor)
                            <label class="instructor-option"
                                   data-name="{{ strtolower($instructor->user?->name ?? '') }}"
                                   data-career="{{ strtolower($instructor->major ?? '') }}">
                                <input type="radio" name="instructor_id" value="{{ $instructor->id }}"
                                       style="display:none"
                                       onchange="onInstructorSelect('{{ addslashes($instructor->user?->name) }}', this)">
                                <div class="inst-avatar">
                                    {{ strtoupper(substr($instructor->user?->name ?? 'IN', 0, 2)) }}
                                </div>
                                <div style="flex:1">
                                    <div class="inst-name">{{ $instructor->user?->name ?? '—' }}</div>
                                    <div class="inst-career">{{ $instructor->major ?? '—' }}</div>
                                </div>
                                <div class="inst-check"><i class="ti ti-check" style="font-size:10px"></i></div>
                            </label>
                        @empty
                            <p style="font-size:12px;color:var(--text-muted);text-align:center;padding:20px 0">
                                No hay instructores disponibles en tu coordinación.
                            </p>
                        @endforelse
                    </div>
                    <div id="emptySearch" style="display:none;text-align:center;padding:24px 0;color:var(--text-muted);font-size:13px">
                        <i class="ti ti-user-search" style="font-size:28px;display:block;margin-bottom:8px;opacity:.4"></i>
                        No se encontraron instructores
                    </div>
                </div>

                {{-- Preview seleccionado --}}
                <div id="selectedPreview" style="display:none;align-items:center;gap:8px;background:var(--primary-50);border:1px solid var(--primary-100);border-radius:8px;padding:8px 12px;margin-top:12px;font-size:12px;color:var(--primary)">
                    <i class="ti ti-circle-check" style="font-size:14px;flex-shrink:0"></i>
                    <span id="selectedName"></span>
                </div>

                {{-- Nota informativa --}}
                <div style="margin-top:12px;padding:10px 12px;background:var(--accent-50);border:1px solid var(--accent-100);border-radius:8px;font-size:12px;color:var(--text-soft);display:flex;gap:8px;align-items:flex-start">
                    <i class="ti ti-info-circle" style="color:var(--accent);font-size:14px;flex-shrink:0;margin-top:1px"></i>
                    <span>El instructor completará los datos de horario, modalidad y aula una vez coordinado con el docente y los estudiantes.</span>
                </div>

            </div>

            <div class="modal-footer">
                <div>
                    <button type="button" class="btn btn-unassign" id="btnUnassign" style="display:none" onclick="confirmUnassign()">
                        <i class="ti ti-user-minus"></i> Desasignar
                    </button>
                </div>
                <div style="display:flex;gap:8px">
                    <button type="button" class="btn btn-ghost" onclick="closeModal('modalInstructor')">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-user-check"></i> Asignar instructor
                    </button>
                </div>
            </div>

        </form>
    </div>
</div>

{{-- MODAL DESASIGNAR INSTRUCTOR --}}
<div class="modal-overlay" id="modalUnassign" role="dialog" aria-modal="true">
    <div class="modal modal-sm">
        <div class="modal-body" style="padding:28px 24px;text-align:center">
            <div style="width:52px;height:52px;border-radius:14px;background:var(--warning-bg,#FEF3C7);display:flex;align-items:center;justify-content:center;font-size:24px;color:var(--warning-text,#92400E);margin:0 auto 14px">
                <i class="ti ti-user-minus" aria-hidden="true"></i>
            </div>
            <div class="confirm-title">¿Desasignar instructor?</div>
            <p class="confirm-desc" style="margin-top:8px">
                <strong id="unassignInstructorName"></strong> será removido del grupo
                <strong id="unassignGroupName"></strong>.<br>
                Podrás asignar otro instructor cuando lo necesites.
            </p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal('modalUnassign')">Cancelar</button>
            <form method="POST" id="unassignForm" action="">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-unassign">
                    <i class="ti ti-user-minus" aria-hidden="true"></i> Sí, desasignar
                </button>
            </form>
        </div>
    </div>
</div>

{{-- MODAL ELIMINAR --}}
<div class="modal-overlay" id="modalDelete" role="dialog" aria-modal="true">
    <div class="modal modal-sm">
        <div class="modal-body" style="padding:28px 24px;text-align:center">
            <div class="confirm-icon">
                <i class="ti ti-trash" aria-hidden="true"></i>
            </div>
            <div class="confirm-title">¿Eliminar grupo?</div>
            <p class="confirm-desc">
                Esta acción eliminará el grupo <strong id="deleteGroupName"></strong> y todos sus datos asociados. No se puede deshacer.
            </p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal('modalDelete')">Cancelar</button>
            <form method="POST" id="deleteForm" action="">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger">
                    <i class="ti ti-trash" aria-hidden="true"></i> Sí, eliminar
                </button>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    // Filas actuales y URLs para PUT/DELETE/asignar sin strings frágiles en onclick
    const GROUPS_ROWS = @json($groups);
    const GROUPS_STORE_URL = @json(route('coordinator.groups.store'));
    const GROUPS_BASE_URL = @json(url('/coordinador/groups'));

    // ── Dropdown ───────────────────────────────────────────
    function toggleDropdown(btn) {
        const menu = btn.nextElementSibling;
        const isOpen = menu.classList.contains('open');
        document.querySelectorAll('.dropdown-menu.open').forEach(m => m.classList.remove('open'));
        if (!isOpen) {
            menu.classList.add('open');
            // Posicionar el dropdown con position fixed
            const btnRect = btn.getBoundingClientRect();
            const menuHeight = 200;
            const menuWidth = 190;
            const spaceBelow = window.innerHeight - btnRect.bottom - 10;
            menu.style.position = 'fixed';
            menu.style.right = (window.innerWidth - btnRect.right) + 'px';
            menu.style.width = menuWidth + 'px';
            if (spaceBelow < menuHeight) {
                menu.style.top = (btnRect.top - menuHeight - 5) + 'px';
                menu.style.transform = 'none';
            } else {
                menu.style.top = (btnRect.bottom + 5) + 'px';
                menu.style.transform = 'none';
            }
            menu.style.bottom = 'auto';
        }
    }
    document.addEventListener('click', e => {
        if (!e.target.closest('.dropdown'))
            document.querySelectorAll('.dropdown-menu.open').forEach(m => m.classList.remove('open'));
    });

    // ── Modales ────────────────────────────────────────────
    function openModal(id) {
        document.getElementById(id).classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function closeModal(id) {
        document.getElementById(id).classList.remove('open');
        document.body.style.overflow = '';
        if (id === 'modalForm') resetForm();
    }

    function openCreateModal() {
        resetForm();
        toggleClassroom();
        openModal('modalForm');
    }
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', e => {
            if (e.target === overlay) closeModal(overlay.id);
        });
    });

    // Reset formulario (alta nueva): POST a store, sin spoof PUT
    function resetForm() {
        const form = document.getElementById('groupForm');
        form.reset();
        form.action = GROUPS_STORE_URL;
        document.getElementById('methodSpoof').innerHTML = '';
        document.getElementById('modalTitle').textContent = 'Nuevo grupo';
        document.getElementById('btnText').textContent = 'Guardar';
        document.getElementById('fieldClassroom').style.display = 'none';
        document.getElementById('fieldLink').style.display = 'none';
        document.getElementById('cycle').classList.remove('is-invalid');
        document.getElementById('cycleError').textContent = '';
        resetScheduleWidget();
    }

    // Mostrar aula física o enlace según modalidad (columna classroom en BD)
    function toggleClassroom() {
        const val = document.getElementById('modality').value;
        document.getElementById('fieldClassroom').style.display = val === 'Presencial' ? 'flex' : 'none';
        document.getElementById('fieldLink').style.display      = val === 'En línea'   ? 'flex' : 'none';
    }

    // ── Validación de ciclo ────────────────────────────────
    function validateCycle(input) {
        const re = /^(01|02)-\d{4}$/;
        const err = document.getElementById('cycleError');
        if (!input.value) {
            input.classList.remove('is-invalid');
            err.textContent = '';
        } else if (!re.test(input.value)) {
            input.classList.add('is-invalid');
            err.textContent = 'Formato inválido. Usa 01-2026 o 02-2026.';
        } else {
            input.classList.remove('is-invalid');
            err.textContent = '';
        }
    }

    // ── Widget de horario estructurado ─────────────────────
    function toggleDay(btn) {
        btn.classList.toggle('active');
        updateSchedulePreview();
    }

    function updateSchedulePreview() {
        const days  = Array.from(document.querySelectorAll('.day-toggle.active')).map(b => b.dataset.day);
        const start = document.getElementById('timeStart').value;
        const end   = document.getElementById('timeEnd').value;

        if (!days.length || !start || !end) {
            document.getElementById('schedulePreview').style.display = 'none';
            document.getElementById('schedule').value = '';
            return;
        }

        const daysStr = days.length === 1
            ? days[0]
            : days.slice(0, -1).join(', ') + ' y ' + days[days.length - 1];

        const fmt = t => {
            const [h, m] = t.split(':');
            return `${h}:${m}`;
        };
        const str = `${daysStr} ${fmt(start)} - ${fmt(end)}`;
        document.getElementById('schedule').value = str;
        document.getElementById('schedulePreviewText').textContent = str;
        document.getElementById('schedulePreview').style.display = 'flex';
    }

    function resetScheduleWidget() {
        document.querySelectorAll('.day-toggle').forEach(b => b.classList.remove('active'));
        document.getElementById('timeStart').value = '';
        document.getElementById('timeEnd').value   = '';
        document.getElementById('schedule').value  = '';
        document.getElementById('schedulePreview').style.display = 'none';
    }

    /** Intenta parsear "Lunes y Miércoles 07:00 - 09:00" de vuelta al widget */
    function restoreScheduleWidget(str) {
        if (!str) return;
        const timeRe = /(\d{1,2}:\d{2})\s*[-–]\s*(\d{1,2}:\d{2})/;
        const m = str.match(timeRe);
        if (!m) return;
        const start = m[1].padStart(5, '0');
        const end   = m[2].padStart(5, '0');
        const daysPart = str.substring(0, str.indexOf(m[0])).trim();
        const allDays  = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
        document.querySelectorAll('.day-toggle').forEach(btn => {
            if (allDays.some(d => d === btn.dataset.day && daysPart.includes(d))) {
                btn.classList.add('active');
            }
        });
        document.getElementById('timeStart').value = start;
        document.getElementById('timeEnd').value   = end;
        updateSchedulePreview();
    }

    function openEditModal(id) {
        const g = GROUPS_ROWS.find(r => r.id === id);
        if (!g) return;
        document.getElementById('modalTitle').textContent = 'Editar grupo';
        document.getElementById('btnText').textContent = 'Actualizar';
        document.getElementById('methodSpoof').innerHTML = '<input type="hidden" name="_method" value="PUT">';
        const form = document.getElementById('groupForm');
        form.action = GROUPS_BASE_URL + '/' + id;
        document.getElementById('subject').value = g.subject;
        document.getElementById('teacher').value = g.teacher;
        document.getElementById('cycle').value = g.cycle;
        document.getElementById('modality').value = g.modality;
        resetScheduleWidget();
        restoreScheduleWidget(g.schedule);
        if (g.modality === 'Presencial') {
            document.getElementById('classroom').value = g.classroom || '';
            document.getElementById('link').value = '';
        } else {
            document.getElementById('link').value = g.classroom || '';
            document.getElementById('classroom').value = '';
        }
        toggleClassroom();
        openModal('modalForm');
    }

    function openDeleteModal(id) {
        const g = GROUPS_ROWS.find(r => r.id === id);
        if (!g) return;
        document.getElementById('deleteGroupName').textContent = g.subject;
        document.getElementById('deleteForm').action = GROUPS_BASE_URL + '/' + id;
        openModal('modalDelete');
    }

    @if($errors->assign->any())
    {{-- Errores de asignar instructor: scroll al alert, NO abrir modal de grupo --}}
    document.addEventListener('DOMContentLoaded', function () {
        const alert = document.getElementById('assignErrorAlert');
        if (alert) alert.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
    @elseif($errors->any())
    {{-- Errores de crear/editar grupo: re-abrir el modal de grupo --}}
    document.addEventListener('DOMContentLoaded', function () {
        openModal('modalForm');
        toggleClassroom();
    });
    @endif

    // ── Modal gestionar instructor ─────────────────────────

    /**
     * openInstructorModal(id)
     * Función principal: carga datos del grupo desde GROUPS_ROWS,
     * setea el action del form, pre-selecciona el instructor actual
     * y pre-rellena el tab de datos de asignación.
     */
    function openInstructorModal(id) {
        const g = GROUPS_ROWS.find(r => r.id === id);
        if (!g) return;
        _currentGroupId = id;

        // ── Chip del grupo ───────────────────────────────
        document.getElementById('instructorGroupName').textContent = g.subject;
        document.getElementById('instructorGroupCycle').textContent = 'Ciclo ' + g.cycle + ' · ' + g.modality;

        const currentBadge = document.getElementById('instructorCurrentBadge');
        if (g.instructor) {
            currentBadge.innerHTML = '<span style="background:var(--success-bg);color:var(--success-text);padding:2px 10px;border-radius:20px;font-weight:500">Instructor actual: ' + g.instructor + '</span>';
        } else {
            currentBadge.innerHTML = '<span style="background:var(--warning-bg);color:var(--warning-text);padding:2px 10px;border-radius:20px;font-weight:500">Sin instructor</span>';
        }

        // ── Acción del formulario ────────────────────────
        document.getElementById('assignInstructorForm').action = GROUPS_BASE_URL + '/' + id + '/assign-instructor';

        // ── Botón desasignar ─────────────────────────────
        document.getElementById('btnUnassign').style.display = g.instructor_id ? 'inline-flex' : 'none';

        // ── Reset búsqueda ───────────────────────────────
        document.getElementById('instructorSearch').value = '';
        filterInstructors();

        // ── Pre-seleccionar instructor actual ────────────
        document.querySelectorAll('#instructorList .instructor-option').forEach(opt => {
            opt.classList.remove('selected');
            const radio = opt.querySelector('input[type=radio]');
            if (radio) {
                const isSelected = g.instructor_id && parseInt(radio.value) === g.instructor_id;
                radio.checked = isSelected;
                if (isSelected) opt.classList.add('selected');
            }
        });

        // Preview del seleccionado
        const preview = document.getElementById('selectedPreview');
        if (g.instructor) {
            preview.style.display = 'flex';
            document.getElementById('selectedName').textContent = g.instructor + ' seleccionado/a';
        } else {
            preview.style.display = 'none';
        }

        openModal('modalInstructor');
    }

    function filterInstructors() {
        const q = document.getElementById('instructorSearch').value.toLowerCase().trim();
        const options = document.querySelectorAll('#instructorList .instructor-option');
        let visible = 0;

        options.forEach(opt => {
            const name   = opt.dataset.name   || '';
            const career = opt.dataset.career || '';
            const match  = !q || name.includes(q) || career.includes(q);
            opt.style.display = match ? '' : 'none';
            if (match) visible++;
        });

        document.getElementById('visibleCount').textContent = visible;
        document.getElementById('totalCount').textContent   = options.length;
        document.getElementById('emptySearch').style.display = visible === 0 ? 'block' : 'none';
    }

    function onInstructorSelect(name, input) {
        document.querySelectorAll('#instructorList .instructor-option').forEach(o => o.classList.remove('selected'));
        input.closest('.instructor-option').classList.add('selected');
        const preview = document.getElementById('selectedPreview');
        preview.style.display = 'flex';
        document.getElementById('selectedName').textContent = name + ' seleccionado/a';
    }

    // _currentGroupId se guarda al abrir el modal de instructor
    // para poder usarlo en el modal de desasignación.
    let _currentGroupId = null;

    function confirmUnassign() {
        const g = GROUPS_ROWS.find(r => r.id === _currentGroupId);
        if (!g) return;

        document.getElementById('unassignInstructorName').textContent = g.instructor || 'El instructor';
        document.getElementById('unassignGroupName').textContent      = g.subject;
        document.getElementById('unassignForm').action =
            GROUPS_BASE_URL + '/' + _currentGroupId + '/unassign-instructor';

        closeModal('modalInstructor');
        openModal('modalUnassign');
    }

    // Inicializar contadores al cargar
    document.addEventListener('DOMContentLoaded', function () {
        const total = document.querySelectorAll('#instructorList .instructor-option').length;
        document.getElementById('visibleCount').textContent = total;
        document.getElementById('totalCount').textContent   = total;
    });
</script>
@endpush