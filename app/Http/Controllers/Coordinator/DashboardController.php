<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\ClassGroup;
use App\Models\Coordinator;
use App\Models\Instructor;
use App\Models\InstructorAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        /** @var Coordinator|null $coordinator */
        $coordinator = Coordinator::query()->where('user_id', $user->id)->first();
        $coordId     = $coordinator?->id ?? -1;

        $coordination = $coordinator ? $this->coordinatorLabel($coordinator) : null;

        // ── Ciclos del coordinador (solo grupos que le pertenecen) ────────────
        $cycles = ClassGroup::query()
            ->where('coordinator_id', $coordId)
            ->whereNotNull('semester')
            ->distinct()
            ->orderByDesc('semester')
            ->pluck('semester')
            ->values();

        $activeCycle = $request->string('cycle')->toString();
        if ($activeCycle === '' && $cycles->isNotEmpty()) {
            $activeCycle = (string) $cycles->first();
        }

        // ── Grupos del coordinador para el ciclo activo ───────────────────────
        $groupsBase = ClassGroup::query()
            ->where('coordinator_id', $coordId)
            ->withCount('students')
            ->when($activeCycle !== '', fn ($q) => $q->where('semester', $activeCycle));

        $activeGroupsCount = (clone $groupsBase)->count();

        $groupsInCycle = (clone $groupsBase)
            ->orderBy('name')
            ->limit(4)
            ->get();

        // ── Asignaciones de los grupos del coordinador en el ciclo activo ─────
        $assignments = InstructorAssignment::query()
            ->with(['instructor.user', 'classGroup'])
            ->whereHas('classGroup', function ($q) use ($coordId, $activeCycle) {
                $q->where('coordinator_id', $coordId);
                if ($activeCycle !== '') {
                    $q->where('semester', $activeCycle);
                }
            })
            ->latest()
            ->get();

        $instructorIdsInCycle = $assignments->pluck('instructor_id')->unique()->values();

        // "Mis instructores" = instructores que pertenecen a este coordinador
        $totalInstructors  = Instructor::where('coordinator_id', $coordId)->count();
        $activeInstructors = Instructor::where('coordinator_id', $coordId)
            ->where('status', 'Activo')
            ->count();

        // Panel de instructores (tabla del dashboard): los del ciclo
        $instructors = $assignments
            ->groupBy('instructor_id')
            ->take(5)
            ->map(function ($rows) {
                $first      = $rows->first();
                $instructor = $first?->instructor;
                return [
                    'name'   => (string) ($instructor?->user?->name ?? 'Instructor'),
                    'group'  => $first?->classGroup?->name,
                    'status' => $instructor?->status ?? 'Activo',
                ];
            })
            ->values();

        // ── Sesiones del coordinador (via grupos del coordinador) ─────────────
        $sessionsTotal      = 0;
        $sessionsThisWeek   = 0;
        $asistenciaPromedio = 0.0;

        if (Schema::hasTable('class_sessions')) {
            $sessBase = DB::table('class_sessions')
                ->join('instructor_assignments', 'class_sessions.instructor_assignment_id', '=', 'instructor_assignments.id')
                ->join('class_groups', 'instructor_assignments.class_group_id', '=', 'class_groups.id')
                ->where('class_groups.coordinator_id', $coordId);

            if ($activeCycle !== '') {
                $sessBase->where('class_groups.semester', $activeCycle);
            }

            $sessionsTotal    = (clone $sessBase)->count();
            $sessionsThisWeek = (clone $sessBase)
                ->where('class_sessions.date', '>=', now()->startOfWeek()->toDateString())
                ->count();

            if (Schema::hasTable('student_attendances')) {
                $attRow = (clone $sessBase)
                    ->join('student_attendances', 'student_attendances.session_id', '=', 'class_sessions.id')
                    ->selectRaw('ROUND(AVG(CASE WHEN student_attendances.attended = 1 THEN 100.0 ELSE 0 END), 1) as rate')
                    ->first();
                $asistenciaPromedio = (float) ($attRow->rate ?? 0);
            }
        }

        // ── Evaluaciones pendientes (instructorías finalizadas sin eval del coordinador) ──
        $coordTypeId  = DB::table('evaluation_types')->where('slug', 'coordinator')->value('id');
        $evalsPending = 0;

        if ($coordTypeId && $coordinator) {
            $evalsPending = DB::table('instructor_assignments')
                ->join('class_groups', 'instructor_assignments.class_group_id', '=', 'class_groups.id')
                ->where('class_groups.coordinator_id', $coordId)
                ->where('instructor_assignments.status', 'Finalizado')
                ->whereNotExists(function ($q) use ($coordTypeId) {
                    $q->from('evaluation_results')
                      ->whereColumn('evaluation_results.assignment_id', 'instructor_assignments.id')
                      ->where('evaluation_results.evaluation_type_id', $coordTypeId);
                })
                ->count();
        }

        return view('coordinator.dashboard', [
            'coordinatorName'  => $user->name,
            'coordinationName' => $coordination,
            'cycles'           => $cycles,
            'activeCycle'      => $activeCycle,
            'stats' => [
                'instructors_total'   => $totalInstructors,
                'instructors_active'  => $activeInstructors,
                'groups_active'       => $activeGroupsCount,
                'sessions_total'      => $sessionsTotal,
                'sessions_this_week'  => $sessionsThisWeek,
                'asistencia_promedio' => $asistenciaPromedio,
                'evals_pending'       => $evalsPending,
            ],
            'instructors'      => $instructors,
            'groups'           => $groupsInCycle,
            'totalInstructors' => $totalInstructors,
            'evalsPending'     => $evalsPending,
        ]);
    }

    private function coordinatorLabel(Coordinator $coordinator): ?string
    {
        if (Schema::hasColumn('coordinators', 'school_name')) {
            return $coordinator->school_name ?: $coordinator->catedra ?: null;
        }
        if (Schema::hasColumn('coordinators', 'catedra')) {
            return $coordinator->catedra ?: null;
        }
        return null;
    }
}
