@extends('layouts.instructor', ['title' => 'Estudiantes del grupo'])

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/instructor/groups.css') }}">
@endpush

@section('content')

<a href="{{ route('instructor.groups.index') }}" class="back-link">
    <i class="ti ti-arrow-left" aria-hidden="true"></i> Volver a mis grupos
</a>

<div class="page-header">
    <div>
        <h1 class="page-title">{{ $group->name }}</h1>
        <p class="page-sub">{{ $group->professor }} · {{ $group->semester }}</p>
    </div>
    <span class="students-pill">
        <i class="ti ti-users" aria-hidden="true"></i>
        {{ $students->count() }} estudiantes
    </span>
</div>

<div class="group-info-card">
    <div>
        <div class="info-label">Horario</div>
        <div class="info-value">{{ $group->schedule }}</div>
    </div>
    <div>
        <div class="info-label">Modalidad</div>
        <div class="info-value">{{ $group->modality }}</div>
        <div class="info-sub">{{ $group->classroom }}</div>
    </div>
</div>

<div class="table-card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Carnet</th>
                    <th>Nombre completo</th>
                    <th>Correo</th>
                </tr>
            </thead>
            <tbody>
                @forelse($students as $i => $student)
                    <tr>
                        <td style="color:var(--text-muted)">{{ $i + 1 }}</td>
                        <td style="font-family:monospace;font-size:12px">{{ $student->carnet }}</td>
                        <td class="td-name">{{ $student->name }}</td>
                        <td style="font-size:12px">{{ $student->email }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="empty-state">
                            <i class="ti ti-users-off" aria-hidden="true"></i>
                            <p>No hay estudiantes inscritos en este grupo.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
