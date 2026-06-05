@extends('layouts.coordinator', ['title' => 'Evaluaciones'])

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/coordinator/evaluations.css') }}">
@endpush

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">Evaluaciones de instructores</h1>
        <p class="page-sub">
            Solo aparecen aquí las instructorías que ya marcaste como
            <strong>Finalizadas</strong>. Para finalizar una instructoría entra
            en <em>Instructorías → instructor</em>.
        </p>
    </div>
</div>

@if(session('status'))
    <div class="ev-flash-success">
        <i class="ti ti-circle-check" aria-hidden="true"></i> {{ session('status') }}
    </div>
@endif

@if($assignments->isEmpty())
    <div class="ev-empty">
        <i class="ti ti-clipboard-check" aria-hidden="true"></i>
        <p>No tenés instructorías finalizadas todavía.</p>
        <a href="{{ route('coordinator.instructorias.index') }}" class="ev-btn-ghost">
            Ir a Instructorías
        </a>
    </div>
@else
    <div class="ev-list">
        @foreach($assignments as $assignment)
            @php
                $group = $assignment->classGroup;
                $instructor = $assignment->instructor;
                $instructorName = $instructor?->user?->name ?? 'Instructor';
                /** @var \App\Models\EvaluationResult|null $result */
                $result = $resultsByAssignment[$assignment->id] ?? null;
                $hasSubmitted = $result !== null;
                $initials = strtoupper(mb_substr($instructorName, 0, 2));
            @endphp
            <div class="ev-card">
                <div class="ev-card-body">
                    <div class="ev-avatar">{{ $initials }}</div>

                    <div class="ev-card-info">
                        <div class="ev-card-title">{{ $instructorName }}</div>
                        <div class="ev-card-group">
                            <i class="ti ti-school" aria-hidden="true"></i>
                            {{ $group?->name ?? '—' }}
                            <span class="ev-dot">·</span>
                            {{ $group?->semester ?? '—' }}
                            @if($assignment->schedule)
                                <span class="ev-dot">·</span>{{ $assignment->schedule }}
                            @endif
                        </div>

                        <div class="ev-card-status">
                            @if($hasSubmitted)
                                <span class="ev-pill ev-pill-done">
                                    <i class="ti ti-circle-check" aria-hidden="true"></i>
                                    Evaluación enviada
                                </span>
                                <span class="ev-card-sub">
                                    Promedio: <strong>{{ number_format((float) $result->total_score, 2) }} / 10</strong>
                                    · {{ $result->submitted_at?->translatedFormat('d M Y') }}
                                </span>
                            @else
                                <span class="ev-pill ev-pill-open">
                                    <i class="ti ti-edit" aria-hidden="true"></i>
                                    Pendiente de evaluar
                                </span>
                            @endif
                        </div>

                        {{-- Atajos a import por tipo (estudiantes / docente).
                             Los chips muestran cuántas evaluaciones ya están importadas. --}}
                        @php
                            $studentCount = $studentImports[$assignment->id] ?? 0;
                            $teacherCount = $teacherImports[$assignment->id] ?? 0;
                        @endphp
                        <div class="ev-card-imports">
                            <a href="{{ route('coordinator.evaluations.import.show', [$assignment, 'student']) }}"
                               class="ev-import-chip">
                                <i class="ti ti-users" aria-hidden="true"></i>
                                Estudiantes
                                <span class="ev-import-count">{{ $studentCount }}</span>
                            </a>
                            <a href="{{ route('coordinator.evaluations.import.show', [$assignment, 'teacher']) }}"
                               class="ev-import-chip">
                                <i class="ti ti-user" aria-hidden="true"></i>
                                Docente
                                <span class="ev-import-count">{{ $teacherCount }}</span>
                            </a>
                        </div>
                    </div>

                    <div class="ev-card-action">
                        @if($hasSubmitted)
                            <a href="{{ route('coordinator.evaluations.create', $assignment) }}" class="ev-btn-ghost">
                                <i class="ti ti-eye" aria-hidden="true"></i> Ver / editar
                            </a>
                        @else
                            <a href="{{ route('coordinator.evaluations.create', $assignment) }}" class="ev-btn-primary">
                                <i class="ti ti-edit" aria-hidden="true"></i> Evaluar
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

@endsection
