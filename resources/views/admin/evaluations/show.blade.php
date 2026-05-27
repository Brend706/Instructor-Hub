@extends('layouts.admin', ['title' => 'Detalle de evaluaciones'])

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin/evaluations.css') }}">
@endpush

@section('content')

@php
    $instructorName = $assignment->instructor?->user?->name ?? 'Instructor';
@endphp

<a href="{{ route('admin.evaluations.index') }}" class="back-link">
    <i class="ti ti-arrow-left" aria-hidden="true"></i> Volver a evaluaciones
</a>

<div class="page-header">
    <div>
        <h1 class="page-title">{{ $instructorName }}</h1>
        <p class="page-sub">
            {{ $assignment->classGroup?->name ?? 'Grupo' }}
            @if($assignment->classGroup?->semester) · {{ $assignment->classGroup->semester }} @endif
            · Estado: <strong>{{ $assignment->status }}</strong>
        </p>
    </div>
    <a href="{{ route('admin.evaluations.export', $assignment) }}" class="ev-btn-primary">
        <i class="ti ti-file-spreadsheet" aria-hidden="true"></i>
        Exportar consolidado
    </a>
</div>

@if(session('status'))
    <div class="ev-flash-success">
        <i class="ti ti-circle-check" aria-hidden="true"></i> {{ session('status') }}
    </div>
@endif

{{-- ── Tarjetas de resumen por tipo ───────────────────────── --}}
<div class="ev-summary-grid">
    @foreach($types as $type)
        @php $m = $metricsByType[$type->id] ?? ['count' => 0, 'avg' => null, 'pending' => 0]; @endphp
        <div class="ev-summary-card">
            <div class="ev-summary-label">{{ $type->name }}</div>
            <div class="ev-summary-value">
                @if($m['avg'] !== null)
                    {{ number_format($m['avg'], 2) }} <small>/ 5</small>
                @else
                    <span class="ev-muted">—</span>
                @endif
            </div>
            <div class="ev-summary-sub">
                {{ $m['count'] }} evaluación(es)
                @if($m['pending'] > 0)
                    · <span class="ev-pending-text">{{ $m['pending'] }} por revisar</span>
                @endif
            </div>
        </div>
    @endforeach
    <div class="ev-summary-card ev-summary-overall">
        <div class="ev-summary-label">Promedio general</div>
        <div class="ev-summary-value">
            @if($overallAvg !== null)
                {{ number_format($overallAvg, 2) }} <small>/ 5</small>
            @else
                <span class="ev-muted">—</span>
            @endif
        </div>
        <div class="ev-summary-sub">Combinado de todos los tipos</div>
    </div>
</div>

{{-- ── Bloques por tipo con respuestas detalladas ──────────── --}}
@foreach($types as $type)
    @php $list = $resultsByType[$type->id] ?? collect(); @endphp
    @if($list->isEmpty())
        @continue
    @endif

    <div class="ev-type-section">
        <div class="ev-type-head">
            <h2 class="ev-type-title">
                <i class="ti ti-clipboard-list" aria-hidden="true"></i>
                {{ $type->name }}
            </h2>
            <span class="ev-type-meta">
                {{ $list->count() }} evaluación(es)
            </span>
        </div>

        @foreach($list as $result)
            @php $answers = $result->answers->keyBy('question_template_id'); @endphp
            <details class="ev-result" {{ $loop->first ? 'open' : '' }}>
                <summary class="ev-result-summary">
                    <div>
                        <div class="ev-result-title">
                            @if($result->evaluator)
                                {{ $result->evaluator->name }}
                            @elseif($result->source === 'csv_import')
                                Importado desde Excel
                            @elseif($result->source === 'forms')
                                Importado desde Forms
                            @else
                                Sin evaluador identificado
                            @endif
                            @if($result->reviewed_by_admin)
                                <span class="ev-tag ev-tag-reviewed"><i class="ti ti-check"></i> Revisado</span>
                            @else
                                <span class="ev-tag ev-tag-pending"><i class="ti ti-clock"></i> Por revisar</span>
                            @endif
                        </div>
                        <div class="ev-result-sub">
                            {{ $result->submitted_at?->translatedFormat('d M Y H:i') ?? '—' }}
                            @if($result->total_score !== null)
                                · Promedio: <strong>{{ number_format((float) $result->total_score, 2) }}</strong> / 5
                            @endif
                        </div>
                    </div>
                    <i class="ti ti-chevron-down ev-chevron" aria-hidden="true"></i>
                </summary>

                <div class="ev-result-body">
                    <ol class="ev-answers">
                        @foreach($type->questions as $q)
                            @php $a = $answers[$q->id] ?? null; @endphp
                            <li>
                                <div class="ev-answer-q">{{ $q->question_text }}</div>
                                <div class="ev-answer-a">
                                    @if(! $a)
                                        <span class="ev-muted">Sin respuesta</span>
                                    @elseif($q->question_type === 'score')
                                        <span class="ev-score-pill">
                                            {{ $a->score_value !== null ? rtrim(rtrim((string) $a->score_value, '0'), '.') : '—' }}
                                            <small>/ {{ $q->max_score ?? 5 }}</small>
                                        </span>
                                    @else
                                        @if($a->text_value)
                                            <span class="ev-text-answer">{{ $a->text_value }}</span>
                                        @else
                                            <span class="ev-muted">Sin comentario</span>
                                        @endif
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>

                    <form method="POST" action="{{ route('admin.evaluations.results.review', $result) }}" class="ev-result-actions">
                        @csrf
                        <button type="submit" class="ev-btn-ghost ev-btn-sm">
                            @if($result->reviewed_by_admin)
                                <i class="ti ti-rotate"></i> Marcar como pendiente
                            @else
                                <i class="ti ti-check"></i> Marcar como revisado
                            @endif
                        </button>
                    </form>
                </div>
            </details>
        @endforeach
    </div>
@endforeach

@endsection
