<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\Coordinator;
use App\Models\EvaluationQuestionTemplate;
use App\Models\EvaluationResult;
use App\Models\EvaluationType;
use App\Models\Instructor;
use App\Models\InstructorAssignment;
use App\Services\EvaluationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * "Evaluaciones" del coordinador.
 *
 *  - index(): muestra TODAS las instructorías finalizadas que el coordinador
 *    está habilitado a evaluar (sus instructores), con el estado de la
 *    evaluación del coordinador (pendiente / completada / promedio).
 *  - create($assignment): formulario con las 10 preguntas (7 score + 3 texto).
 *  - store($assignment): valida y delega al EvaluationService.
 *
 * El coordinador solo puede evaluar instructorías cuyo instructor le pertenezca
 * (coordinator_id de la tabla instructors). Cada coordinador ve únicamente
 * los instructores que él mismo creó, sin compartir entre coordinadores.
 */
class EvaluationController extends Controller
{
    public function index(Request $request): View
    {
        $coordinatorId = $this->coordinatorIdFor($request);

        // Instructorías finalizadas SOLO de los instructores creados por este coordinador.
        $assignments = InstructorAssignment::query()
            ->with(['classGroup', 'instructor.user'])
            ->where('status', EvaluationService::FINALIZED_STATUS)
            ->whereHas('instructor', function ($q) use ($coordinatorId) {
                $q->where('coordinator_id', $coordinatorId ?? -1);
            })
            ->orderByDesc('id')
            ->get();

        $type = EvaluationType::query()
            ->where('slug', EvaluationType::COORDINATOR)
            ->firstOrFail();

        // Map { assignment_id => EvaluationResult|null } -> evaluación del coordinador
        $resultsByAssignment = EvaluationResult::query()
            ->where('evaluation_type_id', $type->id)
            ->whereIn('assignment_id', $assignments->pluck('id'))
            ->get()
            ->keyBy('assignment_id');

        // Conteo de imports por (assignment, tipo) para las pills
        // "X evaluaciones de estudiantes" y "X del docente".
        $studentTypeId = EvaluationType::query()
            ->where('slug', EvaluationType::STUDENT)
            ->value('id');
        $teacherTypeId = EvaluationType::query()
            ->where('slug', EvaluationType::TEACHER)
            ->value('id');

        $importCounts = EvaluationResult::query()
            ->whereIn('assignment_id', $assignments->pluck('id'))
            ->whereIn('evaluation_type_id', array_filter([$studentTypeId, $teacherTypeId]))
            ->selectRaw('assignment_id, evaluation_type_id, COUNT(*) as total')
            ->groupBy('assignment_id', 'evaluation_type_id')
            ->get();

        $studentImports = [];
        $teacherImports = [];
        foreach ($importCounts as $row) {
            if ((int) $row->evaluation_type_id === (int) $studentTypeId) {
                $studentImports[$row->assignment_id] = (int) $row->total;
            } elseif ((int) $row->evaluation_type_id === (int) $teacherTypeId) {
                $teacherImports[$row->assignment_id] = (int) $row->total;
            }
        }

        return view('coordinator.evaluations.index', [
            'assignments' => $assignments,
            'resultsByAssignment' => $resultsByAssignment,
            'studentImports' => $studentImports,
            'teacherImports' => $teacherImports,
        ]);
    }

    public function create(Request $request, InstructorAssignment $assignment): View
    {
        $this->ensureCoordinatorCanEvaluate($request, $assignment);

        $type = EvaluationType::query()
            ->where('slug', EvaluationType::COORDINATOR)
            ->firstOrFail();

        $questions = $type->questions()->get();

        $existing = EvaluationResult::query()
            ->where('assignment_id', $assignment->id)
            ->where('evaluation_type_id', $type->id)
            ->with('answers')
            ->latest('id')
            ->first();

        $previousAnswers = [];
        if ($existing) {
            foreach ($existing->answers as $a) {
                $previousAnswers[$a->question_template_id] = [
                    // Redondear a entero para que la comparación con los radios sea exacta
                    'score' => $a->score_value !== null ? (int) round((float) $a->score_value) : null,
                    'text'  => $a->text_value,
                ];
            }
        }

        $assignment->loadMissing(['classGroup', 'instructor.user']);

        return view('coordinator.evaluations.create', [
            'assignment'     => $assignment,
            'type'           => $type,
            'questions'      => $questions,
            'previousAnswers'=> $previousAnswers,
            'isEditing'      => $existing !== null,
            'existingResult' => $existing,
        ]);
    }

    public function store(Request $request, InstructorAssignment $assignment, EvaluationService $service): RedirectResponse
    {
        $this->ensureCoordinatorCanEvaluate($request, $assignment);

        $type = EvaluationType::query()
            ->where('slug', EvaluationType::COORDINATOR)
            ->firstOrFail();

        // Obtener todas las preguntas con su max_score
        $questions = EvaluationQuestionTemplate::query()
            ->where('evaluation_type_id', $type->id)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        // Score obligatorio para todas las preguntas tipo 'score'.
        $scoreTemplates = $questions->filter(function ($q) {
            return $q->question_type === EvaluationQuestionTemplate::TYPE_SCORE;
        })->keys();

        // Validación dinámica basada en max_score de cada pregunta
        $rules = [
            'answers' => ['required', 'array'],
            'answers.*.text' => ['nullable', 'string', 'max:2000'],
        ];

        // Agregar regla de score dinámicamente por cada pregunta
        foreach ($scoreTemplates as $tid) {
            $maxScore = $questions[$tid]->max_score ?? 10;
            $rules["answers.$tid.score"] = ['nullable', 'numeric', 'min:1', "max:$maxScore"];
        }

        $data = $request->validate($rules);

        foreach ($scoreTemplates as $tid) {
            $value = $data['answers'][$tid]['score'] ?? null;
            if ($value === null || $value === '') {
                $maxScore = $questions[$tid]->max_score ?? 10;
                return back()
                    ->withInput()
                    ->withErrors([
                        "answers.$tid.score" => "Por favor califica todas las preguntas obligatorias (escala 1 a $maxScore).",
                    ]);
            }
        }

        try {
            $service->submitEvaluation(
                assignment: $assignment,
                type: $type,
                answersByTemplateId: $data['answers'],
                evaluator: $request->user(),
                source: EvaluationResult::SOURCE_INTERNAL,
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['general' => $e->getMessage()]);
        }

        return redirect()
            ->route('coordinator.evaluations.index')
            ->with('status', 'La evaluación del instructor se guardó correctamente.');
    }

    /**
     * Reglas combinadas:
     *  - el assignment debe estar Finalizado;
     *  - el instructor debe pertenecer al coordinador (o ser huérfano).
     */
    private function ensureCoordinatorCanEvaluate(Request $request, InstructorAssignment $assignment): void
    {
        if ($assignment->status !== EvaluationService::FINALIZED_STATUS) {
            abort(403, 'Esta instructoría aún no ha sido finalizada.');
        }

        $instructor = Instructor::query()->find($assignment->instructor_id);
        if (! $instructor) {
            throw new ModelNotFoundException;
        }

        $coordinatorId = $this->coordinatorIdFor($request);
        $owned = $coordinatorId !== null
            && (int) $instructor->coordinator_id === (int) $coordinatorId;

        if (! $owned) {
            abort(403, 'No tienes permiso para evaluar a este instructor.');
        }
    }

    /**
     * Recupera el id de la fila `coordinators` asociada al usuario logueado
     * (puede ser NULL si el usuario es admin/no coordinador).
     */
    private function coordinatorIdFor(Request $request): ?int
    {
        $coordinator = Coordinator::query()
            ->where('user_id', $request->user()->id)
            ->first();

        return $coordinator?->id;
    }
}
