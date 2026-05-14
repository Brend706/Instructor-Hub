<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\ClassGroup;
use App\Models\Student;
use App\Services\StudentExcelImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StudentImportController extends Controller
{
    public function __construct(
        private StudentExcelImportService $importService
    ) {}

    /**
     * Vista “Agregar estudiantes” con datos del grupo e instructor asignado.
     */
    public function show(ClassGroup $group): View
    {
        $group->load(['instructorAssignments.instructor.user']);
        $assignment = $group->instructorAssignments->first();
        $instructor = $assignment?->instructor;

        return view('coordinator.groups.students', [
            'group' => $group,
            'instructorName' => $instructor?->user?->name,
            'instructorMajor' => $instructor?->major,
        ]);
    }

    /**
     * Vista previa: recibe el archivo por multipart y llama a `parse()` (PhpSpreadsheet + `parseMatrix`).
     *
     * En la UI actual, el coordinador usa esto sobre todo con **.csv**. No persiste nada en BD.
     */
    public function preview(Request $request, ClassGroup $group): JsonResponse
    {
        $request->validate([
            'file' => $this->spreadsheetFileRules(),
        ]);

        try {
            $parsed = $this->importService->parse($request->file('file'), $group->id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $errors = [];
        foreach ($parsed['rows'] as $row) {
            if (! $row['ok'] && ($row['error'] ?? '') !== '') {
                $errors[] = 'Fila '.$row['sheet_row'].' — '.$row['error'];
            }
        }

        return response()->json([
            'filename' => $request->file('file')->getClientOriginalName(),
            'rows' => $parsed['rows'],
            'summary' => $parsed['summary'],
            'errors' => $errors,
        ]);
    }

    /**
     * Vista previa para **.xlsx / .xls** cuando el navegador ya convirtió la hoja en una matriz (SheetJS).
     *
     * Evita depender de `extension=zip` en PHP para abrir el xlsx en servidor. Misma validación
     * que el otro preview vía `parseMatrix()`. No guarda en BD.
     */
    public function previewMatrix(Request $request, ClassGroup $group): JsonResponse
    {
        $validated = $request->validate([
            'matrix' => ['required', 'array', 'max:5000'],
            'matrix.*' => ['array'],
            'filename' => ['nullable', 'string', 'max:255'],
        ], [
            'matrix.required' => 'No se recibieron filas del archivo.',
            'matrix.max' => 'El archivo tiene demasiadas filas (máximo 5000).',
        ]);

        $parsed = $this->importService->parseMatrix($validated['matrix'], $group->id);

        $errors = [];
        foreach ($parsed['rows'] as $row) {
            if (! $row['ok'] && ($row['error'] ?? '') !== '') {
                $errors[] = 'Fila '.$row['sheet_row'].' — '.$row['error'];
            }
        }

        return response()->json([
            'filename' => $validated['filename'] ?? 'archivo.xlsx',
            'rows' => $parsed['rows'],
            'summary' => $parsed['summary'],
            'errors' => $errors,
        ]);
    }

    /**
     * Importa desde **subida multipart** (mismo criterio que `preview` con archivo): vuelve a leer el fichero
     * con `parse()` y persiste filas con `ok === true` en `students`.
     *
     * Flujo asociado en la vista: **Confirmar** con `.csv` (u otro tipo si PhpSpreadsheet pudo leerlo en servidor).
     * Para **.xlsx/.xls** leídos en cliente, la vista usa {@see importMatrix()} en su lugar.
     */
    public function import(Request $request, ClassGroup $group): RedirectResponse
    {
        $request->validate([
            'file' => $this->spreadsheetFileRules(),
        ]);

        try {
            $parsed = $this->importService->parse($request->file('file'), $group->id);
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('coordinator.groups.students', $group)
                ->withErrors(['file' => $e->getMessage()]);
        }

        $imported = 0;

        DB::transaction(function () use ($parsed, $group, &$imported) {
            foreach ($parsed['rows'] as $row) {
                if (! $row['ok']) {
                    continue;
                }

                $carnet = trim((string) $row['carnet']);
                $name = trim((string) $row['name']);
                $email = trim((string) $row['email']);

                if (Student::existsDuplicateInGroup($group->id, $carnet, $email)) {
                    continue;
                }

                Student::query()->create([
                    'class_group_id' => $group->id,
                    'carnet' => $carnet,
                    'name' => $name,
                    'email' => $email,
                ]);

                // Solo contamos filas válidas que efectivamente intentamos persistir.
                $imported++;
            }
        });

        if ($imported === 0) {
            return redirect()
                ->route('coordinator.groups.students', $group)
                ->withErrors([
                    'file' => 'No se importó ningún estudiante nuevo. Revisa el archivo (filas válidas, sin duplicados en el archivo ni carnet/correo ya registrados en este grupo).',
                ]);
        }

        return redirect()
            ->route('coordinator.groups.index')
            ->with('success', 'Se importaron '.$imported.' estudiante(s) al grupo '.$group->name.'.');
    }

    /**
     * Importa tras vista previa **cliente**: recibe `matrix_json` (misma matriz que generó la vista previa)
     * y vuelve a ejecutar `parseMatrix()` en servidor para validar; solo guarda filas válidas.
     *
     * Así no se confía en flags armados en el navegador: el servidor recalcula errores/pares válidos.
     */
    public function importMatrix(Request $request, ClassGroup $group): RedirectResponse
    {
        $request->validate([
            'matrix_json' => ['required', 'string'],
        ], [
            'matrix_json.required' => 'No se recibieron datos para importar.',
        ]);

        $matrix = json_decode($request->input('matrix_json'), true);
        if (! is_array($matrix)) {
            return redirect()
                ->route('coordinator.groups.students', $group)
                ->withErrors(['file' => 'Los datos del archivo no son válidos. Vuelve a cargar la vista previa.']);
        }

        if (count($matrix) > 5000) {
            return redirect()
                ->route('coordinator.groups.students', $group)
                ->withErrors(['file' => 'El archivo tiene demasiadas filas (máximo 5000).']);
        }

        $parsed = $this->importService->parseMatrix($matrix, $group->id);

        $imported = 0;

        DB::transaction(function () use ($parsed, $group, &$imported) {
            foreach ($parsed['rows'] as $row) {
                if (! $row['ok']) {
                    continue;
                }

                $carnet = trim((string) $row['carnet']);
                $name = trim((string) $row['name']);
                $email = trim((string) $row['email']);

                if (Student::existsDuplicateInGroup($group->id, $carnet, $email)) {
                    continue;
                }

                Student::query()->create([
                    'class_group_id' => $group->id,
                    'carnet' => $carnet,
                    'name' => $name,
                    'email' => $email,
                ]);

                $imported++;
            }
        });

        if ($imported === 0) {
            return redirect()
                ->route('coordinator.groups.students', $group)
                ->withErrors([
                    'file' => 'No se importó ningún estudiante nuevo. Revisa el archivo (filas válidas, sin duplicados en el archivo ni carnet/correo ya registrados en este grupo).',
                ]);
        }

        return redirect()
            ->route('coordinator.groups.index')
            ->with('success', 'Se importaron '.$imported.' estudiante(s) al grupo '.$group->name.'.');
    }

    /**
     * Reglas del input `file` solo para rutas **multipart** (`preview` e `import` con archivo subido).
     * No aplica a `preview-matrix` / `import-matrix` (JSON).
     *
     * @return array<int, \Closure|non-falsy-string>
     */
    private function spreadsheetFileRules(): array
    {
        return [
            'required',
            'file',
            'max:10240',
            function (string $attribute, mixed $value, \Closure $fail): void {
                if (! $value instanceof UploadedFile) {
                    return;
                }
                $ext = strtolower($value->getClientOriginalExtension());
                if (! in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
                    $fail('El archivo debe tener extensión .xlsx, .xls o .csv.');
                }
            },
        ];
    }
}
