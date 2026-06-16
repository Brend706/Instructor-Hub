<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coordinator;
use App\Models\Instructor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportController extends Controller
{
    /**
     * Reporte 1: Desempeño de instructores
     * Muestra evaluaciones por tipo, sesiones impartidas y tasa de asistencia.
     */
    public function instructors(Request $request): View
    {
        $coordinators = Coordinator::with('user:id,name')->orderBy('id')->get();

        // ── Instructores (filtrable) ──────────────────────────────
        $instructorQuery = DB::table('instructors')
            ->join('users', 'instructors.user_id', '=', 'users.id')
            ->leftJoin('coordinators', 'instructors.coordinator_id', '=', 'coordinators.id')
            ->leftJoin('users as cu', 'coordinators.user_id', '=', 'cu.id')
            ->select(
                'instructors.id',
                'users.name',
                'users.email',
                'instructors.major',
                'instructors.category',
                'instructors.status',
                'instructors.coordinator_id',
                DB::raw('COALESCE(coordinators.school_name, coordinators.catedra, cu.name) as coord_label'),
                DB::raw('cu.name as coord_person')
            );

        if ($request->filled('coordinator_id')) {
            $instructorQuery->where('instructors.coordinator_id', $request->coordinator_id);
        }
        if ($request->filled('status')) {
            $instructorQuery->where('instructors.status', $request->status);
        }

        $instructors = $instructorQuery->orderBy('instructors.status')->orderBy('users.name')->get();

        $instructorIds = $instructors->pluck('id')->all();

        // ── Promedios de evaluación por tipo ─────────────────────
        $evalAvgs = DB::table('evaluation_results')
            ->join('evaluation_types', 'evaluation_results.evaluation_type_id', '=', 'evaluation_types.id')
            ->whereIn('evaluation_results.instructor_id', $instructorIds)
            ->whereNotNull('evaluation_results.total_score')
            ->select(
                'evaluation_results.instructor_id',
                'evaluation_types.slug',
                DB::raw('ROUND(AVG(evaluation_results.total_score), 2) as avg_score'),
                DB::raw('COUNT(*) as n')
            )
            ->groupBy('evaluation_results.instructor_id', 'evaluation_types.id', 'evaluation_types.slug')
            ->get()
            ->groupBy('instructor_id')
            ->map(fn($rows) => $rows->keyBy('slug'));

        // ── Sesiones impartidas por instructor ────────────────────
        $sessionCounts = DB::table('class_sessions')
            ->join('instructor_assignments', 'class_sessions.instructor_assignment_id', '=', 'instructor_assignments.id')
            ->whereIn('instructor_assignments.instructor_id', $instructorIds)
            ->select(
                'instructor_assignments.instructor_id',
                DB::raw('COUNT(*) as session_count')
            )
            ->groupBy('instructor_assignments.instructor_id')
            ->pluck('session_count', 'instructor_id');

        // ── Tasa de asistencia promedio por instructor ────────────
        $attendanceRates = DB::table('student_attendances')
            ->join('class_sessions', 'student_attendances.session_id', '=', 'class_sessions.id')
            ->join('instructor_assignments', 'class_sessions.instructor_assignment_id', '=', 'instructor_assignments.id')
            ->whereIn('instructor_assignments.instructor_id', $instructorIds)
            ->select(
                'instructor_assignments.instructor_id',
                DB::raw('ROUND(AVG(CASE WHEN student_attendances.attended = 1 THEN 100.0 ELSE 0 END), 1) as rate')
            )
            ->groupBy('instructor_assignments.instructor_id')
            ->pluck('rate', 'instructor_id');

        return view('admin.reports.instructors', compact(
            'instructors', 'coordinators', 'evalAvgs', 'sessionCounts', 'attendanceRates'
        ));
    }

    /**
     * Reporte 2: Resumen por coordinación
     * Estadísticas agrupadas por coordinador: instructores, asignaciones, evaluaciones.
     */
    public function byCoordination(): View
    {
        // ── Conteo de instructores por estado y coordinador ───────
        $instructorStats = DB::table('instructors')
            ->select(
                'coordinator_id',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'Activo' THEN 1 ELSE 0 END) as activos"),
                DB::raw("SUM(CASE WHEN status = 'Suspendido' THEN 1 ELSE 0 END) as suspendidos"),
                DB::raw("SUM(CASE WHEN status IN ('Bloqueado','Inactivo') THEN 1 ELSE 0 END) as bloqueados")
            )
            ->groupBy('coordinator_id')
            ->get()
            ->keyBy('coordinator_id');

        // ── Asignaciones activas por coordinador ──────────────────
        $activeAssignments = DB::table('instructor_assignments')
            ->join('instructors', 'instructor_assignments.instructor_id', '=', 'instructors.id')
            ->where('instructor_assignments.status', 'Activo')
            ->select('instructors.coordinator_id', DB::raw('COUNT(*) as count'))
            ->groupBy('instructors.coordinator_id')
            ->pluck('count', 'coordinator_id');

        // ── Promedio general de evaluaciones por coordinador ──────
        $avgScores = DB::table('evaluation_results')
            ->join('instructors', 'evaluation_results.instructor_id', '=', 'instructors.id')
            ->whereNotNull('evaluation_results.total_score')
            ->whereNotNull('instructors.coordinator_id')
            ->select(
                'instructors.coordinator_id',
                DB::raw('ROUND(AVG(evaluation_results.total_score), 2) as avg_score')
            )
            ->groupBy('instructors.coordinator_id')
            ->pluck('avg_score', 'coordinator_id');

        // ── Solicitudes pendientes por coordinador ────────────────
        $pendingSuspensions = DB::table('suspension_requests')
            ->join('instructors', 'suspension_requests.instructor_id', '=', 'instructors.id')
            ->where('suspension_requests.status', 'pending')
            ->select('instructors.coordinator_id', DB::raw('COUNT(*) as count'))
            ->groupBy('instructors.coordinator_id')
            ->pluck('count', 'coordinator_id');

        // ── Coordinadores ─────────────────────────────────────────
        $coordinators = Coordinator::with('user:id,name,email')->orderBy('id')->get();

        // ── Estadísticas globales del sistema ─────────────────────
        $globalStats = [
            'total_instructors'  => DB::table('instructors')->count(),
            'active_instructors' => DB::table('instructors')->where('status', 'Activo')->count(),
            'total_coordinators' => $coordinators->count(),
            'active_assignments' => DB::table('instructor_assignments')->where('status', 'Activo')->count(),
            'pending_suspensions'=> DB::table('suspension_requests')->where('status', 'pending')->count(),
            'avg_score_global'   => round((float) DB::table('evaluation_results')
                ->whereNotNull('total_score')->avg('total_score'), 2),
        ];

        return view('admin.reports.by_coordination', compact(
            'coordinators', 'instructorStats', 'activeAssignments', 'avgScores',
            'pendingSuspensions', 'globalStats'
        ));
    }
}
