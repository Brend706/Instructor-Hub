@extends('layouts.admin', ['title' => 'Evaluaciones'])

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin/evaluations.css') }}">
@endpush

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">Evaluaciones — vista global</h1>
        <p class="page-sub">
            Todas las instructorías con evaluaciones registradas. Podés ver el detalle de
            cada una, revisar respuestas individuales o descargar un consolidado en Excel.
        </p>
    </div>
    <div class="ev-header-actions">
        <a href="{{ route('admin.evaluations.questions.index', 'self') }}" class="ev-btn-ghost">
            <i class="ti ti-list-details" aria-hidden="true"></i> Plantillas de preguntas
        </a>
        <a href="{{ route('admin.evaluations.by_instructor') }}" class="ev-btn-ghost">
            <i class="ti ti-chart-bar" aria-hidden="true"></i> Reporte por instructor
        </a>
    </div>
</div>

@if(session('status'))
    <div class="ev-flash-success">
        <i class="ti ti-circle-check" aria-hidden="true"></i> {{ session('status') }}
    </div>
@endif

{{-- ── Filtros ────────────────────────────────────────────── --}}
<form method="GET" action="{{ route('admin.evaluations.index') }}" class="ev-filters">
    <div class="ev-filter">
        <label for="instructor_id">Instructor</label>
        <select name="instructor_id" id="instructor_id">
            <option value="">Todos</option>
            @foreach($instructors as $instructor)
                <option value="{{ $instructor->id }}"
                    {{ ($filters['instructor_id'] ?? null) == $instructor->id ? 'selected' : '' }}>
                    {{ $instructor->user?->name ?? '—' }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="ev-filter">
        <label for="semester">Ciclo</label>
        <select name="semester" id="semester">
            <option value="">Todos</option>
            @foreach($semesters as $sem)
                <option value="{{ $sem }}" {{ ($filters['semester'] ?? null) === $sem ? 'selected' : '' }}>
                    {{ $sem }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="ev-filter ev-filter-actions">
        <button type="submit" class="ev-btn-primary">
            <i class="ti ti-filter" aria-hidden="true"></i> Filtrar
        </button>
        @if($filters['instructor_id'] || $filters['semester'])
            <a href="{{ route('admin.evaluations.index') }}" class="ev-btn-ghost">Limpiar</a>
        @endif
    </div>
</form>

@if($assignments->isEmpty())
    <div class="ev-empty">
        <i class="ti ti-clipboard-off" aria-hidden="true"></i>
        <p>No hay evaluaciones registradas con esos filtros.</p>
    </div>
@else
    <div class="ev-table-card">
        <table class="ev-table">
            <thead>
                <tr>
                    <th>Instructor</th>
                    <th>Grupo / Ciclo</th>
                    <th>Tipos</th>
                    <th class="ev-text-center">Total</th>
                    <th class="ev-text-center">Promedio</th>
                    <th class="ev-text-center">Por revisar</th>
                    <th class="ev-text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($assignments as $assignment)
                    @php
                        $m = $metrics[$assignment->id] ?? ['count' => 0, 'avg' => null, 'pending_review' => 0];
                        $counts = $byTypeCounts[$assignment->id] ?? [];
                    @endphp
                    <tr>
                        <td>
                            <div class="ev-td-name">{{ $assignment->instructor?->user?->name ?? '—' }}</div>
                            <div class="ev-td-sub">
                                Estado: <strong>{{ $assignment->status ?? '—' }}</strong>
                            </div>
                        </td>
                        <td>
                            <div class="ev-td-name">{{ $assignment->classGroup?->name ?? '—' }}</div>
                            <div class="ev-td-sub">{{ $assignment->classGroup?->semester ?? '—' }}</div>
                        </td>
                        <td>
                            <div class="ev-types-row">
                                @foreach(['self' => 'Self', 'coordinator' => 'Coord', 'student' => 'Est.', 'teacher' => 'Doc.'] as $slug => $label)
                                    @php $n = $counts[$slug] ?? 0; @endphp
                                    <span class="ev-type-pill {{ $n > 0 ? 'is-on' : 'is-off' }}">
                                        {{ $label }} <strong>{{ $n }}</strong>
                                    </span>
                                @endforeach
                            </div>
                        </td>
                        <td class="ev-text-center"><strong>{{ $m['count'] }}</strong></td>
                        <td class="ev-text-center">
                            @if($m['avg'] !== null)
                                <span class="ev-avg-badge">{{ number_format($m['avg'], 2) }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="ev-text-center">
                            @if($m['pending_review'] > 0)
                                <span class="ev-pending-badge">{{ $m['pending_review'] }}</span>
                            @else
                                <span class="ev-muted">0</span>
                            @endif
                        </td>
                        <td class="ev-text-end">
                            <a href="{{ route('admin.evaluations.show', $assignment) }}" class="ev-btn-ghost ev-btn-sm" title="Ver detalle">
                                <i class="ti ti-eye"></i>
                            </a>
                            <a href="{{ route('admin.evaluations.export', $assignment) }}" class="ev-btn-ghost ev-btn-sm" title="Exportar Excel">
                                <i class="ti ti-file-spreadsheet"></i>
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@endsection
