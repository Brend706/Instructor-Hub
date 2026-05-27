<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\Coordinator;
use App\Models\EvaluationResult;
use App\Models\EvaluationType;
use App\Models\Instructor;
use App\Models\InstructorAssignment;
use App\Services\EvaluationImportService;
use App\Services\EvaluationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use RuntimeException;

/**
 * Import masivo de evaluaciones (estudiantes / docente titular) desde Excel.
 *
 * Solo se permiten los tipos 'student' y 'teacher' aquí. Self/coordinator
 * se completan desde sus formularios internos.
 */
class EvaluationImportController extends Controller
{
    /** Tipos que aceptan import via Excel. */
    private const IMPORTABLE_TYPES = [EvaluationType::STUDENT, EvaluationType::TEACHER];

    public function show(Request $request, InstructorAssignment $assignment, string $typeSlug): View
    {
        $type = $this->resolveType($typeSlug);
        $this->ensureCoordinatorCanImport($request, $assignment);

        $assignment->loadMissing(['classGroup', 'instructor.user']);

        $previousImports = EvaluationResult::query()
            ->where('assignment_id', $assignment->id)
            ->where('evaluation_type_id', $type->id)
            ->where('source', EvaluationResult::SOURCE_CSV)
            ->latest('id')
            ->get();

        $averageScore = $previousImports->whereNotNull('total_score')->avg('total_score');

        return view('coordinator.evaluations.import', [
            'assignment' => $assignment,
            'type' => $type,
            'previousImports' => $previousImports,
            'averageScore' => $averageScore !== null ? round($averageScore, 2) : null,
        ]);
    }

    public function template(Request $request, InstructorAssignment $assignment, string $typeSlug, EvaluationImportService $importer): Response
    {
        $type = $this->resolveType($typeSlug);
        $this->ensureCoordinatorCanImport($request, $assignment);

        $file = $importer->buildTemplate($assignment, $type);

        return response($file['content'], 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$file['filename'].'"',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    public function store(Request $request, InstructorAssignment $assignment, string $typeSlug, EvaluationImportService $importer): RedirectResponse
    {
        $type = $this->resolveType($typeSlug);
        $this->ensureCoordinatorCanImport($request, $assignment);

        $data = $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ]);

        try {
            $report = $importer->importFromUploadedFile(
                localPath: $data['archivo']->getRealPath(),
                assignment: $assignment,
                type: $type,
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['archivo' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return back()->withErrors([
                'archivo' => 'No se pudo procesar el archivo: '.$e->getMessage(),
            ]);
        }

        return back()->with([
            'status' => sprintf(
                'Import completado. Filas importadas: %d, omitidas (vacías): %d.',
                $report['imported'],
                $report['skipped'],
            ),
            'import_errors' => $report['errors'],
        ]);
    }

    private function resolveType(string $slug): EvaluationType
    {
        if (! in_array($slug, self::IMPORTABLE_TYPES, true)) {
            abort(404);
        }

        return EvaluationType::query()->where('slug', $slug)->firstOrFail();
    }

    private function ensureCoordinatorCanImport(Request $request, InstructorAssignment $assignment): void
    {
        if ($assignment->status !== EvaluationService::FINALIZED_STATUS) {
            abort(403, 'Esta instructoría aún no ha sido finalizada.');
        }

        $instructor = Instructor::query()->find($assignment->instructor_id);
        if (! $instructor) {
            throw new ModelNotFoundException;
        }

        $coordinator = Coordinator::query()
            ->where('user_id', $request->user()->id)
            ->first();

        $coordinatorId = $coordinator?->id;
        $owned = $instructor->coordinator_id === null
            || ($coordinatorId && (int) $instructor->coordinator_id === (int) $coordinatorId);

        if (! $owned) {
            abort(403, 'No tienes permiso para importar evaluaciones de este instructor.');
        }
    }
}
