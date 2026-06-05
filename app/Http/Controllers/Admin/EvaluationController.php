<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EvaluationResult;
use App\Models\EvaluationType;
use App\Models\Instructor;
use App\Models\InstructorAssignment;
use App\Services\EvaluationExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Panel de evaluaciones para el ADMIN.
 *
 *  - index():        lista de instructorías con al menos una evaluación, con
 *                    filtros por instructor y ciclo + promedio por instructoría.
 *  - show():         detalle de UN assignment con todas las evaluaciones
 *                    (autoeval, coordinador, estudiantes, docente) y respuestas.
 *  - markReviewed(): marca un evaluation_results como revisado por admin.
 *  - byInstructor(): reporte que agrupa por instructor y muestra promedio histórico
 *                    por tipo y la cantidad de evaluaciones recibidas.
 *  - export():       descarga el .xlsx consolidado de un assignment.
 */
class EvaluationController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'instructor_id' => $request->integer('instructor_id') ?: null,
            'semester' => $request->string('semester')->trim()->toString() ?: null,
        ];

        // Solo assignments con al menos un evaluation_results asociado.
        $assignmentsQuery = InstructorAssignment::query()
            ->with(['classGroup', 'instructor.user'])
            ->whereExists(function ($q) {
                $q->select('id')
                    ->from('evaluation_results')
                    ->whereColumn('evaluation_results.assignment_id', 'instructor_assignments.id');
            })
            ->orderByDesc('id');

        if ($filters['instructor_id']) {
            $assignmentsQuery->where('instructor_id', $filters['instructor_id']);
        }
        if ($filters['semester']) {
            $assignmentsQuery->whereHas('classGroup', function ($q) use ($filters) {
                $q->where('semester', $filters['semester']);
            });
        }

        $assignments = $assignmentsQuery->get();

        // Carga TODOS los results de esos assignments en una sola query,
        // los agrupa por assignment_id, y desde ahí calculamos métricas.
        $results = EvaluationResult::query()
            ->whereIn('assignment_id', $assignments->pluck('id'))
            ->get()
            ->groupBy('assignment_id');

        $byTypeCounts = []; // [assignment_id][type_slug] => count
        $assignmentMetrics = []; // [assignment_id] => ['count','avg','pending_review']

        $typesById = EvaluationType::query()->get()->keyBy('id');

        foreach ($results as $assignmentId => $rs) {
            $count = $rs->count();
            $avg = $rs->whereNotNull('total_score')->avg('total_score');
            $pending = $rs->where('reviewed_by_admin', false)->count();
            $assignmentMetrics[$assignmentId] = [
                'count' => $count,
                'avg' => $avg !== null ? round((float) $avg, 2) : null,
                'pending_review' => $pending,
            ];
            $byTypeCounts[$assignmentId] = [];
            foreach ($rs as $r) {
                $slug = optional($typesById->get($r->evaluation_type_id))->slug ?? '?';
                $byTypeCounts[$assignmentId][$slug] = ($byTypeCounts[$assignmentId][$slug] ?? 0) + 1;
            }
        }

        // Para los selects del formulario de filtros.
        $instructors = Instructor::query()
            ->with('user')
            ->whereIn('id', $assignments->pluck('instructor_id')->unique())
            ->get()
            ->sortBy(fn ($i) => $i->user?->name ?? '');
        $semesters = $assignments->pluck('classGroup.semester')->filter()->unique()->values();

        return view('admin.evaluations.index', [
            'assignments' => $assignments,
            'metrics' => $assignmentMetrics,
            'byTypeCounts' => $byTypeCounts,
            'instructors' => $instructors,
            'semesters' => $semesters,
            'filters' => $filters,
        ]);
    }

    public function show(InstructorAssignment $assignment): View
    {
        $assignment->loadMissing(['classGroup', 'instructor.user']);

        $types = EvaluationType::query()
            ->with(['questions'])
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        $results = EvaluationResult::query()
            ->where('assignment_id', $assignment->id)
            ->with(['answers.questionTemplate', 'evaluator'])
            ->orderByDesc('id')
            ->get()
            ->groupBy('evaluation_type_id');

        // Métricas por tipo: count + promedio + pendientes de revisión.
        $metricsByType = [];
        foreach ($types as $t) {
            $list = $results[$t->id] ?? collect();
            $metricsByType[$t->id] = [
                'count' => $list->count(),
                'avg' => $list->isEmpty()
                    ? null
                    : round((float) $list->whereNotNull('total_score')->avg('total_score'), 2),
                'pending' => $list->where('reviewed_by_admin', false)->count(),
            ];
        }

        $overallAvg = null;
        $allWithScore = collect();
        foreach ($results as $list) {
            $allWithScore = $allWithScore->merge($list->whereNotNull('total_score'));
        }
        if ($allWithScore->isNotEmpty()) {
            $overallAvg = round((float) $allWithScore->avg('total_score'), 2);
        }

        return view('admin.evaluations.show', [
            'assignment' => $assignment,
            'types' => $types,
            'resultsByType' => $results,
            'metricsByType' => $metricsByType,
            'overallAvg' => $overallAvg,
        ]);
    }

    public function saveVerdict(Request $request, InstructorAssignment $assignment): RedirectResponse
    {
        $request->validate(['verdict' => ['nullable', 'string', 'max:4000']]);

        $assignment->admin_student_verdict = $request->input('verdict');
        $assignment->save();

        return back()->with('status', 'Veredicto guardado correctamente.');
    }

    public function markReviewed(EvaluationResult $result): RedirectResponse
    {
        $result->reviewed_by_admin = ! $result->reviewed_by_admin;
        $result->save();

        return back()->with(
            'status',
            $result->reviewed_by_admin
                ? 'Evaluación marcada como revisada.'
                : 'Evaluación marcada como pendiente.'
        );
    }

    public function byInstructor(Request $request): View
    {
        // Promedio histórico de cada instructor (todas las evaluaciones que tiene).
        $instructors = Instructor::query()
            ->with('user')
            ->get();

        $rows = $instructors->map(function (Instructor $instructor) {
            $resultsQuery = EvaluationResult::query()
                ->where('instructor_id', $instructor->id);

            $count = (clone $resultsQuery)->count();
            $avg = (clone $resultsQuery)->whereNotNull('total_score')->avg('total_score');

            $byType = (clone $resultsQuery)
                ->selectRaw('evaluation_type_id, COUNT(*) as total, AVG(total_score) as avg_score')
                ->groupBy('evaluation_type_id')
                ->get()
                ->keyBy('evaluation_type_id');

            return [
                'instructor' => $instructor,
                'name' => $instructor->user?->name ?? '—',
                'count' => $count,
                'avg' => $avg !== null ? round((float) $avg, 2) : null,
                'by_type' => $byType,
            ];
        })
            ->filter(fn ($r) => $r['count'] > 0)
            ->sortByDesc('avg')
            ->values();

        $types = EvaluationType::query()->orderBy('id')->get();

        return view('admin.evaluations.by_instructor', [
            'rows' => $rows,
            'types' => $types,
        ]);
    }

    public function export(InstructorAssignment $assignment, EvaluationExportService $exporter): Response
    {
        $file = $exporter->buildConsolidated($assignment);

        return response($file['content'], 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$file['filename'].'"',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }
}
