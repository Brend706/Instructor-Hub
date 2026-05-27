<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EvaluationQuestionTemplate;
use App\Models\EvaluationType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CRUD admin de las plantillas de preguntas (`evaluation_question_templates`).
 *
 *  - index($typeSlug): lista las preguntas del tipo elegido (con tabs para
 *    cambiar entre los 4 tipos: self, coordinator, student, teacher).
 *  - store($typeSlug): crea una pregunta nueva (order_index = max + 1).
 *  - update($question): edita texto / tipo / max_score / activa.
 *  - toggle($question): activa o desactiva sin borrar.
 *  - move($question, direction): sube o baja el orden de la pregunta.
 *  - destroy($question): si la pregunta NO tiene respuestas registradas se
 *    elimina; si ya tiene respuestas, solo se DESACTIVA para no romper
 *    los promedios históricos.
 *
 * El admin puede modificar el catálogo de preguntas sin tocar el código.
 * Las preguntas nuevas aparecen al instante en los formularios de
 * instructor / coordinador y en las plantillas de import del coordinador.
 */
class EvaluationQuestionController extends Controller
{
    public function index(Request $request, string $typeSlug): View
    {
        $type = $this->resolveType($typeSlug);

        $questions = EvaluationQuestionTemplate::query()
            ->where('evaluation_type_id', $type->id)
            ->withCount('answers')
            ->orderBy('order_index')
            ->orderBy('id')
            ->get();

        $allTypes = EvaluationType::query()
            ->withCount(['questions as questions_count' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('id')
            ->get();

        return view('admin.evaluations.questions.index', [
            'type' => $type,
            'questions' => $questions,
            'allTypes' => $allTypes,
        ]);
    }

    public function store(Request $request, string $typeSlug): RedirectResponse
    {
        $type = $this->resolveType($typeSlug);

        $data = $request->validate([
            'question_text' => ['required', 'string', 'max:500'],
            'question_type' => ['required', 'in:score,text'],
            'max_score' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        $maxOrder = EvaluationQuestionTemplate::query()
            ->where('evaluation_type_id', $type->id)
            ->max('order_index');

        EvaluationQuestionTemplate::create([
            'evaluation_type_id' => $type->id,
            'question_text' => $data['question_text'],
            'question_type' => $data['question_type'],
            'max_score' => $data['question_type'] === 'score'
                ? ($data['max_score'] ?? 5)
                : null,
            'order_index' => ((int) ($maxOrder ?? 0)) + 1,
            'is_active' => true,
        ]);

        return back()->with('status', 'Pregunta creada correctamente.');
    }

    public function update(Request $request, EvaluationQuestionTemplate $question): RedirectResponse
    {
        $data = $request->validate([
            'question_text' => ['required', 'string', 'max:500'],
            'question_type' => ['required', 'in:score,text'],
            'max_score' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        $question->question_text = $data['question_text'];
        $question->question_type = $data['question_type'];
        $question->max_score = $data['question_type'] === 'score'
            ? ($data['max_score'] ?? 5)
            : null;
        $question->save();

        return back()->with('status', 'Pregunta actualizada.');
    }

    public function toggle(EvaluationQuestionTemplate $question): RedirectResponse
    {
        $question->is_active = ! $question->is_active;
        $question->save();

        return back()->with(
            'status',
            $question->is_active ? 'Pregunta activada.' : 'Pregunta desactivada.'
        );
    }

    public function move(Request $request, EvaluationQuestionTemplate $question, string $direction): RedirectResponse
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            abort(404);
        }

        // Trae las preguntas vecinas ordenadas y permuta order_index.
        $neighborQuery = EvaluationQuestionTemplate::query()
            ->where('evaluation_type_id', $question->evaluation_type_id);

        $neighbor = $direction === 'up'
            ? (clone $neighborQuery)
                ->where('order_index', '<', $question->order_index)
                ->orderByDesc('order_index')
                ->first()
            : (clone $neighborQuery)
                ->where('order_index', '>', $question->order_index)
                ->orderBy('order_index')
                ->first();

        if (! $neighbor) {
            return back();
        }

        // Swap clásico.
        $temp = $question->order_index;
        $question->order_index = $neighbor->order_index;
        $neighbor->order_index = $temp;
        $question->save();
        $neighbor->save();

        return back();
    }

    public function destroy(EvaluationQuestionTemplate $question): RedirectResponse
    {
        // Si ya hay answers, no podemos borrar sin romper resultados históricos.
        $hasAnswers = $question->answers()->exists();

        if ($hasAnswers) {
            $question->is_active = false;
            $question->save();

            return back()->with(
                'status',
                'La pregunta ya tiene respuestas registradas, se desactivó en lugar de eliminarla.'
            );
        }

        $question->delete();

        return back()->with('status', 'Pregunta eliminada.');
    }

    private function resolveType(string $slug): EvaluationType
    {
        return EvaluationType::query()
            ->where('slug', $slug)
            ->firstOrFail();
    }
}
