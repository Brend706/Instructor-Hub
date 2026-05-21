@extends('layouts.instructor', ['title' => 'Asistencia · ' . ($group->name ?? '')])

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/instructor/attendance.css') }}">
@endpush

@section('content')

<a href="{{ route('instructor.attendance.index') }}" class="back-link">
    <i class="ti ti-arrow-left" aria-hidden="true"></i> Volver a asistencia
</a>

<div class="page-header">
    <div>
        <h1 class="page-title">{{ $group->name }}</h1>
        <p class="page-sub">{{ $group->professor }} · {{ $group->semester }} · {{ $sessions->count() }} sesión(es) registradas</p>
    </div>
</div>

@if($sessions->isEmpty())
    <div class="empty-card">
        <i class="ti ti-calendar-off" aria-hidden="true"></i>
        <p>Esta instructoría aún no tiene sesiones registradas.</p>
        <a href="{{ route('instructor.session') }}" class="btn btn-primary" style="margin-top:10px">
            <i class="ti ti-player-play"></i> Iniciar sesión
        </a>
    </div>
@elseif($students->isEmpty())
    <div class="empty-card">
        <i class="ti ti-users-off" aria-hidden="true"></i>
        <p>No hay estudiantes inscritos en este grupo.</p>
    </div>
@else
    {{-- MATRIZ: filas = estudiantes, columnas = sesiones, celda ✓ si asistió. --}}
    <div class="att-matrix-card">
        <div class="att-matrix-wrap">
            <table class="att-matrix">
                <thead>
                    <tr>
                        <th class="att-col-student">Estudiante</th>
                        <th class="att-col-carnet">Carnet</th>
                        @foreach($sessions as $session)
                            <th class="att-col-session">
                                <div class="att-col-session-date">
                                    {{ \Illuminate\Support\Carbon::parse($session->date)->translatedFormat('d M') }}
                                </div>
                                <div class="att-col-session-hour">
                                    {{ \Illuminate\Support\Str::of((string) $session->start_time)->before('.')->limit(5, '') }}
                                </div>
                            </th>
                        @endforeach
                        <th class="att-col-total">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($students as $student)
                        @php
                            $studentAttendCount = 0;
                        @endphp
                        <tr>
                            <td class="att-cell-student">
                                <div class="att-student">
                                    <div class="att-avatar">{{ strtoupper(mb_substr($student->name ?? 'ES', 0, 2)) }}</div>
                                    <span>{{ $student->name }}</span>
                                </div>
                            </td>
                            <td class="att-cell-carnet">{{ $student->carnet }}</td>
                            @foreach($sessions as $session)
                                @php
                                    $attended = in_array($student->id, $attendedMap[$session->id] ?? [], true);
                                    if ($attended) { $studentAttendCount++; }
                                @endphp
                                <td class="att-cell-mark {{ $attended ? 'is-yes' : 'is-no' }}">
                                    @if($attended)
                                        <i class="ti ti-check" aria-hidden="true"></i>
                                    @else
                                        <i class="ti ti-minus" aria-hidden="true"></i>
                                    @endif
                                </td>
                            @endforeach
                            <td class="att-cell-total">
                                <span class="att-total-pill {{ $studentAttendCount === $sessions->count() ? 'is-full' : '' }}">
                                    {{ $studentAttendCount }}/{{ $sessions->count() }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="2" class="att-foot-label">Asistentes por sesión</th>
                        @foreach($sessions as $session)
                            @php
                                $attendees = count($attendedMap[$session->id] ?? []);
                            @endphp
                            <th class="att-foot-count">{{ $attendees }}</th>
                        @endforeach
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endif

@endsection
