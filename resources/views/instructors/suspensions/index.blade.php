@extends('layouts.instructor', ['title' => 'Mis solicitudes'])

@section('content')

{{-- Hero --}}
<div style="
    background: linear-gradient(135deg, var(--primary) 0%, #8B1A4A 100%);
    border-radius: 14px;
    padding: 28px 30px;
    margin-bottom: 24px;
    color: #fff;
    position: relative;
    overflow: hidden;
">
    <div style="position:absolute;right:-10px;top:-10px;font-size:120px;opacity:.06;line-height:1">
        <i class="ti ti-player-pause"></i>
    </div>
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap">
        <div>
            <div style="font-size:11px;font-weight:600;letter-spacing:1px;text-transform:uppercase;opacity:.75;margin-bottom:6px">
                Instructor Hub · Solicitudes
            </div>
            <h1 style="font-size:22px;font-weight:700;margin:0 0 6px">Mis solicitudes de suspensión</h1>
            <p style="font-size:13px;opacity:.85;margin:0">
                Envía una solicitud a tu coordinador si necesitas pausar tu instructoría. Aquí también puedes ver el historial de solicitudes anteriores.
            </p>
        </div>
    </div>
</div>

{{-- Flash de éxito --}}
@if(session('suspension_success'))
    <div style="padding:14px 18px;background:#F0FDF4;border:1.5px solid #86EFAC;border-radius:10px;
                margin-bottom:20px;display:flex;align-items:center;gap:10px;font-size:13px;color:#166534">
        <i class="ti ti-circle-check" style="font-size:18px;flex-shrink:0"></i>
        {{ session('suspension_success') }}
    </div>
@endif

{{-- Estado: solicitud pendiente --}}
@if($hasPending)
    <div style="padding:16px 18px;background:#FFFBEB;border:1.5px solid #FDE68A;border-radius:10px;
                margin-bottom:20px;display:flex;align-items:center;gap:12px">
        <i class="ti ti-clock" style="color:#D97706;font-size:22px;flex-shrink:0"></i>
        <div>
            <div style="font-weight:600;font-size:13px;color:#92400E">Tienes una solicitud en revisión</div>
            <div style="font-size:12px;color:#78350F;margin-top:2px">
                Debes esperar a que tu coordinador la resuelva antes de enviar una nueva.
            </div>
        </div>
    </div>
@endif

{{-- Historial --}}
<div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden">

    <div style="padding:18px 20px 14px;border-bottom:1px solid var(--border);
                display:flex;align-items:center;gap:10px">
        <div style="width:34px;height:34px;border-radius:8px;background:#EEF2FF;
                    display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i class="ti ti-history" style="color:var(--accent);font-size:16px"></i>
        </div>
        <div style="flex:1;min-width:0">
            <div style="font-size:14px;font-weight:600;color:var(--text)">Historial de solicitudes</div>
            <div style="font-size:11.5px;color:var(--text-muted)">{{ $requests->count() }} en total</div>
        </div>
        @if(!$hasPending && $instructor->isActive())
            <button onclick="document.getElementById('modalSolicitud').classList.add('open')"
               style="flex-shrink:0;display:inline-flex;align-items:center;gap:6px;
                      padding:7px 14px;border-radius:8px;border:1.5px solid var(--primary);
                      background:transparent;color:var(--primary);font-size:12px;font-weight:600;
                      cursor:pointer;transition:background .15s;white-space:nowrap;font-family:inherit"
               onmouseover="this.style.background='var(--primary-50)'"
               onmouseout="this.style.background='transparent'">
                <i class="ti ti-plus" style="font-size:13px"></i> Nueva solicitud
            </button>
        @endif
    </div>

    <div style="padding:16px;display:flex;flex-direction:column;gap:10px">
        @forelse($requests as $req)
            @php
                $statusMap = [
                    'pending'  => ['label' => 'En revisión', 'bg' => '#FFFBEB', 'color' => '#92400E', 'icon' => 'clock'],
                    'approved' => ['label' => 'Aprobada',    'bg' => '#F0FDF4', 'color' => '#166534', 'icon' => 'circle-check'],
                    'rejected' => ['label' => 'Rechazada',   'bg' => '#FEF2F2', 'color' => '#B91C1C', 'icon' => 'circle-x'],
                ];
                $st = $statusMap[$req->status] ?? ['label' => $req->status, 'bg' => '#F3F4F6', 'color' => '#6B7280', 'icon' => 'minus'];
                $typeMap = [
                    'voluntary'     => 'Voluntaria',
                    'force_majeure' => 'Fuerza mayor',
                    'other'         => 'Otra razón',
                ];
                $typeLabel = $typeMap[$req->type] ?? $req->type;
            @endphp
            <div style="border:1px solid var(--border);border-radius:10px;overflow:hidden">
                <div style="padding:12px 14px;display:flex;align-items:center;
                            justify-content:space-between;gap:10px;background:var(--bg)">
                    <div style="display:flex;align-items:center;gap:8px;min-width:0">
                        <i class="ti ti-player-pause" style="color:var(--primary);font-size:15px;flex-shrink:0"></i>
                        <div style="min-width:0">
                            <div style="font-size:12.5px;font-weight:600;color:var(--text);
                                        white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                                {{ $typeLabel }}
                                @if($req->assignment?->classGroup?->name)
                                    — {{ $req->assignment->classGroup->name }}
                                @endif
                            </div>
                            <div style="font-size:11px;color:var(--text-muted)">
                                {{ $req->requested_at?->translatedFormat('j \d\e F Y') ?? '—' }}
                            </div>
                        </div>
                    </div>
                    <span style="flex-shrink:0;font-size:11px;font-weight:600;padding:3px 10px;
                                 border-radius:20px;background:{{ $st['bg'] }};color:{{ $st['color'] }};white-space:nowrap">
                        <i class="ti ti-{{ $st['icon'] }}" style="font-size:10px"></i>
                        {{ $st['label'] }}
                    </span>
                </div>
                @if($req->reason)
                    <div style="padding:10px 14px;border-top:1px solid var(--border);
                                font-size:12px;color:var(--text-soft);line-height:1.5">
                        {{ Str::limit($req->reason, 200) }}
                    </div>
                @endif
                @if($req->coordinator_notes ?? $req->admin_notes ?? null)
                    <div style="padding:10px 14px;border-top:1px solid var(--border);background:#F8FAFF;
                                font-size:12px;color:var(--text-soft);display:flex;gap:7px">
                        <i class="ti ti-message-dots" style="color:var(--accent);flex-shrink:0;margin-top:1px"></i>
                        <span>{{ $req->coordinator_notes ?? $req->admin_notes }}</span>
                    </div>
                @endif
                @if(in_array($req->status, ['approved','rejected'], true))
                    {{-- Comprobante PDF descargable cuando la solicitud propia ya fue resuelta.
                         Se colorea verde para aprobada y rojo para rechazada para que el usuario
                         identifique el tipo de comprobante de un vistazo. --}}
                    @php
                        $isApprovedReq = $req->status === 'approved';
                        $btnBorder = $isApprovedReq ? '#16A34A' : '#B91C1C';
                        $btnBg     = $isApprovedReq ? '#F0FDF4' : '#FEF2F2';
                        $btnBgHov  = $isApprovedReq ? '#DCFCE7' : '#FEE2E2';
                        $btnColor  = $isApprovedReq ? '#166534' : '#991B1B';
                        $btnLabel  = $isApprovedReq
                            ? 'Descargar comprobante de aprobación (PDF)'
                            : 'Descargar comprobante de rechazo (PDF)';
                    @endphp
                    <div style="padding:10px 14px;border-top:1px solid var(--border);
                                display:flex;justify-content:flex-end">
                        <a href="{{ route('suspensions.receipt', $req->id) }}"
                           style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;
                                  border-radius:8px;border:1.5px solid {{ $btnBorder }};background:{{ $btnBg }};
                                  color:{{ $btnColor }};font-size:12px;font-weight:600;text-decoration:none;
                                  transition:background .15s"
                           onmouseover="this.style.background='{{ $btnBgHov }}'"
                           onmouseout="this.style.background='{{ $btnBg }}'">
                            <i class="ti ti-file-download" style="font-size:14px"></i>
                            {{ $btnLabel }}
                        </a>
                    </div>
                @endif
            </div>
        @empty
            <div style="text-align:center;padding:40px 0;color:var(--text-muted)">
                <i class="ti ti-inbox" style="font-size:36px;display:block;margin-bottom:10px;opacity:.35"></i>
                <div style="font-size:13px;font-weight:500">No has enviado solicitudes aún</div>
            </div>
        @endforelse
    </div>
</div>

{{-- ── Modal: nueva solicitud ──────────────────────────────── --}}
<div id="modalSolicitud"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);
            z-index:1000;align-items:center;justify-content:center;padding:20px">
    <div style="background:var(--surface);border-radius:14px;width:100%;max-width:500px;
                box-shadow:0 20px 60px rgba(0,0,0,.2)">

        {{-- Header --}}
        <div style="display:flex;align-items:center;justify-content:space-between;
                    padding:18px 20px 14px;border-bottom:1px solid var(--border)">
            <div style="display:flex;align-items:center;gap:10px">
                <div style="width:32px;height:32px;border-radius:8px;background:var(--primary-50);
                            display:flex;align-items:center;justify-content:center">
                    <i class="ti ti-player-pause" style="color:var(--primary);font-size:15px"></i>
                </div>
                <div style="font-size:14px;font-weight:600;color:var(--text)">Nueva solicitud de suspensión</div>
            </div>
            <button onclick="document.getElementById('modalSolicitud').classList.remove('open')"
                style="background:none;border:none;cursor:pointer;color:var(--text-muted);
                       font-size:20px;padding:2px 6px;border-radius:6px;line-height:1">
                <i class="ti ti-x"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('instructor.suspension.store') }}">
            @csrf
            <div style="padding:20px;display:flex;flex-direction:column;gap:16px">

                <div style="padding:12px 14px;background:var(--primary-50);border:1px solid var(--primary-100);
                            border-radius:8px;font-size:12px;color:var(--text-soft);display:flex;gap:8px">
                    <i class="ti ti-info-circle" style="color:var(--primary);flex-shrink:0;margin-top:1px"></i>
                    <span>Tu solicitud será revisada por tu coordinador. Mientras esté pendiente, tu cuenta seguirá activa. Si es aprobada, no podrás iniciar sesión hasta ser reactivado.</span>
                </div>

                @if($errors->has('suspension') || $errors->has('type') || $errors->has('reason'))
                    <div style="padding:10px 14px;background:#FEF2F2;border:1px solid #FECACA;
                                border-radius:8px;font-size:12px;color:#B91C1C;display:flex;gap:8px">
                        <i class="ti ti-alert-circle" style="flex-shrink:0"></i>
                        <span>{{ $errors->first('suspension') ?: $errors->first('type') ?: $errors->first('reason') }}</span>
                    </div>
                @endif

                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:var(--text-soft);margin-bottom:5px">
                        Tipo de solicitud <span style="color:var(--primary)">*</span>
                    </label>
                    <select name="type" required
                        style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px;
                               background:var(--bg);color:var(--text);font-size:13px;font-family:inherit;outline:none">
                        <option value="">Seleccionar...</option>
                        <option value="voluntary"     {{ old('type') === 'voluntary'     ? 'selected' : '' }}>Solicitud voluntaria</option>
                        <option value="force_majeure" {{ old('type') === 'force_majeure' ? 'selected' : '' }}>Fuerza mayor (salud, emergencia familiar…)</option>
                        <option value="other"         {{ old('type') === 'other'         ? 'selected' : '' }}>Otra razón</option>
                    </select>
                </div>

                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:var(--text-soft);margin-bottom:5px">
                        Motivo de la solicitud <span style="color:var(--primary)">*</span>
                    </label>
                    <textarea name="reason" required minlength="20" maxlength="2000" rows="5"
                        placeholder="Describe tu situación con el mayor detalle posible para que el coordinador pueda evaluarla…"
                        style="width:100%;resize:vertical;font-family:inherit;font-size:13px;padding:9px 12px;
                               border:1px solid var(--border);border-radius:8px;background:var(--bg);
                               color:var(--text);outline:none;box-sizing:border-box;transition:border-color .15s"
                        onfocus="this.style.borderColor='var(--accent)'"
                        onblur="this.style.borderColor='var(--border)'">{{ old('reason') }}</textarea>
                    <span style="font-size:11px;color:var(--text-muted)">Mínimo 20 caracteres.</span>
                </div>

            </div>

            <div style="padding:14px 20px;border-top:1px solid var(--border);
                        display:flex;justify-content:flex-end;gap:10px">
                <button type="button"
                    onclick="document.getElementById('modalSolicitud').classList.remove('open')"
                    style="padding:8px 16px;border-radius:8px;border:1px solid var(--border);
                           background:transparent;color:var(--text-soft);font-size:13px;cursor:pointer;font-family:inherit">
                    Cancelar
                </button>
                <button type="submit"
                    style="display:inline-flex;align-items:center;gap:7px;padding:8px 18px;
                           border-radius:8px;border:none;background:var(--primary);
                           color:#fff;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit">
                    <i class="ti ti-send"></i> Enviar solicitud
                </button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
const _modal = document.getElementById('modalSolicitud');
if (_modal) {
    // Mostrar/ocultar con clase 'open'
    new MutationObserver(() => {
        _modal.style.display = _modal.classList.contains('open') ? 'flex' : 'none';
    }).observe(_modal, { attributes: true, attributeFilter: ['class'] });

    // Cerrar al hacer clic fuera
    _modal.addEventListener('click', e => {
        if (e.target === _modal) _modal.classList.remove('open');
    });

    // Re-abrir si hubo errores de validación
    @if($errors->has('suspension') || $errors->has('type') || $errors->has('reason'))
        document.addEventListener('DOMContentLoaded', () => _modal.classList.add('open'));
    @endif
}
</script>
@endpush
