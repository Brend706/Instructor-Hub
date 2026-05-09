@extends('layouts.admin', ['title' => 'Coordinadores'])

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin/coordinators.css') }}">
@endpush

@section('content')

{{-- 
    Esta vista ya está conectada al backend:
    - El controller envía `$coordinators` (paginado) con `user` cargado.
    - El modal usa rutas resource:
      - POST   /admin/coordinadores  (crear)
      - PUT    /admin/coordinadores/{id} (editar)
      - DELETE /admin/coordinadores/{id} (eliminar)
    - En modo compatibilidad, la coordinación puede venir de:
      - `coordinators.coordination_name` (si existe la columna)
      - o `coordinators.name` (columna antigua)
--}}

{{-- ═══════════════════════════════════
     HEADER
═══════════════════════════════════ --}}
<div class="page-header">
    <div>
        <h1 class="page-title">Coordinadores</h1>
        <p class="page-sub">Gestión de coordinadores registrados en el sistema</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('modalForm')">
        <i class="ti ti-plus" aria-hidden="true"></i> Nuevo coordinador
    </button>
</div>

{{-- Mensaje de éxito --}}
@if(session('success'))
    <div class="alert-success" role="alert">
        <i class="ti ti-circle-check" aria-hidden="true"></i>
        {{ session('success') }}
    </div>
@endif

{{-- ═══════════════════════════════════
     TOOLBAR
═══════════════════════════════════ --}}
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
    <select class="filter-select" id="filterCoordination" onchange="filterTable()">
        <option value="">Todas las coordinaciones</option>
        @foreach(($coordinaciones ?? []) as $coord)
            <option value="{{ $coord }}">{{ $coord }}</option>
        @endforeach
    </select>
</div>

{{-- ═══════════════════════════════════
     TABLA
═══════════════════════════════════ --}}
<div class="table-card">
    <div class="table-wrap">
        <table id="coordinatorsTable">
            <thead>
                <tr>
                    <th>Coordinador</th>
                    <th>Correo</th>
                    <th>Coordinación</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($coordinators as $coordinator)
                    @php
                        $user = $coordinator->user;
                        $coordination = $coordinator->coordination_name ?? $coordinator->name ?? '';
                        $since = optional($coordinator->created_at)->format('M Y');
                    @endphp
                    <tr
                        data-id="{{ $coordinator->id }}"
                        data-name="{{ strtolower($user?->name ?? '') }}"
                        data-email="{{ strtolower($user?->email ?? '') }}"
                        data-coordination="{{ $coordination }}"
                        data-since="{{ $since }}"
                    >
                        <td>
                            <div style="display:flex;align-items:center;gap:10px">
                                <div class="avatar" style="background:var(--primary)">
                                    {{ strtoupper(substr($user?->name ?? 'CO', 0, 2)) }}
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
                        <td>{{ $coordination }}</td>
                        <td>
                            <div class="actions">
                                {{-- Ver detalle --}}
                                <button
                                    type="button"
                                    class="btn btn-ghost btn-sm"
                                    title="Ver detalle"
                                    onclick="openView({{ $coordinator->id }})"
                                >
                                    <i class="ti ti-eye" aria-hidden="true"></i>
                                </button>
                                {{-- Editar --}}
                                <button
                                    class="btn btn-ghost btn-sm"
                                    title="Editar"
                                    onclick="openEdit({{ $coordinator->id }})"
                                >
                                    <i class="ti ti-pencil" aria-hidden="true"></i>
                                </button>
                                {{-- Eliminar --}}
                                <button
                                    class="btn btn-danger btn-sm"
                                    title="Eliminar"
                                    onclick="openDelete({{ $coordinator->id }})"
                                >
                                    <i class="ti ti-trash" aria-hidden="true"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="empty-state">
                            <i class="ti ti-users-off" aria-hidden="true"></i>
                            <p>No hay coordinadores registrados aún</p>
                            <button class="btn btn-primary" onclick="openModal('modalForm')" style="margin-top:10px">
                                <i class="ti ti-plus"></i> Agregar el primero
                            </button>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ═══════════════════════════════════
     MODAL VER DETALLE
═══════════════════════════════════ --}}
<div class="modal-overlay" id="modalView" role="dialog" aria-modal="true" aria-labelledby="viewTitle">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title" id="viewTitle">Detalle del coordinador</div>
            <button class="modal-close" onclick="closeModal('modalView')" aria-label="Cerrar">
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
                <label class="field-label">Coordinación</label>
                <div class="input" id="viewCoordination" style="display:flex;align-items:center"></div>
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

{{-- ═══════════════════════════════════
     MODAL CREAR / EDITAR
═══════════════════════════════════ --}}
<div class="modal-overlay" id="modalForm" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title" id="modalTitle">Nuevo coordinador</div>
            <button class="modal-close" onclick="closeModal('modalForm')" aria-label="Cerrar">
                <i class="ti ti-x" aria-hidden="true"></i>
            </button>
        </div>

        <form method="POST" id="coordinatorForm" action="{{ route('admin.coordinadores.store') }}">
            @csrf
            <input type="hidden" name="_method" id="formMethod" value="POST">
            <input type="hidden" id="coordinatorId" value="">

            <div class="modal-body">

                <div class="field">
                    <label class="field-label" for="name">Nombre completo</label>
                    <input
                        class="input"
                        id="name"
                        name="name"
                        type="text"
                        placeholder="Ej. María López"
                        required
                    >
                </div>

                <div class="field">
                    <label class="field-label" for="email">Correo electrónico</label>
                    <input
                        class="input"
                        id="email"
                        name="email"
                        type="email"
                        placeholder="correo@fica.edu.sv"
                        required
                    >
                </div>

                <div class="field">
                    <label class="field-label" for="coordination_name">Coordinación</label>
                    <select class="input" id="coordination_name" name="coordination_name" required>
                        <option value="">Seleccionar...</option>
                        @foreach(($coordinaciones ?? []) as $coord)
                            <option value="{{ $coord }}">{{ $coord }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field" id="passwordField">
                    <label class="field-label" for="password">
                        Contraseña temporal
                        <span id="passwordHint" style="font-weight:400;color:var(--text-muted)"></span>
                    </label>
                    <input
                        class="input"
                        id="password"
                        name="password"
                        type="password"
                        placeholder="Mínimo 8 caracteres"
                    >
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

{{-- ═══════════════════════════════════
     MODAL CONFIRMAR ELIMINAR
═══════════════════════════════════ --}}
<div class="modal-overlay" id="modalDelete" role="dialog" aria-modal="true">
    <div class="modal modal-sm">
        <div class="modal-body" style="padding:28px 24px;text-align:center">
            <div class="confirm-icon">
                <i class="ti ti-trash" aria-hidden="true"></i>
            </div>
            <div class="confirm-title">¿Eliminar coordinador?</div>
            <p class="confirm-desc">
                Esta acción no se puede deshacer. Se desasignarán todos los instructores
                vinculados a <strong id="deleteName"></strong>.
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
    const BASE_URL = @json(url('/admin/coordinadores'));

    // La UI abre un mismo modal para crear/editar.
    // - Crear: action = route(admin.coordinadores.store) y method = POST
    // - Editar: action = `${BASE_URL}/{id}` y method spoofed = PUT

    // ── Abrir / cerrar ─────────────────────────────────────
    function openModal(id) {
        document.getElementById(id).classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function closeModal(id) {
        document.getElementById(id).classList.remove('open');
        document.body.style.overflow = '';
        if (id === 'modalForm') resetForm();
    }

    // Cerrar al hacer clic fuera
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', e => {
            if (e.target === overlay) closeModal(overlay.id);
        });
    });

    // ── Ver detalle (solo lectura) ─────────────────────────
    function openView(id) {
        const row = document.querySelector(`#coordinatorsTable tbody tr[data-id="${id}"]`);
        if (!row) return;

        const name = row.querySelector('.td-main')?.textContent?.trim() || '—';
        const email = row.dataset.email || '—';
        const coordination = row.dataset.coordination || '—';
        const since = row.dataset.since || '—';

        document.getElementById('viewName').textContent = name;
        document.getElementById('viewEmail').textContent = email;
        document.getElementById('viewCoordination').textContent = coordination;
        document.getElementById('viewSince').textContent = since;

        openModal('modalView');
    }

    // ── Reset formulario ───────────────────────────────────
    function resetForm() {
        document.getElementById('coordinatorForm').reset();
        document.getElementById('formMethod').value = 'POST';
        document.getElementById('coordinatorId').value = '';
        document.getElementById('coordinatorForm').action = @json(route('admin.coordinadores.store'));
        document.getElementById('modalTitle').textContent = 'Nuevo coordinador';
        document.getElementById('btnText').textContent = 'Guardar';
        document.getElementById('password').required = true;
        document.getElementById('passwordHint').textContent = '';
    }

    // ── Abrir editar ───────────────────────────────────────
    function openEdit(id) {
        const row = document.querySelector(`#coordinatorsTable tbody tr[data-id="${id}"]`);
        if (!row) return;

        document.getElementById('modalTitle').textContent = 'Editar coordinador';
        document.getElementById('btnText').textContent = 'Actualizar';
        document.getElementById('formMethod').value = 'PUT';
        document.getElementById('coordinatorId').value = String(id);
        document.getElementById('coordinatorForm').action = `${BASE_URL}/${id}`;

        document.getElementById('name').value = row.dataset.name || '';
        document.getElementById('email').value = row.dataset.email || '';
        document.getElementById('coordination_name').value = row.dataset.coordination || '';

        // Contraseña opcional al editar
        document.getElementById('password').required = false;
        document.getElementById('passwordHint').textContent = '(dejar vacío para no cambiar)';

        openModal('modalForm');
    }

    // ── Abrir eliminar ─────────────────────────────────────
    function openDelete(id) {
        const row = document.querySelector(`#coordinatorsTable tbody tr[data-id="${id}"]`);
        const name = row?.querySelector('.td-main')?.textContent?.trim() || 'este coordinador';

        document.getElementById('deleteName').textContent = name;
        document.getElementById('deleteForm').action = `${BASE_URL}/${id}`;
        openModal('modalDelete');
    }

    // ── Filtro en tiempo real ──────────────────────────────
    function filterTable() {
        const search = document.getElementById('searchInput').value.toLowerCase();
        const coord  = document.getElementById('filterCoordination').value;
        const rows   = document.querySelectorAll('#coordinatorsTable tbody tr[data-name]');

        rows.forEach(row => {
            const matchSearch = row.dataset.name.includes(search) || row.dataset.email.includes(search);
            const matchCoord  = !coord || row.dataset.coordination === coord;
            row.style.display = matchSearch && matchCoord ? '' : 'none';
        });
    }
</script>
@endpush