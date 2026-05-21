@extends('layouts.coordinator', ['title' => 'Instructorías'])

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/coordinator/instructorias.css') }}">
@endpush

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">Instructorías</h1>
        <p class="page-sub">Toca un instructor para ver las sesiones que ha realizado</p>
    </div>
</div>

@if($instructors->isEmpty())
    <div class="empty-card">
        <i class="ti ti-clipboard-off" aria-hidden="true"></i>
        <p>No hay instructores con grupos asignados todavía.</p>
    </div>
@else
    <div class="inst-grid">
        @foreach($instructors as $instructor)
            @php
                $name = $instructor->user?->name ?? 'Sin nombre';
                $groups = $instructor->instructorAssignments->pluck('classGroup')->filter();
                $sessionsCount = (int) ($sessionsByInstructor[$instructor->id] ?? 0);
                $attendancesCount = (int) ($attendancesByInstructor[$instructor->id] ?? 0);
            @endphp
            <a href="{{ route('coordinator.instructorias.show', $instructor) }}" class="inst-card">
                <div class="inst-card-head">
                    <div class="inst-avatar">{{ strtoupper(mb_substr($name, 0, 2)) }}</div>
                    <div>
                        <div class="inst-name">{{ $name }}</div>
                        <div class="inst-major">{{ $instructor->major ?? '—' }}</div>
                    </div>
                </div>

                <div class="inst-groups">
                    @forelse($groups as $g)
                        <span class="inst-chip">{{ $g->name }}</span>
                    @empty
                        <span class="inst-no-groups">Sin grupos</span>
                    @endforelse
                </div>

                <div class="inst-stats">
                    <div class="inst-stat">
                        <div class="inst-stat-val">{{ $sessionsCount }}</div>
                        <div class="inst-stat-lbl">Sesiones</div>
                    </div>
                    <div class="inst-stat">
                        <div class="inst-stat-val">{{ $attendancesCount }}</div>
                        <div class="inst-stat-lbl">Asistencias</div>
                    </div>
                    <div class="inst-stat">
                        <div class="inst-stat-val">{{ $groups->count() }}</div>
                        <div class="inst-stat-lbl">Grupos</div>
                    </div>
                </div>

                <div class="inst-foot">Ver instructorías <i class="ti ti-arrow-right" aria-hidden="true"></i></div>
            </a>
        @endforeach
    </div>
@endif

@endsection
