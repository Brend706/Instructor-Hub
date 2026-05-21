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
@endphp

<div class="page-header">
    <div class="inst-head-info">
        <div class="inst-avatar-lg">{{ strtoupper(mb_substr($name, 0, 2)) }}</div>
        <div>
            <h1 class="page-title">{{ $name }}</h1>
            <p class="page-sub">
                {{ $instructor->major ?? '—' }} ·
                {{ $sessions->count() }} sesión(es) realizadas
            </p>
        </div>
    </div>
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
