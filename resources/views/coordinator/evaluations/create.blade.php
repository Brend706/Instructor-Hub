@extends('layouts.coordinator', ['title' => 'Evaluar instructor'])

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/coordinator/evaluations.css') }}">
@endpush

@section('content')

@php
    $instructorName = $assignment->instructor?->user?->name ?? 'Instructor';
@endphp

<a href="{{ route('coordinator.evaluations.index') }}" class="back-link">
    <i class="ti ti-arrow-left" aria-hidden="true"></i> Volver a evaluaciones
</a>

<div class="page-header">
    <div>
        <h1 class="page-title">
            {{ $isEditing ? 'Editar evaluación' : 'Evaluar instructor' }}
        </h1>
        <p class="page-sub">
            <strong>{{ $instructorName }}</strong>
            · {{ $assignment->classGroup?->name ?? 'Grupo' }}
            @if($assignment->classGroup?->semester) · {{ $assignment->classGroup->semester }} @endif
        </p>
    </div>
</div>

@if($errors->any())
    <div class="ev-flash-error">
        <i class="ti ti-alert-triangle" aria-hidden="true"></i>
        <div>
            <strong>Revisá los siguientes campos:</strong>
            <ul>
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

<form method="POST" action="{{ route('coordinator.evaluations.store', $assignment) }}" class="ev-form">
    @csrf

    <div class="ev-form-card">
        <p class="ev-help">
            Calificá del 1 al 10, donde
            <strong>1 = muy bajo</strong> y <strong>10 = excelente</strong>.
            Los comentarios al final son opcionales pero recomendados.
        </p>

        @php $scoreCounter = 0; @endphp

        @foreach($questions as $q)
            @php
                $prev = $previousAnswers[$q->id] ?? null;
                $prevScore = old("answers.$q->id.score", $prev['score'] ?? null);
                $prevText  = old("answers.$q->id.text",  $prev['text']  ?? null);
            @endphp

            <div class="ev-question">
                @if($q->question_type === 'score')
                    @php $scoreCounter++; @endphp
                    <div class="ev-question-label">
                        <span class="ev-question-num">{{ $scoreCounter }}.</span>
                        {{ $q->question_text }}
                    </div>
                    <div class="ev-likert" role="radiogroup" aria-label="{{ $q->question_text }}">
                        @for($n = 1; $n <= ($q->max_score ?? 10); $n++)
                            <label class="ev-likert-opt">
                                <input type="radio"
                                       name="answers[{{ $q->id }}][score]"
                                       value="{{ $n }}"
                                       {{ (string) $prevScore === (string) $n ? 'checked' : '' }}>
                                <span>{{ $n }}</span>
                            </label>
                        @endfor
                    </div>
                @else
                    <div class="ev-question-label">{{ $q->question_text }}</div>
                    <textarea name="answers[{{ $q->id }}][text]"
                              class="ev-textarea"
                              rows="3"
                              maxlength="2000"
                              placeholder="Tu respuesta (opcional)">{{ $prevText }}</textarea>
                @endif
            </div>
        @endforeach
    </div>

    <div class="ev-form-actions">
        <a href="{{ route('coordinator.evaluations.index') }}" class="ev-btn-ghost">Cancelar</a>
        <button type="submit" class="ev-btn-primary">
            <i class="ti ti-send" aria-hidden="true"></i>
            {{ $isEditing ? 'Actualizar evaluación' : 'Guardar evaluación' }}
        </button>
    </div>
</form>

@endsection
