@extends('layouts.admin', ['title' => 'Reporte por instructor'])

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin/evaluations.css') }}">
@endpush

@section('content')

<a href="{{ route('admin.evaluations.index') }}" class="back-link">
    <i class="ti ti-arrow-left" aria-hidden="true"></i> Volver a evaluaciones
</a>

<div class="page-header">
    <div>
        <h1 class="page-title">Reporte por instructor</h1>
        <p class="page-sub">
            Promedio histórico de cada instructor por tipo de evaluación.
            Ordenado de mayor a menor promedio general.
        </p>
    </div>
</div>

@if($rows->isEmpty())
    <div class="ev-empty">
        <i class="ti ti-clipboard-off" aria-hidden="true"></i>
        <p>Todavía no hay evaluaciones registradas en el sistema.</p>
    </div>
@else
    <div class="ev-table-card">
        <table class="ev-table">
            <thead>
                <tr>
                    <th>Instructor</th>
                    @foreach($types as $type)
                        <th class="ev-text-center">{{ $type->name }}</th>
                    @endforeach
                    <th class="ev-text-center">Total</th>
                    <th class="ev-text-center">Promedio</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    <tr>
                        <td>
                            <div class="ev-td-name">{{ $row['name'] }}</div>
                        </td>
                        @foreach($types as $type)
                            @php $cell = $row['by_type']->get($type->id); @endphp
                            <td class="ev-text-center">
                                @if($cell && $cell->total > 0 && $cell->avg_score !== null)
                                    <div class="ev-cell-avg">{{ number_format((float) $cell->avg_score, 2) }}</div>
                                    <div class="ev-cell-sub">{{ $cell->total }} eval.</div>
                                @elseif($cell && $cell->total > 0)
                                    <div class="ev-cell-sub">{{ $cell->total }} eval.</div>
                                @else
                                    <span class="ev-muted">—</span>
                                @endif
                            </td>
                        @endforeach
                        <td class="ev-text-center"><strong>{{ $row['count'] }}</strong></td>
                        <td class="ev-text-center">
                            @if($row['avg'] !== null)
                                <span class="ev-avg-badge">{{ number_format($row['avg'], 2) }}</span>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@endsection
