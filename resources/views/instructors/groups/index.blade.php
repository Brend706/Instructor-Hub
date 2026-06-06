@extends('layouts.instructor', ['title' => 'Mis grupos'])

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/instructor/groups.css') }}">
@endpush

@section('content')

<a href="{{ route('instructor.dashboard') }}" class="back-link">
    <i class="ti ti-arrow-left" aria-hidden="true"></i> Volver al dashboard
</a>

<div class="page-header">
    <div>
        <h1 class="page-title">Mis grupos</h1>
        <p class="page-sub">Historial de grupos donde has sido instructor tutor</p>
    </div>
    @if($assignments->isNotEmpty())
        <span style="font-size:12px;color:var(--text-muted);align-self:center">
            {{ $assignments->count() }} asignación(es)
        </span>
    @endif
</div>

@if($assignments->isEmpty())
    <div class="empty-card">
        <i class="ti ti-books-off" aria-hidden="true"></i>
        <p>No tienes grupos asignados todavía.<br>Tu coordinador te asignará cuando esté disponible.</p>
    </div>
@else
    <div class="groups-list">
        @foreach($assignments as $idx => $assignment)
            @php
                $group      = $assignment->classGroup;
                if (!$group) continue;
                $isFirst    = $idx === 0;
                $initials   = collect(explode(' ', $group->name))
                    ->filter()->take(2)->map(fn($w) => mb_strtoupper(mb_substr($w,0,1)))->implode('');
                $statusRaw  = strtolower($assignment->status ?? 'activo');
                $badgeClass = match($statusRaw) {
                    'activo'     => 'gc-badge-active',
                    'finalizado' => 'gc-badge-closed',
                    default      => 'gc-badge-pending',
                };
                $hasDetails = $assignment->schedule || $assignment->modality;
            @endphp

            <div class="group-card {{ $isFirst ? 'is-open' : '' }}" id="gc-{{ $assignment->id }}">

                {{-- ── Cabecera: siempre visible, clic colapsa/expande ── --}}
                <button
                    type="button"
                    class="group-card-head"
                    onclick="gcToggle({{ $assignment->id }})"
                    aria-expanded="{{ $isFirst ? 'true' : 'false' }}"
                    aria-controls="gc-body-{{ $assignment->id }}"
                >
                    <div class="group-card-left">
                        <div class="group-card-av {{ $statusRaw === 'finalizado' ? 'av-muted' : '' }}">
                            {{ $initials }}
                        </div>
                        <div>
                            <div class="group-card-name">{{ $group->name }}</div>
                            <div class="group-card-meta">
                                {{ $group->professor }}
                                <span class="gc-meta-sep">·</span>
                                <span class="gc-badge gc-badge-cycle" style="vertical-align:middle">{{ $group->semester }}</span>
                                <span class="gc-meta-sep">·</span>
                                <span class="gc-badge {{ $badgeClass }}" style="vertical-align:middle">
                                    {{ ucfirst($assignment->status ?? 'Activo') }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="group-card-right">
                        @if(!$hasDetails)
                            <span class="gc-badge gc-badge-pending" style="font-size:10px">
                                <i class="ti ti-alert-circle" style="font-size:11px"></i> Datos pendientes
                            </span>
                        @endif
                        <i class="ti ti-chevron-down gc-chevron" aria-hidden="true"></i>
                    </div>
                </button>

                {{-- ── Cuerpo colapsable ───────────────────────────── --}}
                <div class="group-card-body" id="gc-body-{{ $assignment->id }}">
                <div>{{-- wrapper único para el grid-template-rows trick --}}

                    {{-- Dos secciones --}}
                    <div class="group-sections">
                        {{-- Grupo de clase --}}
                        <div class="group-section group-section-grupo">
                            <div class="gs-label primary">
                                <i class="ti ti-school"></i> Grupo de clase
                            </div>
                            <div class="gs-row">
                                <div class="gs-key">Horario del grupo</div>
                                <div class="gs-val">{{ $group->schedule ?? '—' }}</div>
                            </div>
                            <div class="gs-row">
                                <div class="gs-key">Modalidad</div>
                                <div class="gs-val">{{ $group->modality ?? '—' }}</div>
                            </div>
                            @if($group->classroom)
                                <div class="gs-row">
                                    <div class="gs-key">Aula / Enlace</div>
                                    <div class="gs-val">{{ $group->classroom }}</div>
                                </div>
                            @endif
                        </div>

                        {{-- Mi instructoría --}}
                        <div class="group-section group-section-instructoria">
                            <div class="gs-label accent">
                                <i class="ti ti-user-check"></i> Mi instructoría
                            </div>
                            @if($hasDetails)
                                <div class="gs-row">
                                    <div class="gs-key">Mi horario</div>
                                    <div class="gs-val">{{ $assignment->schedule ?? '—' }}</div>
                                </div>
                                <div class="gs-row">
                                    <div class="gs-key">Modalidad</div>
                                    <div class="gs-val">{{ $assignment->modality ?? '—' }}</div>
                                </div>
                                @if($assignment->classroom || $assignment->virtual_link)
                                    <div class="gs-row">
                                        <div class="gs-key">Aula / Enlace</div>
                                        <div class="gs-val">{{ $assignment->classroom ?? $assignment->virtual_link }}</div>
                                    </div>
                                @endif
                            @else
                                <div class="gs-val muted" style="font-size:12px;margin-top:4px">
                                    Aún sin completar
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Footer --}}
                    <div class="group-card-footer">
                        <div class="group-card-students">
                            <i class="ti ti-users"></i>
                            {{ $group->students_count ?? 0 }} estudiantes
                        </div>
                        <div style="display:flex;gap:8px">
                            <a href="{{ route('instructor.groups.show', $assignment) }}"
                               class="btn btn-ghost btn-sm">
                                <i class="ti ti-users"></i> Ver estudiantes
                            </a>
                            <a href="{{ route('instructor.groups.show', $assignment) }}#instructoria"
                               class="btn btn-lavanda btn-sm">
                                <i class="ti ti-edit"></i>
                                {{ $hasDetails ? 'Editar instructoría' : 'Completar datos' }}
                            </a>
                        </div>
                    </div>

                </div>{{-- /wrapper --}}
                </div>{{-- /group-card-body --}}
            </div>
        @endforeach
    </div>
@endif

@endsection

@push('scripts')
<script>
function gcToggle(id) {
    const card    = document.getElementById('gc-' + id);
    const body    = document.getElementById('gc-body-' + id);
    const btn     = card.querySelector('.group-card-head');
    const isOpen  = card.classList.contains('is-open');

    if (isOpen) {
        card.classList.remove('is-open');
        btn.setAttribute('aria-expanded', 'false');
    } else {
        card.classList.add('is-open');
        btn.setAttribute('aria-expanded', 'true');
    }
}
</script>
@endpush
