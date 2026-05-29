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
                                        onclick="openInstructor('{{ $group['subject'] }}', '{{ $group['cycle'] }}', {{ $group['instructor'] ? 'true' : 'false' }})">
                                        <i class="ti ti-user-check" style="color:var(--primary)"></i> Asignar instructor
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
                        <input class="input" id="cycle" name="cycle" type="text" placeholder="Ej. 01-2026" required
                               value="{{ old('cycle') }}">
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

                <div class="field">
                    <label class="field-label" for="schedule">Horario</label>
                    <input class="input" id="schedule" name="schedule" type="text" placeholder="Ej. Lunes y Mié 7:00 - 9:00am" required
                           value="{{ old('schedule') }}">
                </div>

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
        <div class="modal-header">
            <div>
                <div class="modal-title">Gestionar instructor</div>
                <div class="modal-subtitle" id="instructorGroupName"></div>
            </div>
            <button class="modal-close" onclick="closeModal('modalInstructor')" aria-label="Cerrar">
                <i class="ti ti-x" aria-hidden="true"></i>
            </button>
        </div>

        {{-- Tabs --}}
        <div class="tab-bar">
            <button class="tab-btn active" onclick="switchInstructorTab('instructor', this)">
                <i class="ti ti-user-check"></i> Asignar instructor
            </button>
            <button class="tab-btn" onclick="switchInstructorTab('assignment', this)">
                <i class="ti ti-settings"></i> Datos de asignación
            </button>
        </div>

        <form method="POST" id="assignInstructorForm" action="#">
            @csrf

            {{-- Tab 1: Instructor --}}
            <div class="tab-panel active" id="itab-instructor">
                <div style="padding:20px">

                    {{-- Buscador --}}
                    <div class="search-box" style="position:relative;margin-bottom:10px">
                        <i class="ti ti-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:15px;pointer-events:none"></i>
                        <input
                            type="search"
                            id="instructorSearch"
                            class="input"
                            style="padding-left:34px"
                            placeholder="Buscar por nombre o carrera..."
                            oninput="filterInstructors()"
                        >
                    </div>

                    {{-- Contador --}}
                    <div style="font-size:11px;color:var(--text-muted);margin-bottom:10px">
                        Mostrando <span id="visibleCount">0</span> de
                        <span id="totalCount">0</span> instructores
                    </div>

                    {{-- Lista con scroll --}}
                    <div id="instructorListWrap" style="max-height:220px;overflow-y:auto;padding-right:2px">
                        <div id="instructorList">
                            @forelse($instructors as $instructor)
                                <label class="instructor-option" data-name="{{ strtolower($instructor->user?->name ?? '') }}" data-career="{{ strtolower($instructor->major ?? '') }}">
                                    <input type="radio" name="instructor_id" value="{{ $instructor->id }}" class="inst-radio" style="display:none" onchange="onInstructorSelect('{{ $instructor->user?->name }}', this)">
                                    <div class="inst-avatar">{{ strtoupper(substr($instructor->user?->name ?? 'IN', 0, 2)) }}</div>
                                    <div style="flex:1">
                                        <div class="inst-name">{{ $instructor->user?->name ?? '—' }}</div>
                                        <div class="inst-career">{{ $instructor->major ?? '—' }}</div>
                                    </div>
                                    <div class="inst-check"><i class="ti ti-check" style="font-size:10px"></i></div>
                                </label>
                            @empty
                                <p style="font-size:12px;color:var(--text-muted);text-align:center;padding:20px 0">
                                    No hay instructores disponibles.
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

                </div>
            </div>

            {{-- Tab 2: Datos de asignación --}}
            <div class="tab-panel" id="itab-assignment" style="display:none;padding:20px">

                <div class="field">
                    <label class="field-label" for="assign-schedule">Horario</label>
                    <input class="input" id="assign-schedule" name="schedule" type="text" placeholder="Ej. Lunes y Mié 7:00 - 9:00am">
                </div>

                <div class="field">
                    <label class="field-label" for="assign-modality">Modalidad</label>
                    <select class="input" id="assign-modality" name="modality" onchange="toggleAssignClassroom()">
                        <option value="">Seleccionar...</option>
                        <option value="presencial">Presencial</option>
                        <option value="linea">En línea</option>
                    </select>
                </div>

                <div class="field" id="assignClassroomField" style="display:none">
                    <label class="field-label" for="assign-classroom">Aula física</label>
                    <input class="input" id="assign-classroom" name="classroom" type="text" placeholder="Ej. Aula 204 — Edificio A">
                </div>

                <div class="field" id="assignLinkField" style="display:none">
                    <label class="field-label" for="assign-link">Plataforma / Enlace virtual</label>
                    <input class="input" id="assign-link" name="link" type="url" placeholder="Ej. https://meet.google.com/abc-xyz">
                    <span class="field-hint">Google Meet, Teams, Zoom u otro enlace.</span>
                </div>

                <div class="field" style="margin-bottom:0">
                    <label class="field-label" for="assign-status">Estado de la asignación</label>
                    <select class="input" id="assign-status" name="status">
                        <option value="active" selected>Activo</option>
                        <option value="finished">Finalizado</option>
                    </select>
                    <span class="field-hint">El estado "Finalizado" indica que el instructor ya no imparte este grupo.</span>
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
                        <i class="ti ti-device-floppy"></i> Guardar
                    </button>
                </div>
            </div>

        </form>
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
    }

    // Mostrar aula física o enlace según modalidad (columna classroom en BD)
    function toggleClassroom() {
        const val = document.getElementById('modality').value;
        document.getElementById('fieldClassroom').style.display = val === 'Presencial' ? 'flex' : 'none';
        document.getElementById('fieldLink').style.display      = val === 'En línea'   ? 'flex' : 'none';
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
        document.getElementById('schedule').value = g.schedule;
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

    function openInstructorModal(id) {
        const g = GROUPS_ROWS.find(r => r.id === id);
        if (!g) return;
        document.getElementById('instructorGroupName').textContent = g.subject + ' — ' + g.cycle;
        document.getElementById('assignInstructorForm').action = GROUPS_BASE_URL + '/' + id + '/assign-instructor';
        openModal('modalInstructor');
    }

    function openDeleteModal(id) {
        const g = GROUPS_ROWS.find(r => r.id === id);
        if (!g) return;
        document.getElementById('deleteGroupName').textContent = g.subject;
        document.getElementById('deleteForm').action = GROUPS_BASE_URL + '/' + id;
        openModal('modalDelete');
    }

    @if($errors->any())
    document.addEventListener('DOMContentLoaded', function () {
        @if($errors->has('instructor_id'))
            {{-- El error viene del POST de assignInstructor: mostramos un
                 banner global y NO reabrimos ningún modal automáticamente,
                 porque al volver no sabemos a qué grupo se intentó asignar. --}}
            window.scrollTo({ top: 0, behavior: 'smooth' });
        @else
            openModal('modalForm');
            toggleClassroom();
        @endif
    });
    @endif

    // ── Modal gestionar instructor ─────────────────────────
function openInstructor(subject, cycle, hasInstructor = false) {
    document.getElementById('instructorGroupName').textContent = subject + ' — ' + cycle;
    document.getElementById('btnUnassign').style.display = hasInstructor ? 'inline-flex' : 'none';

    // Reset búsqueda
    document.getElementById('instructorSearch').value = '';
    filterInstructors();

    // Reset tabs
    switchInstructorTab('instructor', document.querySelector('.tab-btn'));

    openModal('modalInstructor');
}

function switchInstructorTab(name, btn) {
    document.querySelectorAll('#modalInstructor .tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('#modalInstructor .tab-panel').forEach(p => p.style.display = 'none');
    btn.classList.add('active');
    document.getElementById('itab-' + name).style.display = 'block';
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

function toggleAssignClassroom() {
    const val = document.getElementById('assign-modality').value;
    document.getElementById('assignClassroomField').style.display = val === 'presencial' ? 'flex' : 'none';
    document.getElementById('assignLinkField').style.display      = val === 'linea'      ? 'flex' : 'none';
}

function confirmUnassign() {
    if (confirm('Desasignar al instructor de este grupo? Esta acción no se puede deshacer.')) {
        // Al integrar backend: hacer DELETE a la ruta de desasignación
        closeModal('modalInstructor');
    }
}

// Inicializar contadores al cargar
document.addEventListener('DOMContentLoaded', function() {
    const total = document.querySelectorAll('#instructorList .instructor-option').length;
    document.getElementById('visibleCount').textContent = total;
    document.getElementById('totalCount').textContent   = total;
});
</script>
@endpush