@extends('layouts.instructor', ['title' => 'Mis grupos'])

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/instructor/groups.css') }}">
@endpush

@section('content')

<a href="{{ route('instructor.dashboard') }}" class="back-link">
    <i class="ti ti-arrow-left" aria-hidden="true"></i> Volver al dashboard
</a>

<div class="page-header">
    <div>
        <h1 class="page-title">Mis grupos</h1>
        <p class="page-sub">Toca un grupo para ver los estudiantes inscritos</p>
    </div>
</div>

@if($assignments->isEmpty())
    <div class="empty-card">
        <i class="ti ti-books-off" aria-hidden="true"></i>
        <p>No tienes grupos asignados todavía.</p>
    </div>
@else
    <div class="groups-list">
        @foreach($assignments as $assignment)
            @php $group = $assignment->classGroup; @endphp
            @if(!$group)
                @continue
            @endif
            <a href="{{ route('instructor.groups.show', $assignment) }}" class="group-row">
                <div class="group-row-icon"><i class="ti ti-books" aria-hidden="true"></i></div>
                <div class="group-row-main">
                    <div class="group-row-title">{{ $group->name }}</div>
                    <div class="group-row-sub">{{ $group->professor }} · {{ $group->semester }}</div>
                    <div class="group-row-meta">
                        <span><i class="ti ti-clock" aria-hidden="true"></i> {{ $group->schedule }}</span>
                        <span><i class="ti ti-{{ $group->modality === 'Presencial' ? 'building' : 'video' }}" aria-hidden="true"></i> {{ $group->modality }}</span>
                    </div>
                </div>
                <div class="group-row-count">
                    <i class="ti ti-users" aria-hidden="true"></i>
                    {{ $group->students_count ?? 0 }}
                </div>
                <i class="ti ti-chevron-right group-row-arrow" aria-hidden="true"></i>
            </a>
        @endforeach
    </div>
@endif

@endsection
