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
                            <span class="badge badge-students">
                                <i class="ti ti-users" style="font-size:11px" aria-hidden="true"></i>
                                {{ $group['students'] }}
                            </span>
                        </td>
                        <td>
                            <div class="dropdown">
                                <button class="dropdown-btn" onclick="toggleDropdown(this)">
                                    Acciones <i class="ti ti-chevron-down" style="font-size:12px" aria-hidden="true"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <button type="button" class="dropdown-item"
                                        onclick="openInstructorModal({{ $group['id'] }})">
                                        <i class="ti ti-user-check" style="color:var(--primary)" aria-hidden="true"></i>
                                        Asignar instructor
                                    </button>
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

{{-- MODAL ASIGNAR INSTRUCTOR --}}
{{-- Proximo a actualizar para poder actualizar la asignacion y desasignar al instructor del grupo --}}
<div class="modal-overlay" id="modalInstructor" role="dialog" aria-modal="true">
    <div class="modal" style="max-width:440px">
        <div class="modal-header">
            <div>
                <div class="modal-title">Asignar instructor</div>
                <div class="modal-subtitle" id="instructorGroupName"></div>
            </div>
            <button class="modal-close" onclick="closeModal('modalInstructor')" aria-label="Cerrar">
                <i class="ti ti-x" aria-hidden="true"></i>
            </button>
        </div>
        <form method="POST" id="assignInstructorForm" action="">
            @csrf
            <div class="modal-body">
                <div class="field" style="margin-bottom:14px">
                    <label class="field-label">Elegir instructor</label>
                    <p class="field-hint" style="margin-top:0">Lista desde la tabla <code>instructors</code> (usuarios con rol instructor).</p>
                </div>
                <div id="instructorList">
                    @forelse($instructors as $instructor)
                        @php
                            $u = $instructor->user;
                            $label = $u?->name ?? 'Sin usuario';
                            $initials = strtoupper(mb_substr($label, 0, 2));
                        @endphp
                        <label class="instructor-option" style="cursor:pointer;display:flex">
                            <input type="radio" name="instructor_id" value="{{ $instructor->id }}" class="inst-radio" style="margin-right:10px" @if($loop->first && $instructors->isNotEmpty()) required @endif>
                            <div class="inst-avatar">{{ $initials }}</div>
                            <div style="flex:1">
                                <div class="inst-name">{{ $label }}</div>
                                <div class="inst-career">{{ $instructor->major ?? '—' }}</div>
                            </div>
                        </label>
                    @empty
                        <p class="field-hint">No hay instructores en la base de datos. Crea uno desde administración.</p>
                    @endforelse
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('modalInstructor')">Cancelar</button>
                <button type="submit" class="btn btn-primary" @if($instructors->isEmpty()) disabled @endif>
                    <i class="ti ti-user-check" aria-hidden="true"></i> Confirmar asignación
                </button>
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
        openModal('modalForm');
        toggleClassroom();
    });
    @endif
</script>
@endpush