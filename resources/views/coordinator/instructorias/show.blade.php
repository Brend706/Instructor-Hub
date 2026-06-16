@extends('layouts.coordinator', ['title' => 'Instructorías de ' . ($instructor->user?->name ?? '')])

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/coordinator/instructorias.css') }}">
@endpush

@section('content')

<a href="{{ route('coordinator.instructorias.index') }}" class="back-link">
    <i class="ti ti-arrow-left" aria-hidden="true"></i> Volver a instructorías
</a>

@php
    $name = $instructor->user?->name ?? 'Instructor';

    // Total de horas de instructoría: suma de duraciones de las sesiones
    // cerradas (start_time + end_time). Las abiertas se ignoran.
    $totalMinutes = 0;
    foreach ($sessions as $s) {
        if ($s->start_time && $s->end_time) {
            $a = \Illuminate\Support\Carbon::parse($s->start_time);
            $b = \Illuminate\Support\Carbon::parse($s->end_time);
            $totalMinutes += (int) round(abs($b->diffInSeconds($a)) / 60);
        }
    }
    if ($totalMinutes <= 0) {
        $totalHoursLabel = '0 h';
    } elseif ($totalMinutes < 60) {
        $totalHoursLabel = $totalMinutes . ' min';
    } else {
        $h = intdiv($totalMinutes, 60);
        $m = $totalMinutes % 60;
        $totalHoursLabel = $m > 0 ? "{$h} h {$m} min" : "{$h} h";
    }
@endphp

<div class="page-header">
    <div class="inst-head-info">
        <div class="inst-avatar-lg">{{ strtoupper(mb_substr($name, 0, 2)) }}</div>
        <div>
            <h1 class="page-title">{{ $name }}</h1>
            <p class="page-sub">
                {{ $instructor->major ?? '—' }} ·
                {{ $sessions->count() }} sesión(es) realizadas ·
                <strong title="Suma de duraciones de sesiones cerradas">
                    <i class="ti ti-clock" aria-hidden="true"></i>
                    {{ $totalHoursLabel }} totales
                </strong>
            </p>
        </div>
    </div>
    {{-- Botón "Exportar Excel": dispara la descarga del histórico de sesiones
         del instructor. Solo se muestra si hay al menos una sesión registrada. --}}
    @if(!$sessions->isEmpty())
        <a href="{{ route('coordinator.instructorias.export', $instructor) }}" class="btn-export-excel">
            <i class="ti ti-file-spreadsheet" aria-hidden="true"></i>
            <span>Exportar Excel</span>
        </a>
    @endif
</div>

{{-- ─── Flash de éxito/error luego de finalizar o reactivar una instructoría ─── --}}
@if(session('status'))
    <div class="flash-success">
        <i class="ti ti-circle-check" aria-hidden="true"></i> {{ session('status') }}
    </div>
@endif

{{-- ─── Estado de las instructorías (assignments) ─────────────────────────────
     Cada fila = un grupo asignado a este instructor. El estado controla si
     se pueden completar las evaluaciones del módulo. Al pulsar "Finalizar"
     se cambia a "Finalizado" y se habilitan las evaluaciones. --}}
<div class="assignments-card">
    <div class="assignments-head">
        <div>
            <div class="assignments-title">Instructorías a tu cargo</div>
            <div class="assignments-sub">Finalizar una instructoría habilita sus evaluaciones (autoeval, coordinador, etc.)</div>
        </div>
    </div>
    @if($instructor->instructorAssignments->isEmpty())
        <div class="assignments-empty">Este instructor todavía no tiene grupos asignados.</div>
    @else
        <div class="assignments-list">
            @foreach($instructor->instructorAssignments as $assignment)
                @php
                    $g = $assignment->classGroup;
                    $isFinalized = $assignment->status === 'Finalizado';
                @endphp
                <div class="assignment-row">
                    <div class="assignment-info">
                        <div class="assignment-name">{{ $g?->name ?? 'Grupo sin nombre' }}</div>
                        <div class="assignment-meta">
                            {{ $g?->semester ?? '—' }} · {{ $assignment->schedule ?? $g?->schedule ?? '—' }}
                            @if($assignment->modality) · {{ $assignment->modality }} @endif
                        </div>
                    </div>
                    <div class="assignment-status">
                        @if($isFinalized)
                            <span class="badge badge-closed">Finalizado</span>
                        @else
                            <span class="badge badge-open">Activo</span>
                        @endif
                    </div>
                    <div class="assignment-actions">
                        @if($isFinalized)
                            {{-- Atajo a la evaluación del coordinador de ESTA instructoría --}}
                            <a href="{{ route('coordinator.evaluations.create', $assignment) }}"
                               class="btn-evaluate">
                                <i class="ti ti-star" aria-hidden="true"></i> Evaluar
                            </a>
                            <form method="POST"
                                  action="{{ route('coordinator.instructorias.assignment.reactivate', [$instructor, $assignment]) }}"
                                  onsubmit="return confirm('¿Reactivar esta instructoría? Las evaluaciones ya enviadas se conservarán.');">
                                @csrf
                                <button type="submit" class="btn-reactivate">
                                    <i class="ti ti-rotate" aria-hidden="true"></i> Reactivar
                                </button>
                            </form>
                        @else
                            <form method="POST"
                                  action="{{ route('coordinator.instructorias.assignment.finalize', [$instructor, $assignment]) }}"
                                  onsubmit="return confirm('¿Finalizar esta instructoría? Se habilitarán las evaluaciones.');">
                                @csrf
                                <button type="submit" class="btn-finalize">
                                    <i class="ti ti-flag-check" aria-hidden="true"></i> Finalizar instructoría
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

@if($sessions->isEmpty())
    <div class="empty-card">
        <i class="ti ti-calendar-off" aria-hidden="true"></i>
        <p>Este instructor aún no ha realizado ninguna sesión.</p>
    </div>
@else
    <div class="table-card">
        <div class="table-wrap">
            <table class="sessions-table">
                <thead>
                    <tr>
                        <th>Grupo</th>
                        <th>Fecha</th>
                        <th>Hora inicio</th>
                        <th>Hora fin</th>
                        <th>Duración</th>
                        <th>Asistentes</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sessions as $session)
                        @php
                            $group = $session->instructorAssignment?->classGroup;
                            $start = $session->start_time ? \Illuminate\Support\Carbon::parse($session->start_time) : null;
                            $end   = $session->end_time   ? \Illuminate\Support\Carbon::parse($session->end_time)   : null;

                            // Duración como texto corto: "5 min", "1 h 20 min", o "—" si no terminó.
                            $durationLabel = '—';
                            if ($start && $end) {
                                $totalMinutes = (int) round(abs($end->diffInSeconds($start)) / 60);
                                if ($totalMinutes < 1) {
                                    $durationLabel = '< 1 min';
                                } elseif ($totalMinutes < 60) {
                                    $durationLabel = $totalMinutes . ' min';
                                } else {
                                    $hours = intdiv($totalMinutes, 60);
                                    $mins  = $totalMinutes % 60;
                                    $durationLabel = $mins > 0
                                        ? "{$hours} h {$mins} min"
                                        : "{$hours} h";
                                }
                            }
                        @endphp
                        <tr>
                            <td>
                                <div class="td-main">{{ $group?->name ?? '—' }}</div>
                                <div class="td-sub">{{ $group?->semester ?? '' }}</div>
                            </td>
                            <td>
                                {{ optional($session->date)->translatedFormat('d M Y') ?? '—' }}
                            </td>
                            <td>
                                <span class="time-pill time-pill-start">
                                    <i class="ti ti-clock-play" aria-hidden="true"></i>
                                    {{ $start?->format('H:i') ?? '—' }}
                                </span>
                            </td>
                            <td>
                                @if($end)
                                    <span class="time-pill time-pill-end">
                                        <i class="ti ti-clock-stop" aria-hidden="true"></i>
                                        {{ $end->format('H:i') }}
                                    </span>
                                @else
                                    <span class="time-pill time-pill-pending">En curso</span>
                                @endif
                            </td>
                            <td>{{ $durationLabel }}</td>
                            <td>
                                <span class="att-count">
                                    <i class="ti ti-users" aria-hidden="true"></i>
                                    {{ $session->attendees_count }}
                                </span>
                            </td>
                            <td>
                                @if($session->is_open)
                                    <span class="badge badge-open">Abierta</span>
                                @else
                                    <span class="badge badge-closed">Finalizada</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

@endsection
