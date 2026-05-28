@extends('layouts.coordinator', ['title' => 'Importar evaluaciones'])

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/coordinator/evaluations.css') }}">
@endpush

@section('content')

@php
    $instructorName = $assignment->instructor?->user?->name ?? 'Instructor';
    $isStudent = $type->slug === 'student';
@endphp

<a href="{{ route('coordinator.evaluations.index') }}" class="back-link">
    <i class="ti ti-arrow-left" aria-hidden="true"></i> Volver a evaluaciones
</a>

<div class="page-header">
    <div>
        <h1 class="page-title">Importar evaluaciones — {{ $type->name }}</h1>
        <p class="page-sub">
            <strong>{{ $instructorName }}</strong>
            · {{ $assignment->classGroup?->name ?? 'Grupo' }}
            @if($assignment->classGroup?->semester) · {{ $assignment->classGroup->semester }} @endif
        </p>
    </div>
</div>

@if(session('status'))
    <div class="ev-flash-success">
        <i class="ti ti-circle-check" aria-hidden="true"></i> {{ session('status') }}
    </div>
@endif

@if(session('import_errors') && count(session('import_errors')))
    <div class="ev-flash-error">
        <i class="ti ti-alert-triangle" aria-hidden="true"></i>
        <div>
            <strong>Algunas filas no se pudieron importar:</strong>
            <ul>
                @foreach(session('import_errors') as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

@if($errors->any())
    <div class="ev-flash-error">
        <i class="ti ti-alert-triangle" aria-hidden="true"></i>
        <div>
            @foreach($errors->all() as $err)
                <div>{{ $err }}</div>
            @endforeach
        </div>
    </div>
@endif

<div class="ev-import-grid">
    {{-- Paso 1: descargar plantilla --}}
    <div class="ev-import-card">
        <div class="ev-import-step">1</div>
        <h2 class="ev-import-title">Descargá el formulario de evaluación</h2>
        <p class="ev-import-desc">
            Descargá el formulario de evaluación correspondiente y completá la información solicitada antes de subir el archivo.
        </p>
        <a href="{{ route('coordinator.evaluations.import.template', [$assignment, $type->slug]) }}"
           class="ev-btn-ghost">
            <i class="ti ti-file-download" aria-hidden="true"></i>
            Descarga el formulario
        </a>
    </div>

    {{-- Paso 2: subir archivo --}}
    <div class="ev-import-card">
        <div class="ev-import-step">2</div>
        <h2 class="ev-import-title">Subí el archivo completado</h2>
        <p class="ev-import-desc">
            Solo se aceptan archivos .xlsx o .xls de hasta 5 MB.
            Las filas completamente vacías se ignoran.
        </p>
        <form method="POST"
              action="{{ route('coordinator.evaluations.import.store', [$assignment, $type->slug]) }}"
              enctype="multipart/form-data"
              class="ev-import-form">
            @csrf
            <input type="file" name="archivo" accept=".xlsx,.xls" required class="ev-file-input">
            <button type="submit" class="ev-btn-primary">
                <i class="ti ti-upload" aria-hidden="true"></i>
                Importar
            </button>
        </form>
    </div>
</div>

{{-- Historial de imports previos para este assignment + tipo --}}
<div class="ev-history">
    <div class="ev-history-head">
        <h2 class="ev-history-title">Imports previos</h2>
        @if($previousImports->isNotEmpty())
            <span class="ev-history-sub">
                {{ $previousImports->count() }} evaluación(es) registradas
                @if($averageScore !== null)
                    · Promedio general: <strong>{{ number_format($averageScore, 2) }} / 5</strong>
                @endif
            </span>
        @endif
    </div>

    @if($previousImports->isEmpty())
        <p class="ev-history-empty">Todavía no hay evaluaciones importadas para este tipo.</p>
    @else
        <table class="ev-history-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Fecha</th>
                    <th>Promedio</th>
                </tr>
            </thead>
            <tbody>
                @foreach($previousImports as $i => $r)
                    <tr>
                        <td>{{ $previousImports->count() - $i }}</td>
                        <td>{{ $r->submitted_at?->translatedFormat('d M Y H:i') ?? '—' }}</td>
                        <td>
                            @if($r->total_score !== null)
                                <strong>{{ number_format((float) $r->total_score, 2) }}</strong> / 5
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

@endsection
