@extends('layouts.instructor', ['title' => 'Evaluaciones'])

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/instructor/evaluations.css') }}">
@endpush

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">Mis evaluaciones</h1>
        <p class="page-sub">
            Cuando la coordinación finalice una instructoría, podrás completar tu autoevaluación.
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
        <i class="ti ti-clipboard-list" aria-hidden="true"></i>
        <p>Todavía no tenés instructorías asignadas.</p>
    </div>
@else
    <div class="ev-list">
        @foreach($assignments as $assignment)
            @php
                $group = $assignment->classGroup;
                $isFinalized = $assignment->status === 'Finalizado';
                /** @var \App\Models\EvaluationResult|null $result */
                $result = $resultsByAssignment[$assignment->id] ?? null;
                $hasSubmitted = $result !== null;
            @endphp
            <div class="ev-card">
                <div class="ev-card-body">
                    <div class="ev-card-info">
                        <div class="ev-card-title">{{ $group?->name ?? 'Grupo' }}</div>
                        <div class="ev-card-meta">
                            {{ $group?->semester ?? '—' }} ·
                            {{ $assignment->schedule ?? $group?->schedule ?? '—' }}
                            @if($assignment->modality) · {{ $assignment->modality }} @endif
                        </div>

                        <div class="ev-card-status">
                            @if(!$isFinalized)
                                <span class="ev-pill ev-pill-pending">
                                    <i class="ti ti-hourglass" aria-hidden="true"></i>
                                    Instructoría activa — aún no se puede evaluar
                                </span>
                            @elseif($hasSubmitted)
                                <span class="ev-pill ev-pill-done">
                                    <i class="ti ti-circle-check" aria-hidden="true"></i>
                                    Autoevaluación enviada
                                </span>
                                <span class="ev-card-sub">
                                    Promedio: <strong>{{ number_format((float) $result->total_score, 2) }} / 5</strong>
                                    · {{ $result->submitted_at?->translatedFormat('d M Y') }}
                                </span>
                            @else
                                <span class="ev-pill ev-pill-open">
                                    <i class="ti ti-edit" aria-hidden="true"></i>
                                    Lista para evaluar
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="ev-card-action">
                        @if($isFinalized && !$hasSubmitted)
                            <a href="{{ route('instructor.evaluations.create', $assignment) }}" class="ev-btn-primary">
                                <i class="ti ti-edit" aria-hidden="true"></i> Hacer autoevaluación
                            </a>
                        @elseif($isFinalized && $hasSubmitted)
                            <a href="{{ route('instructor.evaluations.create', $assignment) }}" class="ev-btn-ghost">
                                <i class="ti ti-eye" aria-hidden="true"></i> Ver / editar
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

@endsection
