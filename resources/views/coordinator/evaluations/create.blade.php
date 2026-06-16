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

@if($isEditing && isset($existingResult))
@php
    $prevTotal   = $existingResult->total_score !== null ? number_format((float)$existingResult->total_score, 1) : null;
    $prevDate    = $existingResult->submitted_at
                    ? \Carbon\Carbon::parse($existingResult->submitted_at)->translatedFormat('j \d\e F Y, H:i')
                    : null;
    $scoreColor  = $prevTotal !== null
        ? (((float)$prevTotal) >= 8 ? '#166534' : ((float)$prevTotal >= 6 ? '#854D0E' : '#B91C1C'))
        : '#6B7280';
    $scoreBg     = $prevTotal !== null
        ? (((float)$prevTotal) >= 8 ? '#F0FDF4' : ((float)$prevTotal >= 6 ? '#FFFBEB' : '#FEF2F2'))
        : '#F3F4F6';
@endphp
<div style="
    background:#EEF2FF;
    border:1.5px solid #C7D2FE;
    border-radius:10px;
    padding:14px 18px;
    margin-bottom:18px;
    display:flex;
    align-items:center;
    gap:14px;
">
    <div style="flex-shrink:0;width:38px;height:38px;border-radius:50%;background:#7F77DD;display:flex;align-items:center;justify-content:center">
        <i class="ti ti-clipboard-check" style="color:#fff;font-size:18px"></i>
    </div>
    <div style="flex:1;min-width:0">
        <div style="font-weight:600;font-size:13.5px;color:#3730A3;margin-bottom:2px">
            Estás editando una evaluación existente
        </div>
        <div style="font-size:12.5px;color:#4338CA">
            @if($prevDate) Enviada el {{ $prevDate }}.@endif
            Los campos ya vienen cargados con las respuestas anteriores.
        </div>
    </div>
    @if($prevTotal !== null)
    <div style="flex-shrink:0;text-align:center">
        <div style="font-size:10px;color:#6B7280;margin-bottom:2px;text-transform:uppercase;letter-spacing:.5px">Nota anterior</div>
        <span style="
            display:inline-block;
            padding:4px 12px;
            border-radius:20px;
            font-weight:700;
            font-size:15px;
            background:{{ $scoreBg }};
            color:{{ $scoreColor }};
        ">{{ $prevTotal }}</span>
    </div>
    @endif
</div>
@endif

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
