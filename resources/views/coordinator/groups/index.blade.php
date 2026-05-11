@extends('layouts.coordinator', ['title' => 'Grupos de clase'])

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/coordinator/groups.css') }}">
@endpush

@php
    // ── Datos ficticios — reemplazar con $groups del controller
    $groups = [
        ['id' => 1, 'subject' => 'Programación I',  'teacher' => 'Ing. R. Chávez',  'cycle' => '01-2026', 'schedule' => 'Lun y Mié 7-9am',  'modality' => 'Presencial', 'classroom' => 'Aula 204',            'instructor' => 'Ana Mejía',   'students' => 28],
        ['id' => 2, 'subject' => 'Cálculo I',        'teacher' => 'Lic. M. Fuentes', 'cycle' => '01-2026', 'schedule' => 'Mar y Jue 9-11am', 'modality' => 'En línea',   'classroom' => 'meet.google.com/xyz', 'instructor' => null,          'students' => 35],
        ['id' => 3, 'subject' => 'Física I',         'teacher' => 'Dr. L. Gómez',    'cycle' => '02-2025', 'schedule' => 'Lun y Mié 11am-1pm','modality' => 'Presencial', 'classroom' => 'Aula 101',            'instructor' => 'Carlos Rivas', 'students' => 22],
        ['id' => 4, 'subject' => 'Química General',  'teacher' => 'Dra. S. López',   'cycle' => '02-2025', 'schedule' => 'Mar y Jue 2-4pm',  'modality' => 'En línea',   'classroom' => 'meet.google.com/abc', 'instructor' => null,          'students' => 30],
    ];
    $cycles = ['01-2026', '02-2026', '01-2025', '02-2025'];
@endphp

@section('content')

{{-- HEADER --}}
<div class="page-header">
    <div>
        <h1 class="page-title">Grupos de clase</h1>
        <p class="page-sub">Gestión de grupos, instructores y estudiantes</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('modalForm')">
        <i class="ti ti-plus" aria-hidden="true"></i> Nuevo grupo
    </button>
</div>

@if(session('success'))
    <div class="alert-success" role="alert">
        <i class="ti ti-circle-check" aria-hidden="true"></i>
        {{ session('success') }}
    </div>
@endif

{{-- TOOLBAR --}}
<div class="toolbar">
    <div class="search-wrap">
        <i class="ti ti-search" aria-hidden="true"></i>
        <input class="search-input" type="search" placeholder="Buscar por materia, docente..." id="searchInput" oninput="filterTable()">
    </div>
    <select class="filter-select" id="filterCycle" onchange="filterTable()">
        <option value="">Todos los ciclos</option>
        @foreach($cycles as $cycle)
            <option value="{{ $cycle }}">{{ $cycle }}</option>
        @endforeach
    </select>
    <select class="filter-select" id="filterModality" onchange="filterTable()">
        <option value="">Todas las modalidades</option>
        <option value="Presencial">Presencial</option>
        <option value="En línea">En línea</option>
    </select>
</div>

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
                                    <button class="dropdown-item"
                                        onclick="openInstructor('{{ $group['subject'] }}', '{{ $group['cycle'] }}')">
                                        <i class="ti ti-user-check" style="color:var(--primary)" aria-hidden="true"></i>
                                        Asignar instructor
                                    </button>
                                    <a class="dropdown-item" href="#">
                                        {{-- Al integrar backend: href="{{ route('admin.groups.students', $group['id']) }}" --}}
                                        <i class="ti ti-users-plus" style="color:var(--accent)" aria-hidden="true"></i>
                                        Agregar estudiantes
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <button class="dropdown-item"
                                        onclick="openEdit({{ $group['id'] }}, '{{ $group['subject'] }}', '{{ $group['teacher'] }}', '{{ $group['cycle'] }}', '{{ $group['modality'] }}', '{{ $group['schedule'] }}', '{{ $group['classroom'] }}')">
                                        <i class="ti ti-pencil" aria-hidden="true"></i>
                                        Editar grupo
                                    </button>
                                    <button class="dropdown-item danger"
                                        onclick="openDelete({{ $group['id'] }}, '{{ $group['subject'] }}')">
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
                            <button class="btn btn-primary" onclick="openModal('modalForm')" style="margin-top:10px">
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
        <form method="POST" id="groupForm" action="#">
            @csrf
            <input type="hidden" name="_method" id="formMethod" value="POST">
            <div class="modal-body">

                <div class="field">
                    <label class="field-label" for="subject">Materia</label>
                    <input class="input" id="subject" name="subject" type="text" placeholder="Ej. Programación I" required>
                </div>

                <div class="field">
                    <label class="field-label" for="teacher">Docente</label>
                    <input class="input" id="teacher" name="teacher" type="text" placeholder="Ej. Ing. Roberto Chávez" required>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label class="field-label" for="cycle">Ciclo</label>
                        <input class="input" id="cycle" name="cycle" type="text" placeholder="Ej. 01-2026" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="modality">Modalidad</label>
                        <select class="input" id="modality" name="modality" onchange="toggleClassroom()" required>
                            <option value="">Seleccionar...</option>
                            <option value="Presencial">Presencial</option>
                            <option value="En línea">En línea</option>
                        </select>
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="schedule">Horario</label>
                    <input class="input" id="schedule" name="schedule" type="text" placeholder="Ej. Lunes y Mié 7:00 - 9:00am" required>
                </div>

                <div class="field" id="fieldClassroom" style="display:none">
                    <label class="field-label" for="classroom" id="classroomLabel">Aula física</label>
                    <input class="input" id="classroom" name="classroom" type="text" placeholder="Ej. Aula 204 — Edificio A">
                </div>

                <div class="field" id="fieldLink" style="display:none">
                    <label class="field-label" for="link">Enlace virtual</label>
                    <input class="input" id="link" name="link" type="url" placeholder="Ej. https://meet.google.com/abc-xyz">
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
        <div class="modal-body">
            <div class="field" style="margin-bottom:14px">
                <label class="field-label">Buscar instructor</label>
                <input class="input" type="search" placeholder="Nombre del instructor...">
            </div>
            {{-- Al integrar backend: lista dinámica de instructores --}}
            <div id="instructorList">
                <div class="instructor-option selected" onclick="selectInstructor(this)">
                    <div class="inst-avatar">AM</div>
                    <div><div class="inst-name">Ana Mejía</div><div class="inst-career">Ing. Sistemas</div></div>
                    <div class="inst-check"><i class="ti ti-check" style="font-size:10px"></i></div>
                </div>
                <div class="instructor-option" onclick="selectInstructor(this)">
                    <div class="inst-avatar" style="background:var(--primary)">CR</div>
                    <div><div class="inst-name">Carlos Rivas</div><div class="inst-career">Arquitectura</div></div>
                    <div class="inst-check"><i class="ti ti-check" style="font-size:10px"></i></div>
                </div>
                <div class="instructor-option" onclick="selectInstructor(this)">
                    <div class="inst-avatar" style="background:var(--primary-400)">MG</div>
                    <div><div class="inst-name">Miguel García</div><div class="inst-career">Ing. Civil</div></div>
                    <div class="inst-check"><i class="ti ti-check" style="font-size:10px"></i></div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal('modalInstructor')">Cancelar</button>
            <button class="btn btn-primary">
                <i class="ti ti-user-check" aria-hidden="true"></i> Confirmar asignación
            </button>
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
            <form method="POST" id="deleteForm" action="#">
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
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', e => {
            if (e.target === overlay) closeModal(overlay.id);
        });
    });

    // ── Reset formulario ───────────────────────────────────
    function resetForm() {
        document.getElementById('groupForm').reset();
        document.getElementById('formMethod').value = 'POST';
        document.getElementById('modalTitle').textContent = 'Nuevo grupo';
        document.getElementById('btnText').textContent = 'Guardar';
        document.getElementById('fieldClassroom').style.display = 'none';
        document.getElementById('fieldLink').style.display = 'none';
    }

    // ── Toggle aula según modalidad ────────────────────────
    function toggleClassroom() {
        const val = document.getElementById('modality').value;
        document.getElementById('fieldClassroom').style.display = val === 'Presencial' ? 'flex' : 'none';
        document.getElementById('fieldLink').style.display      = val === 'En línea'   ? 'flex' : 'none';
    }

    // ── Abrir editar ───────────────────────────────────────
    function openEdit(id, subject, teacher, cycle, modality, schedule, classroom) {
        document.getElementById('modalTitle').textContent = 'Editar grupo';
        document.getElementById('btnText').textContent   = 'Actualizar';
        document.getElementById('formMethod').value      = 'PUT';
        document.getElementById('subject').value   = subject;
        document.getElementById('teacher').value   = teacher;
        document.getElementById('cycle').value     = cycle;
        document.getElementById('modality').value  = modality;
        document.getElementById('schedule').value  = schedule;
        document.getElementById('classroom').value = classroom;
        toggleClassroom();
        openModal('modalForm');
    }

    // ── Abrir asignar instructor ───────────────────────────
    function openInstructor(subject, cycle) {
        document.getElementById('instructorGroupName').textContent = subject + ' — ' + cycle;
        openModal('modalInstructor');
    }

    // ── Seleccionar instructor ─────────────────────────────
    function selectInstructor(el) {
        document.querySelectorAll('.instructor-option').forEach(o => o.classList.remove('selected'));
        el.classList.add('selected');
    }

    // ── Abrir eliminar ─────────────────────────────────────
    function openDelete(id, subject) {
        document.getElementById('deleteGroupName').textContent = subject;
        openModal('modalDelete');
    }

    // ── Filtro en tiempo real ──────────────────────────────
    function filterTable() {
        const search   = document.getElementById('searchInput').value.toLowerCase();
        const cycle    = document.getElementById('filterCycle').value;
        const modality = document.getElementById('filterModality').value;
        const rows     = document.querySelectorAll('#groupsTable tbody tr[data-subject]');

        rows.forEach(row => {
            const matchSearch   = row.dataset.subject.includes(search) || row.dataset.teacher.includes(search);
            const matchCycle    = !cycle    || row.dataset.cycle    === cycle;
            const matchModality = !modality || row.dataset.modality === modality;
            row.style.display   = matchSearch && matchCycle && matchModality ? '' : 'none';
        });
    }
</script>
@endpush