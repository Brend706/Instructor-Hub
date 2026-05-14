<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\ClassGroup;
use App\Models\Coordinator;
use App\Models\Instructor;
use App\Models\InstructorAssignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        // La coordinación del usuario se guarda en `coordinators` (compat: `coordination_name` o `name`).
        $coordination = null;
        $coordinator = Coordinator::query()->where('user_id', $user->id)->first();
        if ($coordinator) {
            $coordination = $this->coordinatorCoordinationName($coordinator);
        }

        // Ciclos disponibles salen de `class_groups.semester` (select del dashboard).
        $cycles = ClassGroup::query()
            ->whereNotNull('semester')
            ->distinct()
            ->orderByDesc('semester')
            ->pluck('semester')
            ->values();

        $activeCycle = $request->string('cycle')->toString();
        if ($activeCycle === '' && $cycles->isNotEmpty()) {
            $activeCycle = (string) $cycles->first();
        }

        // Instructores visibles en el dashboard:
        // se derivan desde `instructor_assignments` para el ciclo seleccionado, de modo que
        // si un instructor está asignado a un grupo, aparezca aquí aunque su `major` no coincida.
        $hasInstructorStatus = Schema::hasColumn('instructors', 'status');

        // Grupos del ciclo activo.
        $groupsQuery = ClassGroup::query()
            ->withCount('students')
            ->when($activeCycle !== '', fn (Builder $q) => $q->where('semester', $activeCycle));

        $activeGroupsCount = (clone $groupsQuery)->count();
        $groupsInCycle = (clone $groupsQuery)
            ->orderBy('name')
            ->limit(4)
            ->get();

        // Asignaciones (ciclo) → instructores + grupo.
        $assignments = InstructorAssignment::query()
            ->with(['instructor.user', 'classGroup'])
            ->when($activeCycle !== '', function ($q) use ($activeCycle) {
                $q->whereHas('classGroup', fn ($g) => $g->where('semester', $activeCycle));
            })
            ->latest()
            ->get();

        $instructorIds = $assignments->pluck('instructor_id')->unique()->values();

        $totalInstructors = $instructorIds->count();
        $activeInstructors = $totalInstructors;

        if ($hasInstructorStatus && $totalInstructors > 0) {
            $activeInstructors = Instructor::query()
                ->whereIn('id', $instructorIds)
                ->where('status', 'Activo')
                ->count();
        }

        // Panel "mis instructores": muestra el primer grupo asignado del ciclo (si hay varios).
        $instructors = $assignments
            ->groupBy('instructor_id')
            ->take(4)
            ->map(function ($rows) use ($hasInstructorStatus) {
                /** @var InstructorAssignment $first */
                $first = $rows->first();
                $instructor = $first?->instructor;
                $group = $first?->classGroup;

                return [
                    'name' => (string) ($instructor?->user?->name ?? 'Instructor'),
                    'group' => $group?->name,
                    'status' => $hasInstructorStatus ? ($instructor?->status ?? null) : null,
                ];
            })
            ->values();

        // Métricas de sesiones (si existen tablas); si no, se muestran en 0 sin romper la vista.
        $sessionsTotal = 0;
        $sessionsThisWeek = 0;
        $sessionsPending = 0;

        if (Schema::hasTable('class_sessions') && Schema::hasTable('instructor_assignments')) {
            $base = DB::table('class_sessions')
                ->join('instructor_assignments', 'class_sessions.instructor_assignment_id', '=', 'instructor_assignments.id')
                ->join('class_groups', 'instructor_assignments.class_group_id', '=', 'class_groups.id');

            if ($activeCycle !== '') {
                $base->where('class_groups.semester', $activeCycle);
            }

            $sessionsTotal = (clone $base)->count();
            $sessionsThisWeek = (clone $base)->where('class_sessions.date', '>=', now()->subDays(7)->toDateString())->count();

            // "Pendientes": sesiones que aún no tienen asistencia registrada (si existe la tabla).
            if (Schema::hasTable('student_attendances')) {
                $sessionsPending = (clone $base)
                    ->leftJoin('student_attendances', 'student_attendances.session_id', '=', 'class_sessions.id')
                    ->whereNull('student_attendances.id')
                    ->distinct('class_sessions.id')
                    ->count('class_sessions.id');
            }
        }

        return view('coordinator.dashboard', [
            'coordinatorName' => $user->name,
            'coordinationName' => $coordination,
            'cycles' => $cycles,
            'activeCycle' => $activeCycle,
            'stats' => [
                'instructors_total' => $totalInstructors,
                'instructors_active' => $activeInstructors,
                'groups_active' => $activeGroupsCount,
                'sessions_total' => $sessionsTotal,
                'sessions_this_week' => $sessionsThisWeek,
                'sessions_pending' => $sessionsPending,
            ],
            'instructors' => $instructors,
            'groups' => $groupsInCycle,
        ]);
    }

    private function coordinatorCoordinationName(Coordinator $coordinator): ?string
    {
        // Compatibilidad con BD antiguas/nuevas.
        if (Schema::hasColumn('coordinators', 'coordination_name')) {
            return $coordinator->coordination_name ?: $coordinator->name;
        }

        return $coordinator->name;
    }
}
