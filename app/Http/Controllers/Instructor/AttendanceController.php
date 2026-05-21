<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\ClassSession;
use App\Models\Instructor;
use App\Models\InstructorAssignment;
use App\Models\StudentAttendance;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Asistencia" del instructor:
 *  - index: lista las instructorías del usuario logueado con resumen (sesiones, asistencias).
 *  - show:  para una instructoría, muestra cada sesión y los estudiantes que marcaron asistencia.
 *
 * Datos:
 *   instructor_assignments → class_sessions → student_attendances ← students
 */
class AttendanceController extends Controller
{
    /**
     * Lista las instructorías del instructor logueado con:
     *  - cantidad de sesiones realizadas
     *  - total de asistencias registradas
     *  - cantidad de estudiantes inscritos
     */
    public function index(Request $request): View
    {
        $instructor = $this->currentInstructor($request);

        $assignments = $instructor->instructorAssignments()
            ->with(['classGroup' => fn ($q) => $q->withCount('students')])
            ->withCount('classSessions as sessions_count')
            ->orderByDesc('id')
            ->get();

        $assignmentIds = $assignments->pluck('id')->all();
        $sessionsByAssignment = ClassSession::query()
            ->whereIn('instructor_assignment_id', $assignmentIds)
            ->get(['id', 'instructor_assignment_id'])
            ->groupBy('instructor_assignment_id');

        // Total de asistencias por instructoría (suma de filas attended=true en student_attendances).
        $attendancesByAssignment = [];
        foreach ($sessionsByAssignment as $assignmentId => $sessions) {
            $attendancesByAssignment[$assignmentId] = StudentAttendance::query()
                ->whereIn('session_id', $sessions->pluck('id'))
                ->where('attended', true)
                ->count();
        }

        return view('instructors.attendance.index', [
            'assignments' => $assignments,
            'attendancesByAssignment' => $attendancesByAssignment,
        ]);
    }

    /**
     * Para una instructoría dada, muestra una matriz: filas = estudiantes inscritos,
     * columnas = sesiones de clase, celdas marcadas si el estudiante asistió.
     */
    public function show(Request $request, InstructorAssignment $assignment): View
    {
        $this->ensureOwnsAssignment($request, $assignment);

        $group = $assignment->classGroup;

        // Sesiones de esa instructoría, en orden cronológico.
        $sessions = $assignment->classSessions()
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        // Estudiantes inscritos al grupo (uno por carnet/email; orden alfabético).
        $students = $group->students()->orderBy('name')->get();

        // Map { session_id => Set<student_id que asistió> } para construir la matriz rápido.
        $attended = StudentAttendance::query()
            ->whereIn('session_id', $sessions->pluck('id'))
            ->where('attended', true)
            ->get(['session_id', 'student_id'])
            ->groupBy('session_id')
            ->map(fn ($rows) => $rows->pluck('student_id')->all());

        return view('instructors.attendance.show', [
            'assignment' => $assignment,
            'group' => $group,
            'sessions' => $sessions,
            'students' => $students,
            'attendedMap' => $attended,
        ]);
    }

    private function currentInstructor(Request $request): Instructor
    {
        return Instructor::query()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }

    /**
     * Evita que un instructor abra la asistencia de la instructoría de otro.
     */
    private function ensureOwnsAssignment(Request $request, InstructorAssignment $assignment): void
    {
        $instructor = $this->currentInstructor($request);

        if ((int) $assignment->instructor_id !== (int) $instructor->id) {
            throw new ModelNotFoundException;
        }

        $assignment->loadMissing('classGroup');
    }
}
