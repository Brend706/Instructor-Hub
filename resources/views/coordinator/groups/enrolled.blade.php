@extends('layouts.coordinator', ['title' => 'Estudiantes inscritos'])

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/coordinator/students.css') }}">
    <link rel="stylesheet" href="{{ asset('css/coordinator/groups.css') }}">
@endpush

@section('content')

<a href="{{ route('coordinator.groups.index') }}" class="back-link">
    <i class="ti ti-arrow-left" aria-hidden="true"></i> Volver a grupos
</a>

<div class="page-header">
    <div>
        <h1 class="page-title">Estudiantes inscritos</h1>
        <p class="page-sub">Lista del grupo — carnet, nombre y correo</p>
    </div>
    <a href="{{ route('coordinator.groups.students', $group) }}" class="btn btn-primary">
        <i class="ti ti-users-plus" aria-hidden="true"></i> Agregar estudiantes
    </a>
</div>

<div class="group-info-card">
    <div>
        <div class="info-item-label">Materia</div>
        <div class="info-item-value">{{ $group->name }}</div>
        <div class="info-item-sub">{{ $group->professor }}</div>
    </div>
    <div>
        <div class="info-item-label">Ciclo</div>
        <div class="info-item-value">{{ $group->semester }}</div>
    </div>
    <div>
        <div class="info-item-label">Modalidad</div>
        <div class="info-item-value">{{ $group->modality }}</div>
        <div class="info-item-sub">{{ $group->classroom }}</div>
    </div>
    <div>
        <div class="info-item-label">Instructor</div>
        <div class="info-item-value">{{ $instructorName ?? 'Sin asignar' }}</div>
        @if(!empty($instructorMajor))
            <div class="info-item-sub">{{ $instructorMajor }}</div>
        @endif
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
                        <td class="td-main">{{ $student->name }}</td>
                        <td style="font-size:12px">{{ $student->email }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="empty-state">
                            <i class="ti ti-users-off" aria-hidden="true"></i>
                            <p>No hay estudiantes inscritos. Usa «Agregar estudiantes» para importar el Excel.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
