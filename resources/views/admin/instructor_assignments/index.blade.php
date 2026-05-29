@extends('layouts.admin', ['title' => 'Instructorías'])

@section('content')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/admin/coordinators.css') }}">
@endpush
<div class="page-header">
    <div>
        <h1 class="page-title">Instructorías</h1>
        <p class="page-sub">Todas las asignaciones de instructoría registradas</p>
    </div>
</div>

<div class="table-card">
    <div class="table-wrap">
        <table id="assignmentsTable">
            <thead>
                <tr>
                    <th>Instructor</th>
                    <th>Grupo / Materia</th>
                    <th>Ciclo</th>
                    <th>Horario</th>
                    <th>Modalidad</th>
                    <th>Aula / Enlace</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($assignments as $a)
                    @php
                        $instr = $a->instructor?->user;
                        $group = $a->classGroup;
                    @endphp
                    <tr data-id="{{ $a->id }}">
                        <td>
                            <div style="display:flex;align-items:center;gap:10px">
                                <div class="avatar">{{ strtoupper(substr($instr?->name ?? '-', 0, 1)) }}</div>
                                <div>
                                    <div class="td-main">{{ $instr?->name ?? '—' }}</div>
                                    <div style="font-size:11px;color:var(--text-muted)">{{ $instr?->email ?? '—' }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="td-main">{{ $group?->name ?? '—' }}</td>
                        <td style="font-size:11px;color:var(--text-muted)">Ciclo: {{ $group?->semester ?? '—' }}</td>
                        <td>{{ $a->schedule ?? $group?->schedule ?? '—' }}</td>
                        <td>{{ $a->modality ?? $group?->modality ?? '—' }}</td>
                        <td>{{ $a->classroom ?? $group?->classroom ?? ($a->virtual_link ? 'Enlace' : '—') }}</td>
                        <td><span class="badge">{{ ucfirst($a->status ?? '—') }}</span></td>
                        <td class="actions">
                            <button class="btn btn-ghost btn-sm" onclick="openAssignment({{ $a->id }})">Ver</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="empty-state">
                            <i class="ti ti-calendar-event" aria-hidden="true"></i>
                            <p>No hay asignaciones registradas</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($assignments->hasPages())
    <div class="pagination-wrap">
        {{ $assignments->links() }}
    </div>
@endif

{{-- Modal detalles --}}
<div class="modal-overlay" id="modalAssignment" role="dialog" aria-modal="true">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Detalle de la instructoría</div>
            <button class="modal-close" onclick="closeModal('modalAssignment')" aria-label="Cerrar">&times;</button>
        </div>
        <div class="modal-body">
            <div class="field"><label>Instructor</label><div id="a_instructor"></div></div>
            <div class="field"><label>Correo</label><div id="a_email"></div></div>
            <div class="field"><label>Grupo / Materia</label><div id="a_group"></div></div>
            <div class="field"><label>Ciclo</label><div id="a_semester"></div></div>
            <div class="field"><label>Horario</label><div id="a_schedule"></div></div>
            <div class="field"><label>Modalidad</label><div id="a_modality"></div></div>
            <div class="field"><label>Aula / Enlace</label><div id="a_classroom"></div></div>
            <div class="field"><label>Estado</label><div id="a_status"></div></div>
            <div class="field"><label>Sesiones</label><div id="a_sessions"></div></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal('modalAssignment')">Cerrar</button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    const ASSIGNMENT_BASE = @json(url('/admin/instructorias'));

    function openAssignment(id) {
        fetch(`${ASSIGNMENT_BASE}/${id}`)
            .then(r => r.json())
            .then(data => {
                document.getElementById('a_instructor').textContent = data.instructor || '—';
                document.getElementById('a_email').textContent = data.instructor_email || '—';
                document.getElementById('a_group').textContent = data.class_group || '—';
                document.getElementById('a_semester').textContent = data.semester || '—';
                document.getElementById('a_schedule').textContent = data.schedule || '—';
                document.getElementById('a_modality').textContent = data.modality || '—';
                document.getElementById('a_classroom').textContent = data.classroom || data.virtual_link || '—';
                document.getElementById('a_status').textContent = data.status || '—';

                const sessionsEl = document.getElementById('a_sessions');
                sessionsEl.innerHTML = '';
                if ((data.sessions || []).length === 0) {
                    sessionsEl.textContent = 'Sin sesiones registradas';
                } else {
                    const ul = document.createElement('ul');
                    data.sessions.forEach(s => {
                        const li = document.createElement('li');
                        li.textContent = `${s.date || ''} ${s.start_time || ''}-${s.end_time || ''} — Asistentes: ${s.attendees_count || 0}`;
                        ul.appendChild(li);
                    });
                    sessionsEl.appendChild(ul);
                }

                openModal('modalAssignment');
            })
            .catch(() => alert('Error al cargar detalle'));
    }

    function openModal(id) {
        document.getElementById(id).classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function closeModal(id) {
        document.getElementById(id).classList.remove('open');
        document.body.style.overflow = '';
    }
</script>
@endpush
