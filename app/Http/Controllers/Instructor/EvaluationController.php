<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\EvaluationQuestionTemplate;
use App\Models\EvaluationResult;
use App\Models\EvaluationType;
use App\Models\Instructor;
use App\Models\InstructorAssignment;
use App\Models\User;
use App\Notifications\SelfEvaluationSubmitted;
use App\Services\EvaluationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * "Evaluaciones" del instructor.
 *
 *  - index(): lista sus instructorías FINALIZADAS con el estado de la
 *    autoevaluación (pendiente / completada / fecha de envío).
 *  - create($assignment): muestra el formulario de autoevaluación con
 *    las preguntas activas y, si ya existía un envío previo, lo
 *    precarga (sirve también como "Editar mi respuesta").
 *  - store($assignment): valida y delega en EvaluationService.
 */
class EvaluationController extends Controller
{
    public function index(Request $request): View
    {
        $instructor = $this->currentInstructor($request);

        $assignments = $instructor->instructorAssignments()
            ->with('classGroup')
            ->orderByDesc('id')
            ->get();

        $selfType = EvaluationType::query()
            ->where('slug', EvaluationType::SELF)
            ->firstOrFail();

        // Map { assignment_id => EvaluationResult|null } para saber si ya envió.
        $resultsByAssignment = EvaluationResult::query()
            ->where('evaluation_type_id', $selfType->id)
            ->whereIn('assignment_id', $assignments->pluck('id'))
            ->get()
            ->keyBy('assignment_id');

        return view('instructors.evaluations.index', [
            'assignments' => $assignments,
            'resultsByAssignment' => $resultsByAssignment,
        ]);
    }

    public function create(Request $request, InstructorAssignment $assignment): View
    {
        $instructor = $this->currentInstructor($request);
        $this->ensureOwns($instructor, $assignment);
        $this->ensureFinalized($assignment);

        $type = EvaluationType::query()
            ->where('slug', EvaluationType::SELF)
            ->firstOrFail();

        $questions = $type->questions()->get();

        // Pre-cargar respuestas previas para permitir edición.
        $existing = EvaluationResult::query()
            ->where('assignment_id', $assignment->id)
            ->where('evaluation_type_id', $type->id)
            ->with('answers')
            ->latest('id')
            ->first();

        // Map { template_id => ['score' => ?, 'text' => ?] }
        $previousAnswers = [];
        if ($existing) {
            foreach ($existing->answers as $a) {
                $previousAnswers[$a->question_template_id] = [
                    'score' => $a->score_value,
                    'text' => $a->text_value,
                ];
            }
        }

        $assignment->loadMissing('classGroup');

        return view('instructors.evaluations.create', [
            'assignment' => $assignment,
            'type' => $type,
            'questions' => $questions,
            'previousAnswers' => $previousAnswers,
            'isEditing' => $existing !== null,
        ]);
    }

    public function store(Request $request, InstructorAssignment $assignment, EvaluationService $service): RedirectResponse
    {
        $instructor = $this->currentInstructor($request);
        $this->ensureOwns($instructor, $assignment);
        $this->ensureFinalized($assignment);

        $type = EvaluationType::query()
            ->where('slug', EvaluationType::SELF)
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
            $result = $service->submitEvaluation(
                assignment: $assignment,
                type: $type,
                answersByTemplateId: $data['answers'],
                evaluator: $request->user(),
                source: EvaluationResult::SOURCE_INTERNAL,
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['general' => $e->getMessage()]);
        }

        // Notificar al coordinador encargado del instructor.
        // Si el instructor no tiene coordinador asignado (caso de huérfanos
        // heredados), simplemente no se envía nada y no se rompe el flujo.
        $this->notifyCoordinator($instructor, $assignment, $result);

        return redirect()
            ->route('instructor.evaluations.index')
            ->with('status', 'Tu autoevaluación se envió correctamente.');
    }

    /**
     * Envía la notificación al usuario User del coordinador propietario
     * del instructor. Aislada en un helper para que el `store()` quede
     * limpio y para poder protegerla con un try/catch (la notificación
     * nunca debe romper el envío de la evaluación).
     */
    private function notifyCoordinator(Instructor $instructor, InstructorAssignment $assignment, EvaluationResult $result): void
    {
        if (! $instructor->coordinator_id) {
            return;
        }

        try {
            $instructor->loadMissing('coordinator.user');
            $coordinatorUser = $instructor->coordinator?->user;

            if ($coordinatorUser instanceof User) {
                Notification::send(
                    $coordinatorUser,
                    new SelfEvaluationSubmitted($instructor, $assignment, $result),
                );
            }
        } catch (\Throwable) {
            // Silencioso: no interrumpimos el flujo principal si la
            // notificación falla por cualquier razón secundaria.
        }
    }

    private function currentInstructor(Request $request): Instructor
    {
        return Instructor::query()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }

    private function ensureOwns(Instructor $instructor, InstructorAssignment $assignment): void
    {
        if ((int) $assignment->instructor_id !== (int) $instructor->id) {
            throw new ModelNotFoundException;
        }
    }

    private function ensureFinalized(InstructorAssignment $assignment): void
    {
        if ($assignment->status !== EvaluationService::FINALIZED_STATUS) {
            abort(403, 'Esta instructoría aún no ha sido finalizada por la coordinación.');
        }
    }
}
