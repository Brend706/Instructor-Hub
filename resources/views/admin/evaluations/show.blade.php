@extends('layouts.admin', ['title' => 'Detalle de evaluaciones'])

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin/evaluations.css') }}">
@endpush

@section('content')

@php
    $instructorName = $assignment->instructor?->user?->name ?? 'Instructor';

    // Iniciales para el avatar
    $initials = collect(explode(' ', $instructorName))
        ->filter()->take(2)->map(fn($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');

    // Icono por slug de tipo
    $typeIcons = [
        \App\Models\EvaluationType::SELF        => 'ti-user',
        \App\Models\EvaluationType::TEACHER     => 'ti-school',
        \App\Models\EvaluationType::COORDINATOR => 'ti-user-check',
        \App\Models\EvaluationType::STUDENT     => 'ti-users',
    ];

    // Solo mostrar tipos que tengan resultados O que sean el tipo estudiante activo
    $studentType   = $types->firstWhere('slug', \App\Models\EvaluationType::STUDENT);
    $studentTypeId = $studentType?->id;
    $studentResults = $studentTypeId ? ($resultsByType[$studentTypeId] ?? collect()) : collect();

    // Agregación por pregunta para el tab de estudiantes
    $studentAgg      = [];   // [question_id] => array
    $studentComments = collect();
    if ($studentType) {
        foreach ($studentType->questions as $q) {
            $answers = $studentResults->flatMap(
                fn($r) => $r->answers->filter(fn($a) => $a->question_template_id === $q->id)
            );
            if ($q->question_type === 'score') {
                $sAnswers = $answers->whereNotNull('score_value');
                $maxScore = $q->max_score ?? 5;
                $avg      = $sAnswers->isNotEmpty()
                    ? round((float) $sAnswers->avg('score_value'), 1)
                    : null;
                $total    = $sAnswers->count();
                $dist     = [];
                for ($s = $maxScore; $s >= 1; $s--) {
                    $dist[$s] = $sAnswers->filter(
                        fn($a) => (int) round((float) $a->score_value) === $s
                    )->count();
                }
                $studentAgg[$q->id] = [
                    'type'     => 'score',
                    'question' => $q->question_text,
                    'avg'      => $avg,
                    'dist'     => $dist,
                    'maxScore' => $maxScore,
                    'total'    => $total,
                ];
            } else {
                $texts = $answers->whereNotNull('text_value')
                    ->pluck('text_value')->filter()->values();
                $studentComments = $studentComments->merge($texts);
                $studentAgg[$q->id] = ['type' => 'text'];
            }
        }
    }
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

{{-- ── Tarjetas de resumen ─────────────────────────────────── --}}
<div class="ev-summary-grid">
    @foreach($types as $type)
        @php $m = $metricsByType[$type->id] ?? ['count' => 0, 'avg' => null, 'pending' => 0]; @endphp
        <div class="ev-summary-card {{ $type->slug === \App\Models\EvaluationType::STUDENT ? 'ev-accent-card' : '' }}">
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

{{-- ── Barra de tabs ───────────────────────────────────────── --}}
<div class="ev-tabs-bar" role="tablist">
    @foreach($types as $i => $type)
        @php
            $count = $metricsByType[$type->id]['count'] ?? 0;
            $icon  = $typeIcons[$type->slug] ?? 'ti-clipboard-list';
        @endphp
        <button
            class="ev-tab-btn {{ $i === 0 ? 'active' : '' }}"
            onclick="evSwitchTab('{{ $type->slug }}', this)"
            role="tab"
            aria-selected="{{ $i === 0 ? 'true' : 'false' }}"
            aria-controls="ev-tab-{{ $type->slug }}"
        >
            <i class="ti {{ $icon }}" aria-hidden="true"></i>
            {{ $type->name }}
            <span class="ev-tab-count">{{ $count }}</span>
        </button>
    @endforeach
</div>

{{-- ── Paneles de tab ──────────────────────────────────────── --}}
@foreach($types as $i => $type)
    @php
        $list    = $resultsByType[$type->id] ?? collect();
        $m       = $metricsByType[$type->id];
        $isFirst = ($i === 0);
        $isStudent = $type->slug === \App\Models\EvaluationType::STUDENT;
    @endphp

    <div
        class="ev-tab-panel {{ $isFirst ? 'active' : '' }}"
        id="ev-tab-{{ $type->slug }}"
        role="tabpanel"
    >
        {{-- Stat row --}}
        <div class="ev-tab-stat-row">
            <div class="ev-tab-stat-item">
                <div class="ev-tab-stat-val {{ $isStudent ? 'accent' : '' }}">
                    {{ $m['avg'] !== null ? number_format($m['avg'], 2) : '—' }}
                </div>
                <div class="ev-tab-stat-label">Promedio</div>
            </div>
            <div class="ev-tab-stat-divider"></div>
            <div class="ev-tab-stat-item">
                <div class="ev-tab-stat-val">{{ $m['count'] }}</div>
                <div class="ev-tab-stat-label">
                    {{ $isStudent ? 'Estudiantes evaluaron' : 'Evaluación(es)' }}
                </div>
            </div>
            @if(! $isStudent)
                <div class="ev-tab-stat-divider"></div>
                <div class="ev-tab-stat-item">
                    <div class="ev-tab-stat-val {{ $m['pending'] > 0 ? 'warn' : 'success' }}">
                        {{ $m['pending'] }}
                    </div>
                    <div class="ev-tab-stat-label">Por revisar</div>
                </div>
            @endif
        </div>

        @if($list->isEmpty())
            {{-- Empty state --}}
            <div class="ev-tab-empty">
                <i class="ti ti-inbox" aria-hidden="true"></i>
                Sin evaluaciones registradas para este tipo.
            </div>

        @elseif($isStudent)
            {{-- ─── Tab especial: Estudiantes ─────────────────── --}}

            {{-- Resultados por pregunta (agregado) --}}
            @if(! empty($studentAgg))
                <div class="ev-agg-section">
                    <div class="ev-agg-header">
                        <div class="ev-agg-title">
                            <i class="ti ti-chart-bar" aria-hidden="true"></i>
                            Resultados por pregunta
                        </div>
                        <span style="font-size:11px;color:var(--accent)">{{ $list->count() }} respuesta(s)</span>
                    </div>
                    <div class="ev-agg-body">
                        @foreach($type->questions as $q)
                            @php $agg = $studentAgg[$q->id] ?? null; @endphp
                            @if(! $agg || $agg['type'] !== 'score') @continue @endif
                            <div class="ev-agg-question">
                                <div class="ev-agg-q-text">{{ $loop->iteration }}. {{ $q->question_text }}</div>
                                @if($agg['avg'] !== null)
                                    @php $pct = round(($agg['avg'] / $agg['maxScore']) * 100); @endphp
                                    <div class="ev-agg-q-avg">
                                        <div class="ev-agg-avg-val">
                                            {{ $agg['avg'] }}
                                            <small>/ {{ $agg['maxScore'] }}</small>
                                        </div>
                                        <div style="flex:1">
                                            <div class="ev-agg-bar-wrap">
                                                <div class="ev-agg-bar" style="width:{{ $pct }}%"></div>
                                            </div>
                                        </div>
                                        <div style="font-size:11px;color:var(--text-muted)">{{ $pct }}%</div>
                                    </div>
                                    <div class="ev-dist-grid">
                                        @foreach($agg['dist'] as $star => $cnt)
                                            @php
                                                $barPct = $agg['total'] > 0
                                                    ? round(($cnt / $agg['total']) * 100)
                                                    : 0;
                                            @endphp
                                            <div class="ev-dist-row">
                                                <span class="ev-dist-star">{{ $star }}★</span>
                                                <div class="ev-dist-bar-wrap">
                                                    <div class="ev-dist-bar" style="width:{{ $barPct }}%"></div>
                                                </div>
                                                <span class="ev-dist-count">{{ $cnt }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="ev-muted">Sin datos numéricos</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Comentarios de estudiantes --}}
            @if($studentComments->isNotEmpty())
                <div class="ev-comments-section">
                    <div class="ev-comments-header">
                        <div class="ev-comments-title">
                            <i class="ti ti-message-circle" style="color:var(--accent)" aria-hidden="true"></i>
                            Comentarios de estudiantes
                        </div>
                        <span style="font-size:11px;color:var(--text-muted)">
                            {{ $studentComments->count() }} comentario(s)
                        </span>
                    </div>
                    <div class="ev-comments-body" id="ev-comments-list">
                        @foreach($studentComments as $idx => $comment)
                            <div class="ev-comment-item {{ $idx >= 4 ? 'ev-comments-hidden' : '' }}">
                                "{{ $comment }}"
                            </div>
                        @endforeach
                        @if($studentComments->count() > 4)
                            <button class="ev-see-more" onclick="evShowAllComments(this)">
                                Ver los {{ $studentComments->count() - 4 }} comentarios restantes ↓
                            </button>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Veredicto del administrador --}}
            <div class="ev-verdict-card">
                <div class="ev-verdict-title">
                    <i class="ti ti-gavel" aria-hidden="true"></i>
                    Veredicto del administrador
                </div>
                <form method="POST" action="{{ route('admin.evaluations.verdict', $assignment) }}">
                    @csrf
                    <div class="ev-verdict-field">
                        <label class="ev-verdict-label" for="ev-verdict-textarea">
                            Observaciones generales sobre las evaluaciones de estudiantes
                        </label>
                        <textarea
                            id="ev-verdict-textarea"
                            name="verdict"
                            class="ev-verdict-textarea"
                            placeholder="Escribe tu análisis y conclusiones..."
                        >{{ $assignment->admin_student_verdict }}</textarea>
                    </div>
                    <div class="ev-verdict-footer">
                        <button type="submit" class="ev-btn-accent">
                            <i class="ti ti-device-floppy" aria-hidden="true"></i>
                            Guardar veredicto
                        </button>
                    </div>
                </form>
            </div>

        @else
            {{-- ─── Tab normal (autoevaluación, docente, coordinador) ─ --}}
            @foreach($list as $result)
                @php
                    $answers     = $result->answers->keyBy('question_template_id');
                    $evalName    = $result->evaluator?->name
                        ?? ($result->source === 'csv_import' ? 'Importado (Excel)'
                            : ($result->source === 'forms'  ? 'Importado (Forms)'
                                : 'Sin evaluador'));
                    $evalInitials = collect(explode(' ', $evalName))
                        ->filter()->take(2)->map(fn($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
                    $maxScore = $type->questions->where('question_type', 'score')->first()?->max_score ?? 5;
                @endphp

                <details class="ev-result" {{ $loop->first ? 'open' : '' }}>
                    <summary class="ev-result-summary">
                        <div class="ev-result-left">
                            <div class="ev-result-av {{ $result->reviewed_by_admin ? 'alt' : '' }}">
                                {{ $evalInitials ?: '?' }}
                            </div>
                            <div>
                                <div class="ev-result-title">
                                    {{ $evalName }}
                                    @if($result->reviewed_by_admin)
                                        <span class="ev-tag ev-tag-reviewed">
                                            <i class="ti ti-check"></i> Revisado
                                        </span>
                                    @else
                                        <span class="ev-tag ev-tag-pending">
                                            <i class="ti ti-clock"></i> Por revisar
                                        </span>
                                    @endif
                                </div>
                                <div class="ev-result-sub">
                                    {{ $result->submitted_at?->translatedFormat('d M Y · H:i') ?? '—' }}
                                </div>
                            </div>
                        </div>
                        <div class="ev-result-right">
                            @if($result->total_score !== null)
                                <div class="ev-result-score">
                                    {{ number_format((float) $result->total_score, 2) }}
                                    <small>/ {{ $maxScore }}</small>
                                </div>
                            @endif
                            <i class="ti ti-chevron-down ev-chevron" aria-hidden="true"></i>
                        </div>
                    </summary>

                    <div class="ev-result-body">
                        <ol class="ev-answers-v2">
                            @foreach($type->questions as $q)
                                @php $a = $answers[$q->id] ?? null; @endphp
                                <li class="ev-answer-item">
                                    <div class="ev-answer-q">{{ $loop->iteration }}. {{ $q->question_text }}</div>
                                    <div class="ev-answer-row">
                                        @if(! $a)
                                            <span class="ev-muted">Sin respuesta</span>
                                        @elseif($q->question_type === 'score')
                                            @php
                                                $val    = $a->score_value !== null ? (float) $a->score_value : null;
                                                $maxQ   = $q->max_score ?? 5;
                                                $barPct = $val !== null ? round(($val / $maxQ) * 100) : 0;
                                            @endphp
                                            <span class="ev-score-pill-v2">
                                                {{ $val !== null ? rtrim(rtrim(number_format($val, 2), '0'), '.') : '—' }}
                                                <small>/ {{ $maxQ }}</small>
                                            </span>
                                            <div class="ev-score-bar-wrap">
                                                <div class="ev-score-bar" style="width:{{ $barPct }}%"></div>
                                            </div>
                                        @else
                                            @if($a->text_value)
                                                <span class="ev-text-answer-v2">"{{ $a->text_value }}"</span>
                                            @else
                                                <span class="ev-muted">Sin comentario</span>
                                            @endif
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ol>

                        <form method="POST"
                              action="{{ route('admin.evaluations.results.review', $result) }}"
                              class="ev-result-actions">
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
        @endif
    </div>
@endforeach

@endsection

@push('scripts')
<script>
function evSwitchTab(slug, btn) {
    document.querySelectorAll('.ev-tab-btn').forEach(b => {
        b.classList.remove('active');
        b.setAttribute('aria-selected', 'false');
    });
    document.querySelectorAll('.ev-tab-panel').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    btn.setAttribute('aria-selected', 'true');
    document.getElementById('ev-tab-' + slug).classList.add('active');
}

function evShowAllComments(btn) {
    document.querySelectorAll('.ev-comments-hidden').forEach(el => el.classList.remove('ev-comments-hidden'));
    btn.remove();
}
</script>
@endpush
