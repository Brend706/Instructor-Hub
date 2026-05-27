<?php

namespace App\Services;

use App\Models\EvaluationAnswer;
use App\Models\EvaluationQuestionTemplate;
use App\Models\EvaluationResult;
use App\Models\EvaluationType;
use App\Models\InstructorAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Lógica de negocio del módulo de evaluaciones.
 *
 * Reglas centrales (validadas acá, no en el controlador):
 *   1. Una evaluación solo se puede realizar si el assignment está en
 *      estado 'Finalizado'.
 *   2. Para los tipos 'self' y 'coordinator' solo puede existir UN result
 *      por (assignment, evaluation_type). Para 'student' y 'teacher' se
 *      permiten múltiples (vendrán por import masivo).
 *   3. Las respuestas se validan contra los templates: cada pregunta
 *      'score' debe tener score_value entre 1 y max_score; 'text' acepta
 *      cadena (puede ser vacía si la pregunta no es obligatoria).
 *   4. Al cerrar el envío, total_score = promedio de los score_value de
 *      las respuestas tipo 'score' (en escala 1..max_score; las preguntas
 *      'text' no entran al promedio).
 */
class EvaluationService
{
    public const FINALIZED_STATUS = 'Finalizado';

    /**
     * Crea (o reemplaza) la evaluación de un instructor para un assignment.
     *
     * @param  array<int, array{score?: int|float|string|null, text?: string|null, selected_option?: string|null}>  $answersByTemplateId
     *         Mapa { question_template_id => { score?, text? } }
     */
    public function submitEvaluation(
        InstructorAssignment $assignment,
        EvaluationType $type,
        array $answersByTemplateId,
        ?User $evaluator,
        string $source = EvaluationResult::SOURCE_INTERNAL,
    ): EvaluationResult {
        $this->ensureAssignmentFinalized($assignment);

        // Si el tipo es self o coordinator y ya existe un result previo del
        // mismo evaluador, lo reemplazamos (borramos answers + result viejo).
        $singleResultTypes = [EvaluationType::SELF, EvaluationType::COORDINATOR];
        $shouldBeUnique = in_array($type->slug, $singleResultTypes, true);

        return DB::transaction(function () use (
            $assignment, $type, $answersByTemplateId, $evaluator, $source, $shouldBeUnique
        ) {
            if ($shouldBeUnique) {
                EvaluationResult::query()
                    ->where('assignment_id', $assignment->id)
                    ->where('evaluation_type_id', $type->id)
                    ->delete();
            }

            $result = EvaluationResult::create([
                'assignment_id' => $assignment->id,
                'instructor_id' => $assignment->instructor_id,
                'evaluation_type_id' => $type->id,
                'evaluator_user_id' => $evaluator?->id,
                'source' => $source,
                'total_score' => null,
                'submitted_at' => now(),
                'reviewed_by_admin' => false,
            ]);

            $templates = EvaluationQuestionTemplate::query()
                ->where('evaluation_type_id', $type->id)
                ->where('is_active', true)
                ->get()
                ->keyBy('id');

            foreach ($templates as $template) {
                $raw = $answersByTemplateId[$template->id] ?? [];
                EvaluationAnswer::create([
                    'evaluation_result_id' => $result->id,
                    'question_template_id' => $template->id,
                    'score_value' => $template->question_type === EvaluationQuestionTemplate::TYPE_SCORE
                        ? $this->clampScore($raw['score'] ?? null, $template->max_score ?? 5)
                        : null,
                    'text_value' => $template->question_type === EvaluationQuestionTemplate::TYPE_TEXT
                        ? (isset($raw['text']) ? trim((string) $raw['text']) : null)
                        : null,
                    'selected_option' => $raw['selected_option'] ?? null,
                ]);
            }

            $result->total_score = $this->computeTotalScore($result);
            $result->save();

            return $result->fresh(['answers.questionTemplate', 'evaluationType']);
        });
    }

    /**
     * Devuelve los tipos de evaluación todavía PENDIENTES para un assignment
     * dado el rol que está consultando. Para 'self', un instructor solo ve
     * el suyo y solo si no lo ha enviado. Para los tipos importados los
     * mostramos siempre (cada import crea un result nuevo).
     *
     * @return array<int, EvaluationType>
     */
    public function pendingTypesFor(InstructorAssignment $assignment, string $roleSlug): array
    {
        if ($assignment->status !== self::FINALIZED_STATUS) {
            return [];
        }

        $allowedSlugs = match ($roleSlug) {
            'instructor' => [EvaluationType::SELF],
            'coordinator' => [EvaluationType::COORDINATOR],
            'admin' => [
                EvaluationType::SELF,
                EvaluationType::COORDINATOR,
                EvaluationType::STUDENT,
                EvaluationType::TEACHER,
            ],
            default => [],
        };

        $types = EvaluationType::query()
            ->whereIn('slug', $allowedSlugs)
            ->where('is_active', true)
            ->get();

        $submitted = EvaluationResult::query()
            ->where('assignment_id', $assignment->id)
            ->pluck('evaluation_type_id')
            ->all();

        return $types
            ->reject(fn (EvaluationType $t) => in_array($t->slug, [EvaluationType::SELF, EvaluationType::COORDINATOR], true)
                && in_array($t->id, $submitted, true))
            ->values()
            ->all();
    }

    /**
     * Calcula el promedio de score_value (solo de respuestas tipo 'score').
     */
    public function computeTotalScore(EvaluationResult $result): ?float
    {
        $scores = $result->answers()
            ->whereNotNull('score_value')
            ->pluck('score_value');

        if ($scores->isEmpty()) {
            return null;
        }

        return round($scores->avg(), 2);
    }

    /**
     * Lanza si el assignment todavía está activo.
     */
    private function ensureAssignmentFinalized(InstructorAssignment $assignment): void
    {
        if ($assignment->status !== self::FINALIZED_STATUS) {
            throw new RuntimeException(
                'La instructoría todavía está activa. Debe estar finalizada para poder evaluarla.'
            );
        }
    }

    /**
     * Recorta el score al rango [1..max], devolviendo null si el valor no
     * es numérico (se trata como pregunta sin responder).
     */
    private function clampScore(mixed $value, int $max): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value)) {
            return null;
        }
        $n = (float) $value;
        if ($n < 1) {
            $n = 1;
        }
        if ($n > $max) {
            $n = $max;
        }

        return round($n, 2);
    }
}
