@extends('layouts.admin', ['title' => 'Instructores'])

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin/coordinators.css') }}">
@endpush

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">Instructores</h1>
        <p class="page-sub">Gestión de instructores registrados en el sistema</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="openNewInstructorModal()">
        <i class="ti ti-plus" aria-hidden="true"></i> Nuevo instructor
    </button>
</div>

@if(session('success'))
    <div class="alert-success" role="alert">
        <i class="ti ti-circle-check" aria-hidden="true"></i>
        {{ session('success') }}
    </div>
@endif

<div class="toolbar">
    <div class="search-wrap">
        <i class="ti ti-search" aria-hidden="true"></i>
        <input
            class="search-input"
            type="search"
            placeholder="Buscar por nombre, correo..."
            id="searchInput"
            oninput="filterTable()"
        >
    </div>
    <select class="filter-select" id="filterMajor" onchange="filterTable()">
        <option value="">Todas las coordinaciones</option>
        @foreach(($carreras ?? []) as $c)
            <option value="{{ $c }}">{{ $c }}</option>
        @endforeach
    </select>
</div>

<div class="table-card">
    <div class="table-wrap">
        <table id="instructorsTable">
            <thead>
                <tr>
                    <th>Instructor</th>
                    <th>Correo</th>
                    <th>Carrera</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($instructors as $instructor)
                    @php
                        $user = $instructor->user;
                        $since = optional($instructor->created_at)->format('M Y');
                        $statusLabel = ($hasStatusColumn ?? false) ? ($instructor->status ?? 'Activo') : 'Activo';
                        $isActive = $statusLabel === 'Activo';
                    @endphp
                    <tr
                        data-id="{{ $instructor->id }}"
                        data-name="{{ strtolower($user?->name ?? '') }}"
                        data-email="{{ strtolower($user?->email ?? '') }}"
                        data-user-name="{{ $user?->name ?? '' }}"
                        data-user-email="{{ $user?->email ?? '' }}"
                        data-major="{{ $instructor->major }}"
                        data-status="{{ $statusLabel }}"
                        data-since="{{ $since }}"
                    >
                        <td>
                            <div style="display:flex;align-items:center;gap:10px">
                                <div class="avatar" style="background:var(--primary)">
                                    {{ strtoupper(substr($user?->name ?? 'IN', 0, 2)) }}
                                </div>
                                <div>
                                    <div class="td-main">{{ $user?->name ?? '—' }}</div>
                                    <div style="font-size:11px;color:var(--text-muted)">
                                        Desde {{ $since }}
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>{{ $user?->email ?? '—' }}</td>
                        <td>{{ $instructor->major }}</td>
                        <td>
                            <span class="badge {{ $isActive ? 'badge-success' : 'badge-warning' }}"
                                style="display:inline-flex;align-items:center;gap:5px">
                                <span style="width:6px;height:6px;border-radius:50%;
                                            background:{{ $isActive ? '#166534' : '#854D0E' }};
                                            flex-shrink:0"></span>
                                {{ $statusLabel }}
                            </span>
                        </td>
                        <td>
                            <div class="actions">
                                <button
                                    type="button"
                                    class="btn btn-ghost btn-sm"
                                    title="Ver detalle"
                                    onclick="openView({{ $instructor->id }})"
                                >
                                    <i class="ti ti-eye" aria-hidden="true"></i>
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-ghost btn-sm"
                                    title="Editar"
                                    onclick="openEdit({{ $instructor->id }})"
                                >
                                    <i class="ti ti-pencil" aria-hidden="true"></i>
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-danger btn-sm"
                                    title="Eliminar"
                                    onclick="openDelete({{ $instructor->id }})"
                                >
                                    <i class="ti ti-trash" aria-hidden="true"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="empty-state">
                            <i class="ti ti-users-off" aria-hidden="true"></i>
                            <p>No hay instructores registrados aún</p>
                            <button type="button" class="btn btn-primary" onclick="openNewInstructorModal()" style="margin-top:10px">
                                <i class="ti ti-plus"></i> Agregar el primero
                            </button>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($instructors->hasPages())
    <div style="margin-top:16px">
        {{ $instructors->links() }}
    </div>
@endif

{{-- MODAL VER DETALLE --}}
<div class="modal-overlay" id="modalView" role="dialog" aria-modal="true" aria-labelledby="viewTitle">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title" id="viewTitle">Detalle del instructor</div>
            <button type="button" class="modal-close" onclick="closeModal('modalView')" aria-label="Cerrar">
                <i class="ti ti-x" aria-hidden="true"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="field">
                <label class="field-label">Nombre completo</label>
                <div class="input" id="viewName" style="display:flex;align-items:center"></div>
            </div>
            <div class="field">
                <label class="field-label">Correo electrónico</label>
                <div class="input" id="viewEmail" style="display:flex;align-items:center"></div>
            </div>
            <div class="field">
                <label class="field-label">Carrera</label>
                <div class="input" id="viewMajor" style="display:flex;align-items:center"></div>
            </div>
            <div class="field" id="viewStatusWrap">
                <label class="field-label">Estado</label>
                <div class="input" id="viewStatus" style="display:flex;align-items:center"></div>
            </div>
            <div class="field">
                <label class="field-label">Registrado</label>
                <div class="input" id="viewSince" style="display:flex;align-items:center"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="closeModal('modalView')">Cerrar</button>
        </div>
    </div>
</div>

{{-- MODAL CREAR / EDITAR --}}
<div class="modal-overlay" id="modalForm" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title" id="modalTitle">Nuevo instructor</div>
            <button type="button" class="modal-close" onclick="closeModal('modalForm')" aria-label="Cerrar">
                <i class="ti ti-x" aria-hidden="true"></i>
            </button>
        </div>

        <form method="POST" id="instructorForm" action="{{ route('admin.instructores.store') }}" novalidate>
            @csrf
            <input type="hidden" name="_method" id="formMethod" value="POST">
            <input type="hidden" id="instructorId" value="">

            <div class="modal-body">

                @if ($errors->any())
                    <div class="alert-success" style="border-color:#fecaca;background:#fef2f2;color:#991b1b" role="alert">
                        <i class="ti ti-alert-circle" aria-hidden="true"></i>
                        Corrige los campos marcados antes de continuar.
                    </div>
                @endif

                <div class="field">
                    <label class="field-label" for="name">Nombre completo</label>
                    <input
                        class="input @error('name') is-invalid @enderror"
                        id="name"
                        name="name"
                        type="text"
                        placeholder="Ej. Ana Mejía"
                        value="{{ old('name') }}"
                        autocomplete="name"
                    >
                    <span class="field-msg field-msg--error" id="nameClientError" aria-live="polite"></span>
                    @error('name')
                        <span class="field-msg field-msg--error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="field">
                    <label class="field-label" for="email">Correo electrónico</label>
                    <input
                        class="input @error('email') is-invalid @enderror"
                        id="email"
                        name="email"
                        type="email"
                        placeholder="correo@fica.edu.sv"
                        value="{{ old('email') }}"
                        autocomplete="email"
                    >
                    <span class="field-msg field-msg--error" id="emailClientError" aria-live="polite"></span>
                    @error('email')
                        <span class="field-msg field-msg--error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="field">
                    <label class="field-label" for="major">Carrera</label>
                    <select class="input @error('major') is-invalid @enderror" id="major" name="major">
                        <option value="">Seleccionar...</option>
                        @foreach(($carreras ?? []) as $c)
                            <option value="{{ $c }}" @selected(old('major') === $c)>{{ $c }}</option>
                        @endforeach
                    </select>
                    <span class="field-msg field-msg--error" id="majorClientError" aria-live="polite"></span>
                    @error('major')
                        <span class="field-msg field-msg--error">{{ $message }}</span>
                    @enderror
                </div>

                @if($hasStatusColumn ?? false)
                    <div class="field">
                        <label class="field-label" for="status">Estado</label>
                        <select class="input @error('status') is-invalid @enderror" id="status" name="status">
                            <option value="Activo" @selected(old('status', 'Activo') === 'Activo')>Activo</option>
                            <option value="Inactivo" @selected(old('status') === 'Inactivo')>Inactivo</option>
                        </select>
                        <span class="field-msg field-msg--error" id="statusClientError" aria-live="polite"></span>
                        @error('status')
                            <span class="field-msg field-msg--error">{{ $message }}</span>
                        @enderror
                    </div>
                @endif

                <div class="field" id="passwordField">
                    <label class="field-label" for="password">
                        Contraseña temporal
                        <span id="passwordHint" style="font-weight:400;color:var(--text-muted)"></span>
                    </label>
                    <input
                        class="input @error('password') is-invalid @enderror"
                        id="password"
                        name="password"
                        type="password"
                        placeholder="Mínimo 8 caracteres"
                        autocomplete="new-password"
                        minlength="8"
                        maxlength="255"
                    >
                    <label class="show-password-toggle" for="showInstructorPassword">
                        <input type="checkbox" id="showInstructorPassword" class="show-password-checkbox">
                        Ver contraseña
                    </label>
                    <span class="field-msg field-msg--error" id="passwordClientError" aria-live="polite"></span>
                    @error('password')
                        <span class="field-msg field-msg--error">{{ $message }}</span>
                    @enderror
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

{{-- MODAL ELIMINAR --}}
<div class="modal-overlay" id="modalDelete" role="dialog" aria-modal="true">
    <div class="modal modal-sm">
        <div class="modal-body" style="padding:28px 24px;text-align:center">
            <div class="confirm-icon">
                <i class="ti ti-trash" aria-hidden="true"></i>
            </div>
            <div class="confirm-title">¿Eliminar instructor?</div>
            <p class="confirm-desc">
                Esta acción no se puede deshacer. Se eliminará a <strong id="deleteName"></strong> del sistema.
            </p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="closeModal('modalDelete')">Cancelar</button>
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
    const BASE_URL = @json(url('/admin/instructores'));
    const HAS_STATUS = @json($hasStatusColumn ?? false);

    function clearInstructorClientErrors() {
        ['name', 'email', 'major', 'status', 'password'].forEach((key) => {
            const id = key + 'ClientError';
            const el = document.getElementById(id);
            if (el) el.textContent = '';
        });
        document.querySelectorAll('#instructorForm .input.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
    }

    function setInstructorClientError(fieldId, message) {
        const input = document.getElementById(fieldId);
        let errId = fieldId + 'ClientError';
        if (fieldId === 'major') errId = 'majorClientError';
        const err = document.getElementById(errId);
        if (input) input.classList.add('is-invalid');
        if (err) err.textContent = message;
    }

    function openNewInstructorModal() {
        resetForm();
        openModal('modalForm');
    }

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

    function openView(id) {
        const row = document.querySelector(`#instructorsTable tbody tr[data-id="${id}"]`);
        if (!row) return;

        document.getElementById('viewName').textContent = row.querySelector('.td-main')?.textContent?.trim() || '—';
        document.getElementById('viewEmail').textContent = row.dataset.userEmail || '—';
        document.getElementById('viewMajor').textContent = row.dataset.major || '—';
        document.getElementById('viewSince').textContent = row.dataset.since || '—';
        const wrap = document.getElementById('viewStatusWrap');
        const st = document.getElementById('viewStatus');
        if (HAS_STATUS && wrap && st) {
            wrap.style.display = '';
            st.textContent = row.dataset.status || '—';
        } else if (wrap) {
            wrap.style.display = 'none';
        }

        openModal('modalView');
    }

    function resetForm() {
        document.getElementById('instructorForm').reset();
        clearInstructorClientErrors();
        document.getElementById('formMethod').value = 'POST';
        document.getElementById('instructorId').value = '';
        document.getElementById('instructorForm').action = @json(route('admin.instructores.store'));
        document.getElementById('modalTitle').textContent = 'Nuevo instructor';
        document.getElementById('btnText').textContent = 'Guardar';
        const pwd = document.getElementById('password');
        pwd.required = true;
        pwd.minLength = 8;
        pwd.type = 'password';
        const showPwd = document.getElementById('showInstructorPassword');
        if (showPwd) showPwd.checked = false;
        document.getElementById('passwordHint').textContent = '';
        const statusEl = document.getElementById('status');
        if (statusEl) statusEl.value = 'Activo';
    }

    function openEdit(id) {
        const row = document.querySelector(`#instructorsTable tbody tr[data-id="${id}"]`);
        if (!row) return;

        clearInstructorClientErrors();

        document.getElementById('modalTitle').textContent = 'Editar instructor';
        document.getElementById('btnText').textContent = 'Actualizar';
        document.getElementById('formMethod').value = 'PUT';
        document.getElementById('instructorId').value = String(id);
        document.getElementById('instructorForm').action = `${BASE_URL}/${id}`;

        document.getElementById('name').value = row.dataset.userName || '';
        document.getElementById('email').value = row.dataset.userEmail || '';
        document.getElementById('major').value = row.dataset.major || '';

        const statusEl = document.getElementById('status');
        if (statusEl && HAS_STATUS) {
            statusEl.value = row.dataset.status === 'Inactivo' ? 'Inactivo' : 'Activo';
        }

        const pwd = document.getElementById('password');
        pwd.required = false;
        pwd.value = '';
        pwd.minLength = 8;
        pwd.type = 'password';
        const showPwd = document.getElementById('showInstructorPassword');
        if (showPwd) showPwd.checked = false;
        document.getElementById('passwordHint').textContent = '(dejar vacío para no cambiar)';

        openModal('modalForm');
    }

    function openDelete(id) {
        const form = document.getElementById('deleteForm');
        if (form) form.action = `${BASE_URL}/${String(id)}`;

        const row = document.querySelector(`#instructorsTable tbody tr[data-id="${id}"]`);
        const name = row?.querySelector('.td-main')?.textContent?.trim() || 'este instructor';
        const deleteNameEl = document.getElementById('deleteName');
        if (deleteNameEl) deleteNameEl.textContent = name;

        openModal('modalDelete');
    }

    function filterTable() {
        const search = document.getElementById('searchInput').value.toLowerCase();
        const major = document.getElementById('filterMajor').value;
        const rows = document.querySelectorAll('#instructorsTable tbody tr[data-name]');

        rows.forEach(row => {
            const matchSearch = row.dataset.name.includes(search) || row.dataset.email.includes(search);
            const matchMajor = !major || row.dataset.major === major;
            row.style.display = matchSearch && matchMajor ? '' : 'none';
        });
    }

    document.getElementById('instructorForm')?.addEventListener('submit', function (e) {
        clearInstructorClientErrors();

        const method = document.getElementById('formMethod').value;
        const isCreate = method === 'POST';

        const name = document.getElementById('name').value.trim();
        const email = document.getElementById('email').value.trim();
        const major = document.getElementById('major').value;
        const password = document.getElementById('password').value;

        let ok = true;

        if (!name) {
            setInstructorClientError('name', 'Debe ingresar el nombre completo.');
            ok = false;
        }
        if (!email) {
            setInstructorClientError('email', 'Debe ingresar el correo electrónico.');
            ok = false;
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            setInstructorClientError('email', 'El correo electrónico no es válido.');
            ok = false;
        }
        if (!major) {
            setInstructorClientError('major', 'Debe seleccionar la carrera.');
            ok = false;
        }

        const statusEl = document.getElementById('status');
        if (HAS_STATUS && statusEl && !statusEl.value) {
            setInstructorClientError('status', 'Debe seleccionar el estado.');
            ok = false;
        }

        if (isCreate) {
            if (!password) {
                setInstructorClientError('password', 'Debe ingresar una contraseña.');
                ok = false;
            } else if (password.length < 8) {
                setInstructorClientError('password', 'La contraseña debe tener al menos 8 caracteres.');
                ok = false;
            }
        } else if (password && password.length < 8) {
            setInstructorClientError('password', 'La contraseña debe tener al menos 8 caracteres.');
            ok = false;
        }

        if (!ok) e.preventDefault();
    });

    ['name', 'email', 'major', 'password'].forEach((id) => {
        document.getElementById(id)?.addEventListener('input', () => {
            const input = document.getElementById(id);
            const err = document.getElementById(id + 'ClientError');
            if (input) input.classList.remove('is-invalid');
            if (err) err.textContent = '';
        });
    });
    document.getElementById('status')?.addEventListener('change', () => {
        const input = document.getElementById('status');
        const err = document.getElementById('statusClientError');
        if (input) input.classList.remove('is-invalid');
        if (err) err.textContent = '';
    });

    document.getElementById('showInstructorPassword')?.addEventListener('change', function () {
        const input = document.getElementById('password');
        if (input) input.type = this.checked ? 'text' : 'password';
    });

    @if ($errors->any())
    document.addEventListener('DOMContentLoaded', function () {
        openModal('modalForm');
    });
    @endif
</script>
@endpush
