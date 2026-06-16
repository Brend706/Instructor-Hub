@extends('layouts.admin', ['title' => 'Solicitudes de suspensión'])

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/suspensions.css') }}">
@endpush

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">Solicitudes de suspensión</h1>
        <p class="page-sub">Todas las solicitudes del sistema — todas las coordinaciones</p>
    </div>
</div>

@if(session('success'))
    <div class="alert-success" role="alert">
        <i class="ti ti-circle-check"></i> {{ session('success') }}
    </div>
@endif
@if(session('error'))
    <div class="alert-error" role="alert">
        <i class="ti ti-alert-circle"></i> {{ session('error') }}
    </div>
@endif

<div class="susp-stats">
    <div class="susp-stat">
        <div class="susp-stat-icon pending"><i class="ti ti-clock"></i></div>
        <div>
            <div class="susp-stat-val">{{ $pendingCount }}</div>
            <div class="susp-stat-lbl">Pendientes</div>
        </div>
    </div>
    <div class="susp-stat">
        <div class="susp-stat-icon approved"><i class="ti ti-circle-check"></i></div>
        <div>
            <div class="susp-stat-val">{{ $requests->getCollection()->where('status','approved')->count() }}</div>
            <div class="susp-stat-lbl">Aprobadas</div>
        </div>
    </div>
    <div class="susp-stat">
        <div class="susp-stat-icon rejected"><i class="ti ti-circle-x"></i></div>
        <div>
            <div class="susp-stat-val">{{ $requests->getCollection()->where('status','rejected')->count() }}</div>
            <div class="susp-stat-lbl">Rechazadas</div>
        </div>
    </div>
</div>

<div class="table-card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Instructor</th>
                    <th>Coordinación</th>
                    <th>Tipo</th>
                    <th>Motivo (resumen)</th>
                    <th>Grupo afectado</th>
                    <th>Solicitado</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($requests as $req)
                    @php
                        $name       = $req->instructor?->user?->name ?? '—';
                        $email      = $req->instructor?->user?->email ?? '';
                        $coordName  = $req->instructor?->coordinator?->school_name
                                   ?? $req->instructor?->coordinator?->catedra
                                   ?? $req->instructor?->coordinator?->name
                                   ?? '—';
                        $group      = $req->assignment?->classGroup?->name ?? '—';
                        $typeLabel  = \App\Models\SuspensionRequest::TYPE_LABELS[$req->type] ?? $req->type;
                        $statusBadge = match($req->status) {
                            'pending'  => ['class'=>'badge-pending',  'label'=>'Pendiente'],
                            'approved' => ['class'=>'badge-approved', 'label'=>'Aprobada'],
                            'rejected' => ['class'=>'badge-rejected', 'label'=>'Rechazada'],
                            default    => ['class'=>'badge-pending',  'label'=>$req->status],
                        };
                    @endphp
                    <tr>
                        <td>
                            <div class="td-name">{{ $name }}</div>
                            <div class="td-email">{{ $email }}</div>
                        </td>
                        <td style="font-size:12px;color:var(--text-soft)">{{ $coordName }}</td>
                        <td><span class="type-chip">{{ $typeLabel }}</span></td>
                        <td>
                            <div class="td-reason" title="{{ $req->reason }}">{{ $req->reason }}</div>
                        </td>
                        <td style="font-size:12px">{{ $group }}</td>
                        <td style="font-size:12px;color:var(--text-muted)">
                            {{ $req->requested_at?->format('d/m/Y H:i') }}
                        </td>
                        <td>
                            <span class="badge {{ $statusBadge['class'] }}">
                                <span class="badge-dot"></span>
                                {{ $statusBadge['label'] }}
                            </span>
                        </td>
                        <td>
                            <div class="actions">
                                <button type="button" class="btn btn-ghost btn-sm"
                                    onclick="openDetail({{ $req->id }})">
                                    <i class="ti ti-eye"></i>
                                </button>
                                @if($req->status === 'pending')
                                    <button type="button" class="btn btn-success btn-sm"
                                        onclick="openApprove({{ $req->id }})">
                                        <i class="ti ti-check"></i> Aprobar
                                    </button>
                                    <button type="button" class="btn btn-danger btn-sm"
                                        onclick="openReject({{ $req->id }})">
                                        <i class="ti ti-x"></i> Rechazar
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="empty-state">
                            <i class="ti ti-file-check"></i>
                            <p>No hay solicitudes de suspensión registradas.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($requests->hasPages())
    <div style="margin-top:16px">{{ $requests->links() }}</div>
@endif

@php
$requestsJson = $requests->getCollection()->map(function($r) {
    return [
        'id'          => $r->id,
        'name'        => $r->instructor?->user?->name ?? '—',
        'email'       => $r->instructor?->user?->email ?? '',
        'type_label'  => \App\Models\SuspensionRequest::TYPE_LABELS[$r->type] ?? $r->type,
        'reason'      => $r->reason,
        'group'       => $r->assignment?->classGroup?->name ?? 'Sin asignación activa',
        'requested'   => $r->requested_at?->format('d/m/Y H:i'),
        'reviewed'    => $r->reviewed_at?->format('d/m/Y H:i'),
        'status'      => $r->status,
        'admin_notes' => $r->admin_notes,
        'reviewer'    => $r->reviewer?->name,
        'approve_url' => route('admin.suspensions.approve', $r->id),
        'reject_url'  => route('admin.suspensions.reject',  $r->id),
    ];
});
@endphp
<script>
const REQUESTS_DATA = @json($requestsJson);
</script>

{{-- Modales (mismos que coordinator pero con rutas admin) --}}
<div class="modal-overlay" id="modalDetail">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title"><i class="ti ti-file-description" style="color:var(--accent);margin-right:6px"></i> Detalle de solicitud</div>
            <button class="modal-close" onclick="closeModal('modalDetail')"><i class="ti ti-x"></i></button>
        </div>
        <div class="modal-body">
            <div class="modal-info-row"><div class="modal-info-label">Instructor</div><div class="modal-info-value" id="detailName">—</div></div>
            <div class="modal-info-row"><div class="modal-info-label">Tipo</div><div class="modal-info-value" id="detailType">—</div></div>
            <div class="modal-info-row"><div class="modal-info-label">Grupo afectado</div><div class="modal-info-value" id="detailGroup">—</div></div>
            <div class="modal-info-row"><div class="modal-info-label">Motivo</div><div class="modal-reason-box" id="detailReason"></div></div>
            <div class="modal-info-row"><div class="modal-info-label">Solicitado</div><div class="modal-info-value" id="detailDate">—</div></div>
            <div id="detailReviewBlock" style="display:none">
                <div class="modal-sep"></div>
                <div class="modal-info-row" style="margin-top:10px">
                    <div class="modal-info-label">Nota del revisor</div>
                    <div class="modal-reason-box" id="detailNotes"></div>
                </div>
                <div class="modal-info-row"><div class="modal-info-label">Revisado por</div><div class="modal-info-value" id="detailReviewer">—</div></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal('modalDetail')">Cerrar</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="modalApprove">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title" style="color:var(--success-text)"><i class="ti ti-circle-check" style="margin-right:6px"></i> Aprobar solicitud</div>
            <button class="modal-close" onclick="closeModal('modalApprove')"><i class="ti ti-x"></i></button>
        </div>
        <form method="POST" id="approveForm" action="">
            @csrf
            <div class="modal-body">
                <p style="font-size:13px;color:var(--text-soft);margin:0">La cuenta del instructor quedará <strong>Suspendida</strong> y no podrá iniciar sesión.</p>
                <div class="field">
                    <label class="field-label">Nota interna (opcional)</label>
                    <textarea name="admin_notes" rows="3" placeholder="Observaciones para el expediente..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('modalApprove')">Cancelar</button>
                <button type="submit" class="btn btn-success"><i class="ti ti-check"></i> Confirmar aprobación</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modalReject">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title" style="color:#B91C1C"><i class="ti ti-circle-x" style="margin-right:6px"></i> Rechazar solicitud</div>
            <button class="modal-close" onclick="closeModal('modalReject')"><i class="ti ti-x"></i></button>
        </div>
        <form method="POST" id="rejectForm" action="">
            @csrf
            <div class="modal-body">
                <p style="font-size:13px;color:var(--text-soft);margin:0">El instructor seguirá activo. La solicitud quedará archivada como rechazada.</p>
                <div class="field">
                    <label class="field-label">Motivo del rechazo <span style="color:#B91C1C">*</span></label>
                    <textarea name="admin_notes" rows="3" required placeholder="Explica el motivo del rechazo..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('modalReject')">Cancelar</button>
                <button type="submit" class="btn btn-danger"><i class="ti ti-x"></i> Confirmar rechazo</button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
function openModal(id) { document.getElementById(id).classList.add('open'); document.body.style.overflow = 'hidden'; }
function closeModal(id) { document.getElementById(id).classList.remove('open'); document.body.style.overflow = ''; }
document.querySelectorAll('.modal-overlay').forEach(o => { o.addEventListener('click', e => { if (e.target === o) closeModal(o.id); }); });

function openDetail(id) {
    const r = REQUESTS_DATA.find(x => x.id === id); if (!r) return;
    document.getElementById('detailName').textContent   = `${r.name} (${r.email})`;
    document.getElementById('detailType').textContent   = r.type_label;
    document.getElementById('detailGroup').textContent  = r.group;
    document.getElementById('detailReason').textContent = r.reason;
    document.getElementById('detailDate').textContent   = r.requested;
    const rb = document.getElementById('detailReviewBlock');
    if (r.admin_notes || r.reviewer) {
        rb.style.display = '';
        document.getElementById('detailNotes').textContent    = r.admin_notes || '—';
        document.getElementById('detailReviewer').textContent = `${r.reviewer ?? '—'} · ${r.reviewed ?? '—'}`;
    } else { rb.style.display = 'none'; }
    openModal('modalDetail');
}
function openApprove(id) {
    const r = REQUESTS_DATA.find(x => x.id === id); if (!r) return;
    document.getElementById('approveForm').action = r.approve_url;
    openModal('modalApprove');
}
function openReject(id) {
    const r = REQUESTS_DATA.find(x => x.id === id); if (!r) return;
    document.getElementById('rejectForm').action = r.reject_url;
    openModal('modalReject');
}
</script>
@endpush
