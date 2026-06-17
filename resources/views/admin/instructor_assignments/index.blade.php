@extends('layouts.admin', ['title' => 'Instructorías'])

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin/instructor-assignments.css') }}">
@endpush

@section('content')

@php
    $total    = $assignments instanceof \Illuminate\Pagination\LengthAwarePaginator
        ? $assignments->total()
        : $assignments->count();
    $active   = $assignments->filter(fn($a) => strtolower($a->status ?? '') === 'activo')->count();
    $finished = $assignments->filter(fn($a) => strtolower($a->status ?? '') === 'finalizado')->count();
    $groups   = $assignments->pluck('class_group_id')->unique()->count();
@endphp

{{-- ── Encabezado ─────────────────────────────────────────── --}}
<div class="ia-header">
    <div class="ia-header-left">
        <h1>Instructorías</h1>
        <p>Asignaciones de instructoría registradas en el sistema</p>
    </div>
    <div>
        @if(isset($showAll) && $showAll)
            <a href="{{ route('admin.instructorias.index', array_filter(['semester' => $semester])) }}"
               class="ia-btn ia-btn-ghost">
                <i class="ti ti-list" aria-hidden="true"></i> Ver paginado
            </a>
        @else
            <a href="{{ route('admin.instructorias.index', array_filter(['all' => 1, 'semester' => $semester])) }}"
               class="ia-btn ia-btn-ghost">
                <i class="ti ti-list" aria-hidden="true"></i> Ver todas
            </a>
        @endif
    </div>
</div>

{{-- ── Stat cards ──────────────────────────────────────────── --}}
<div class="ia-stats">
    <div class="ia-stat-card">
        <div class="ia-stat-icon primary"><i class="ti ti-clipboard-list"></i></div>
        <div>
            <div class="ia-stat-val">{{ $total }}</div>
            <div class="ia-stat-label">Total asignaciones</div>
        </div>
    </div>
    <div class="ia-stat-card">
        <div class="ia-stat-icon success"><i class="ti ti-circle-check"></i></div>
        <div>
            <div class="ia-stat-val">{{ $active }}</div>
            <div class="ia-stat-label">Activas</div>
        </div>
    </div>
    <div class="ia-stat-card">
        <div class="ia-stat-icon accent"><i class="ti ti-check"></i></div>
        <div>
            <div class="ia-stat-val">{{ $finished }}</div>
            <div class="ia-stat-label">Finalizadas</div>
        </div>
    </div>
    <div class="ia-stat-card">
        <div class="ia-stat-icon warn"><i class="ti ti-users"></i></div>
        <div>
            <div class="ia-stat-val">{{ $groups }}</div>
            <div class="ia-stat-label">Grupos distintos</div>
        </div>
    </div>
</div>

{{-- ── Toolbar ─────────────────────────────────────────────── --}}
<div class="ia-toolbar">
    {{-- Búsqueda client-side --}}
    <div class="ia-search-wrap">
        <i class="ti ti-search"></i>
        <input
            id="iaSearch"
            type="search"
            class="ia-search"
            placeholder="Buscar instructor o grupo…"
            oninput="iaFilterTable()"
        >
    </div>

    {{-- Filtro de ciclo --}}
    <form method="GET" action="{{ route('admin.instructorias.index') }}" id="iaFilterForm" style="display:contents">
        @if(isset($showAll) && $showAll)
            <input type="hidden" name="all" value="1">
        @endif
        <div class="ia-filter-wrap">
            <i class="ti ti-calendar"></i>
            <select
                name="semester"
                class="ia-select"
                onchange="document.getElementById('iaFilterForm').submit()"
                aria-label="Filtrar por ciclo"
            >
                <option value="">Todos los ciclos</option>
                @foreach($semesters as $sem)
                    <option value="{{ $sem }}" {{ $semester === $sem ? 'selected' : '' }}>
                        Ciclo {{ $sem }}
                    </option>
                @endforeach
            </select>
        </div>
    </form>

    {{-- Chip de filtro activo --}}
    @if($semester)
        <div class="ia-active-filter">
            <i class="ti ti-filter"></i>
            Ciclo {{ $semester }}
            <button
                type="button"
                onclick="window.location='{{ route('admin.instructorias.index', array_filter(['all' => $showAll ? 1 : null])) }}'"
                title="Quitar filtro"
                aria-label="Quitar filtro de ciclo"
            ><i class="ti ti-x"></i></button>
        </div>
    @endif

    <span class="ia-results-count" id="iaCount">
        {{ $total }} resultado(s)
    </span>
</div>

{{-- ── Tabla ────────────────────────────────────────────────── --}}
<div class="ia-table-card">
    <div class="ia-table-wrap">
        <table class="ia-table" id="iaTable">
            <thead>
                <tr>
                    <th>Instructor</th>
                    <th>Grupo / Materia</th>
                    <th>Ciclo</th>
                    <th>Horario</th>
                    <th>Modalidad</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="iaTableBody">
                @forelse($assignments as $a)
                    @php
                        $instr      = $a->instructor?->user;
                        $group      = $a->classGroup;
                        $statusRaw  = strtolower($a->status ?? '');
                        $badgeClass = match($statusRaw) {
                            'activo'      => 'ia-badge-active',
                            'finalizado'  => 'ia-badge-finished',
                            'inactivo'    => 'ia-badge-inactive',
                            default       => 'ia-badge-default',
                        };
                        $initials = collect(explode(' ', $instr?->name ?? '-'))
                            ->filter()->take(2)
                            ->map(fn($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
                    @endphp
                    <tr data-id="{{ $a->id }}" data-search="{{ strtolower(($instr?->name ?? '') . ' ' . ($group?->name ?? '')) }}">
                        <td>
                            <div style="display:flex;align-items:center;gap:10px">
                                <div class="ia-avatar">{{ $initials ?: '?' }}</div>
                                <div>
                                    <div class="ia-td-main">{{ $instr?->name ?? '—' }}</div>
                                    <div class="ia-td-sub">{{ $instr?->email ?? '—' }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="ia-td-main">{{ $group?->name ?? '—' }}</div>
                            @if($group?->professor)
                                <div class="ia-td-sub">{{ $group->professor }}</div>
                            @endif
                        </td>
                        <td>
                            @if($group?->semester)
                                <span class="ia-cycle-pill">{{ $group->semester }}</span>
                            @else
                                <span class="ia-td-muted">—</span>
                            @endif
                        </td>
                        <td class="ia-td-muted">
                            {{ $a->schedule ?? $group?->schedule ?? '—' }}
                        </td>
                        <td class="ia-td-muted">
                            {{ $a->modality ?? $group?->modality ?? '—' }}
                        </td>
                        <td>
                            <span class="ia-badge {{ $badgeClass }}">
                                {{ ucfirst($a->status ?? '—') }}
                            </span>
                        </td>
                        <td>
                            <button
                                class="ia-btn ia-btn-ghost ia-btn-sm"
                                onclick="iaOpenDetail({{ $a->id }})"
                                title="Ver detalles"
                            >
                                <i class="ti ti-eye" aria-hidden="true"></i> Ver
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr id="iaEmptyRow">
                        <td colspan="7">
                            <div class="ia-empty">
                                <i class="ti ti-calendar-off"></i>
                                <p>No hay asignaciones registradas{{ $semester ? ' para el ciclo ' . $semester : '' }}.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if(method_exists($assignments, 'hasPages') && $assignments->hasPages())
        <div class="ia-pagination">
            {{ $assignments->links() }}
        </div>
    @endif
</div>

{{-- ══════════════════════════════════════════════════════════
     MODAL DETALLE INSTRUCTORÍA
     ══════════════════════════════════════════════════════════ --}}
<div class="ia-modal-overlay" id="iaModalOverlay" role="dialog" aria-modal="true" aria-labelledby="iaModalTitle">
    <div class="ia-modal" id="iaModal">

        {{-- Hero: datos del instructor --}}
        <div class="ia-modal-hero">
            <div class="ia-modal-hero-top">
                <div>
                    <div class="ia-modal-hero-name" id="ia_name">—</div>
                    <div class="ia-modal-hero-email" id="ia_email">—</div>
                </div>
                <button class="ia-modal-hero-close" onclick="iaCloseDetail()" aria-label="Cerrar">
                    <i class="ti ti-x"></i>
                </button>
            </div>
            <div class="ia-modal-hero-meta">
                <span class="ia-modal-hero-pill" id="ia_status_pill">
                    <i class="ti ti-circle"></i> <span id="ia_status">—</span>
                </span>
                <span class="ia-modal-hero-pill" id="ia_semester_pill">
                    <i class="ti ti-calendar"></i> <span id="ia_semester">—</span>
                </span>
                <span class="ia-modal-hero-pill">
                    <i class="ti ti-users"></i> <span id="ia_students">—</span> estudiantes
                </span>
            </div>
        </div>

        {{-- Body con secciones --}}
        <div class="ia-modal-body">

            {{-- Sección: Grupo / Materia --}}
            <div class="ia-modal-section">
                <div class="ia-modal-section-header">
                    <i class="ti ti-school" aria-hidden="true"></i> Grupo / Materia
                </div>
                <div class="ia-modal-section-body cols-2">
                    <div class="ia-modal-field">
                        <span class="ia-modal-field-label">Nombre del grupo</span>
                        <span class="ia-modal-field-val" id="ia_group">—</span>
                    </div>
                    <div class="ia-modal-field">
                        <span class="ia-modal-field-label">Docente titular</span>
                        <span class="ia-modal-field-val" id="ia_professor">—</span>
                    </div>
                    <div class="ia-modal-field">
                        <span class="ia-modal-field-label">Ciclo</span>
                        <span class="ia-modal-field-val" id="ia_semester_field">—</span>
                    </div>
                    <div class="ia-modal-field">
                        <span class="ia-modal-field-label">Estudiantes inscritos</span>
                        <span class="ia-modal-field-val" id="ia_students_field">—</span>
                    </div>
                </div>
            </div>

            {{-- Sección: Logística --}}
            <div class="ia-modal-section">
                <div class="ia-modal-section-header">
                    <i class="ti ti-info-circle" aria-hidden="true"></i> Detalles
                </div>
                <div class="ia-modal-section-body cols-2">
                    <div class="ia-modal-field">
                        <span class="ia-modal-field-label">Horario</span>
                        <span class="ia-modal-field-val" id="ia_schedule">—</span>
                    </div>
                    <div class="ia-modal-field">
                        <span class="ia-modal-field-label">Modalidad</span>
                        <span class="ia-modal-field-val" id="ia_modality">—</span>
                    </div>
                    <div class="ia-modal-field" id="ia_classroom_wrap">
                        <span class="ia-modal-field-label">Aula</span>
                        <span class="ia-modal-field-val" id="ia_classroom">—</span>
                    </div>
                    <div class="ia-modal-field" id="ia_link_wrap" style="display:none">
                        <span class="ia-modal-field-label">Enlace virtual</span>
                        <a class="ia-modal-link" id="ia_link" href="#" target="_blank" rel="noopener">—</a>
                    </div>
                </div>
            </div>

            {{-- Sección: Sesiones --}}
            <div class="ia-modal-section">
                <div class="ia-modal-section-header">
                    <i class="ti ti-calendar-event" aria-hidden="true"></i>
                    Sesiones
                    <span style="margin-left:auto;font-size:11px;color:var(--accent);font-weight:500" id="ia_sessions_count"></span>
                </div>
                <div style="padding:14px;overflow-x:auto" id="ia_sessions_wrap">
                    <p class="ia-td-muted" style="font-size:13px">Cargando sesiones…</p>
                </div>
            </div>

        </div>

        {{-- Footer --}}
        <div class="ia-modal-footer">
            <button class="ia-btn ia-btn-ghost" onclick="iaCloseDetail()">
                <i class="ti ti-x" aria-hidden="true"></i> Cerrar
            </button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
// ── Filtro client-side por texto ───────────────────────────
function iaFilterTable() {
    const q = document.getElementById('iaSearch').value.toLowerCase().trim();
    const rows = document.querySelectorAll('#iaTableBody tr[data-search]');
    let visible = 0;
    rows.forEach(tr => {
        const match = !q || tr.dataset.search.includes(q);
        tr.style.display = match ? '' : 'none';
        if (match) visible++;
    });
    const countEl = document.getElementById('iaCount');
    if (countEl) countEl.textContent = visible + ' resultado(s)';
}

// ── Modal: abrir ───────────────────────────────────────────
const IA_BASE = @json(url('/admin/instructorias'));

function iaOpenDetail(id) {
    // Mostrar overlay con skeleton inmediato
    const overlay = document.getElementById('iaModalOverlay');
    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
    _iaSkeleton();

    fetch(`${IA_BASE}/${id}`, {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
    .then(r => r.ok ? r.json() : Promise.reject(r.status))
    .then(data => _iaPopulate(data))
    .catch(err => {
        document.getElementById('ia_sessions_wrap').innerHTML =
            `<p style="color:var(--danger-text);font-size:13px">Error al cargar (${err}). Intenta de nuevo.</p>`;
    });
}

function iaCloseDetail() {
    document.getElementById('iaModalOverlay').classList.remove('open');
    document.body.style.overflow = '';
}

// Cerrar al hacer clic fuera del modal
document.getElementById('iaModalOverlay').addEventListener('click', function(e) {
    if (e.target === this) iaCloseDetail();
});
// Cerrar con Escape
document.addEventListener('keydown', e => { if (e.key === 'Escape') iaCloseDetail(); });

// ── Skeleton mientras carga ────────────────────────────────
function _iaSkeleton() {
    ['ia_name','ia_email','ia_group','ia_professor','ia_semester_field',
     'ia_students_field','ia_schedule','ia_modality','ia_classroom'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.innerHTML = '<span class="ia-skeleton" style="width:70%;display:inline-block">&nbsp;</span>';
    });
    document.getElementById('ia_sessions_wrap').innerHTML =
        '<p style="font-size:13px;color:var(--text-muted)">Cargando sesiones…</p>';
}

// ── Poblar modal con datos ─────────────────────────────────
function _iaPopulate(data) {
    const instr    = data.instructor || {};
    const group    = data.class_group || {};
    const asgn     = data.assignment  || {};

    const name  = instr.name  || '—';
    const email = instr.email || '—';
    const status = asgn.status || '—';

    document.getElementById('ia_name').textContent  = name;
    document.getElementById('ia_email').textContent = email;

    // Pills del hero
    document.getElementById('ia_status').textContent   = _ucfirst(status);
    document.getElementById('ia_semester').textContent = group.semester || '—';
    document.getElementById('ia_students').textContent = group.students_count ?? '—';

    // Grupo
    document.getElementById('ia_group').textContent         = group.name      || '—';
    document.getElementById('ia_professor').textContent     = group.professor  || '—';
    document.getElementById('ia_semester_field').textContent= group.semester   || '—';
    document.getElementById('ia_students_field').textContent= group.students_count != null
        ? group.students_count + ' estudiante(s)' : '—';

    // Logística
    const schedule  = asgn.schedule  || group.schedule  || '—';
    const modality  = asgn.modality  || group.modality  || '—';
    const classroom = asgn.classroom || group.classroom  || '—';
    const link      = asgn.virtual_link || null;

    document.getElementById('ia_schedule').textContent = schedule;
    document.getElementById('ia_modality').textContent = modality;
    document.getElementById('ia_classroom').textContent = classroom;

    const linkWrap = document.getElementById('ia_link_wrap');
    const classWrap = document.getElementById('ia_classroom_wrap');
    if (link) {
        linkWrap.style.display = '';
        classWrap.style.display = 'none';
        const linkEl = document.getElementById('ia_link');
        linkEl.href = link;
        linkEl.textContent = link;
    } else {
        linkWrap.style.display = 'none';
        classWrap.style.display = '';
    }

    // Sesiones
    const sessions = data.sessions || [];
    const countEl  = document.getElementById('ia_sessions_count');
    countEl.textContent = sessions.length + ' sesión(es)';

    const wrap = document.getElementById('ia_sessions_wrap');
    if (!sessions.length) {
        wrap.innerHTML = '<p style="font-size:13px;color:var(--text-muted)">Sin sesiones registradas.</p>';
        return;
    }

    const table = document.createElement('table');
    table.className = 'ia-sessions-table';
    table.innerHTML = `
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Horario</th>
                <th>Asistentes</th>
                <th>Estado</th>
            </tr>
        </thead>`;
    const tbody = document.createElement('tbody');
    sessions.forEach(s => {
        const tr = document.createElement('tr');
        const time = (s.start_time && s.end_time) ? `${s.start_time} – ${s.end_time}` : (s.start_time || '—');
        const statusCls = s.is_open ? 'ia-session-open' : 'ia-session-closed';
        const statusLbl = s.is_open ? 'Abierta' : 'Cerrada';
        tr.innerHTML = `
            <td style="font-weight:500;color:var(--text)">${s.date || '—'}</td>
            <td>${time}</td>
            <td><span class="ia-attendees-pill"><i class="ti ti-user-check" style="font-size:10px"></i> ${s.attendees_count ?? 0}</span></td>
            <td><span class="ia-session-status ${statusCls}">${statusLbl}</span></td>`;
        tbody.appendChild(tr);
    });
    table.appendChild(tbody);
    wrap.innerHTML = '';
    wrap.appendChild(table);
}

function _ucfirst(str) {
    if (!str) return '—';
    return str.charAt(0).toUpperCase() + str.slice(1);
}
</script>
@endpush
