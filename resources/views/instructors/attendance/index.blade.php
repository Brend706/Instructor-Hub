@extends('layouts.instructor', ['title' => 'Asistencia'])

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/instructor/attendance.css') }}">
@endpush

@section('content')

<a href="{{ route('instructor.dashboard') }}" class="back-link">
    <i class="ti ti-arrow-left" aria-hidden="true"></i> Volver al dashboard
</a>

<div class="page-header">
    <div>
        <h1 class="page-title">Asistencia por instructoría</h1>
        <p class="page-sub">Selecciona una instructoría para ver qué estudiantes asistieron a cada sesión</p>
    </div>
</div>

@if($assignments->isEmpty())
    <div class="empty-card">
        <i class="ti ti-clipboard-off" aria-hidden="true"></i>
        <p>No tienes instructorías asignadas todavía.</p>
    </div>
@else
    <div class="att-grid">
        @foreach($assignments as $assignment)
            @php
                $group = $assignment->classGroup;
                $sessionsCount = (int) ($assignment->sessions_count ?? 0);
                $totalAttendances = (int) ($attendancesByAssignment[$assignment->id] ?? 0);
                $enrolled = (int) ($group?->students_count ?? 0);
                $avgPct = ($sessionsCount > 0 && $enrolled > 0)
                    ? round(($totalAttendances / ($sessionsCount * $enrolled)) * 100)
                    : 0;
            @endphp
            @if(!$group)
                @continue
            @endif
            <a href="{{ route('instructor.attendance.show', $assignment) }}" class="att-card">
                <div class="att-card-head">
                    <div class="att-card-icon"><i class="ti ti-clipboard-check" aria-hidden="true"></i></div>
                    <div>
                        <div class="att-card-title">{{ $group->name }}</div>
                        <div class="att-card-sub">{{ $group->professor }} · {{ $group->semester }}</div>
                    </div>
                </div>

                <div class="att-card-stats">
                    <div class="att-stat">
                        <div class="att-stat-val">{{ $sessionsCount }}</div>
                        <div class="att-stat-lbl">Sesiones</div>
                    </div>
                    <div class="att-stat">
                        <div class="att-stat-val">{{ $enrolled }}</div>
                        <div class="att-stat-lbl">Estudiantes</div>
                    </div>
                    <div class="att-stat">
                        <div class="att-stat-val">{{ $totalAttendances }}</div>
                        <div class="att-stat-lbl">Asistencias</div>
                    </div>
                    <div class="att-stat">
                        <div class="att-stat-val">{{ $avgPct }}%</div>
                        <div class="att-stat-lbl">Promedio</div>
                    </div>
                </div>

                <div class="att-card-foot">
                    Ver detalle <i class="ti ti-arrow-right" aria-hidden="true"></i>
                </div>
            </a>
        @endforeach
    </div>
@endif

@endsection
