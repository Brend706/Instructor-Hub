<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\ClassSession;
use App\Models\Instructor;
use App\Models\StudentAttendance;
use App\Services\AttendanceExcelExportService;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * "Instructorías" del coordinador.
 *
 *  - index(): lista de instructores con cuántas tutorías (class_sessions) han dado en total.
 *  - show():  para un instructor seleccionado, muestra cada tutoría con
 *             fecha, hora de inicio/fin, grupo y conteo de asistencias.
 */
class InstructoriaController extends Controller
{
    /**
     * Lista de instructores que tienen al menos un grupo asignado,
     * más conteos de instructorías dadas y asistencias totales.
     */
    public function index(): View
    {
        $instructors = Instructor::query()
            ->with(['user', 'instructorAssignments.classGroup'])
            ->whereHas('instructorAssignments')
            ->get();

        $sessionsByInstructor = [];
        $attendancesByInstructor = [];

        foreach ($instructors as $instructor) {
            $assignmentIds = $instructor->instructorAssignments->pluck('id');

            $sessionIds = ClassSession::query()
                ->whereIn('instructor_assignment_id', $assignmentIds)
                ->pluck('id');

            $sessionsByInstructor[$instructor->id] = $sessionIds->count();
            $attendancesByInstructor[$instructor->id] = StudentAttendance::query()
                ->whereIn('session_id', $sessionIds)
                ->where('attended', true)
                ->count();
        }

        return view('coordinator.instructorias.index', [
            'instructors' => $instructors,
            'sessionsByInstructor' => $sessionsByInstructor,
            'attendancesByInstructor' => $attendancesByInstructor,
        ]);
    }

    /**
     * Detalle por instructor: lista todas sus class_sessions con hora de inicio/fin,
     * grupo correspondiente y cantidad de asistentes que marcaron.
     */
    public function show(Instructor $instructor): View
    {
        $instructor->load(['user', 'instructorAssignments.classGroup']);

        $assignmentIds = $instructor->instructorAssignments->pluck('id');

        $sessions = ClassSession::query()
            ->whereIn('instructor_assignment_id', $assignmentIds)
            ->with(['instructorAssignment.classGroup'])
            ->withCount(['attendances as attendees_count' => fn ($q) => $q->where('attended', true)])
            ->orderByDesc('date')
            ->orderByDesc('start_time')
            ->get();

        return view('coordinator.instructorias.show', [
            'instructor' => $instructor,
            'sessions' => $sessions,
        ]);
    }

    /**
     * Descarga el .xlsx con todas las sesiones que ha dado un instructor.
     *
     * Pensado para que el coordinador pueda llevarse el histórico completo
     * de un instructor (no se restringe a un grupo en particular).
     *
     * Flujo:
     *  1. AttendanceExcelExportService::buildCoordinatorSessions carga las sesiones
     *     del instructor (con grupo y conteo de asistentes) y arma el libro con
     *     dos hojas: "Sesiones" y "Resumen".
     *  2. Se devuelven los bytes del archivo con los headers de descarga
     *     correctos para que el navegador guarde el .xlsx en vez de abrirlo
     *     como texto.
     */
    public function export(Instructor $instructor, AttendanceExcelExportService $exporter): Response
    {
        $file = $exporter->buildCoordinatorSessions($instructor);

        return response($file['content'], 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$file['filename'].'"',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }
}
