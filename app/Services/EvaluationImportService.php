<?php

namespace App\Services;

use App\Models\EvaluationAnswer;
use App\Models\EvaluationQuestionTemplate;
use App\Models\EvaluationResult;
use App\Models\EvaluationType;
use App\Models\InstructorAssignment;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

/**
 * Import masivo de evaluaciones (estudiantes / docente titular) desde Excel.
 *
 * Formato de la plantilla (1 fila = 1 evaluación):
 *   col A: Identificador (carnet, nombre del docente, etc.) — opcional, solo auditoría.
 *   col B: Nombre / cargo                                     — opcional, solo auditoría.
 *   col C en adelante: 1 columna por pregunta, en el orden de `order_index`.
 *      - Las preguntas tipo 'score' aceptan números 1..max_score.
 *      - Las preguntas tipo 'text'  aceptan texto libre.
 *
 * La primera fila siempre es el header con el texto de la pregunta.
 *
 * Reglas:
 *   - El assignment debe estar 'Finalizado'.
 *   - Filas completamente vacías se ignoran.
 *   - Cada fila válida crea UN evaluation_results con source='csv_import',
 *     evaluator_user_id=NULL, submitted_at=now(), y sus respectivas answers.
 *   - El total_score de cada fila se calcula como promedio de los scores.
 */
class EvaluationImportService
{
    /**
     * @return array{filename:string,content:string}
     */
    public function buildTemplate(InstructorAssignment $assignment, EvaluationType $type): array
    {
        $questions = $type->questions()->get();

        $spreadsheet = new Spreadsheet;

        // ── Hoja "Respuestas" ─────────────────────────────────────
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Respuestas');

        $headers = ['Identificador (opcional)', 'Nombre (opcional)'];
        foreach ($questions as $q) {
            $suffix = $q->question_type === EvaluationQuestionTemplate::TYPE_SCORE
                ? ' ('.'1-'.($q->max_score ?? 5).')'
                : ' (texto)';
            $headers[] = $q->question_text.$suffix;
        }

        foreach ($headers as $i => $text) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue($col.'1', $text);
            $sheet->getColumnDimension($col)->setWidth(28);
        }
        $sheet->getRowDimension(1)->setRowHeight(34);

        $headerRange = 'A1:'.Coordinate::stringFromColumnIndex(count($headers)).'1';
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF4F46E5'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCBD5E1']],
            ],
        ]);
        $sheet->freezePane('A2');

        // 3 filas vacías de ejemplo con bordes finos para que se vea editable.
        $exampleRange = 'A2:'.Coordinate::stringFromColumnIndex(count($headers)).'4';
        $sheet->getStyle($exampleRange)->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['argb' => 'FFE2E8F0']],
            ],
        ]);

        // ── Hoja "Instrucciones" ──────────────────────────────────
        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle('Instrucciones');
        $lines = [
            ['Plantilla de '.$type->name],
            [''],
            ['Cada fila = 1 evaluación completa.'],
            ['Las dos primeras columnas son opcionales y sirven solo para identificar al evaluador.'],
            ['Las preguntas marcadas con "(1-5)" aceptan un número del 1 al 5.'],
            ['Las preguntas marcadas con "(texto)" aceptan texto libre.'],
            ['Las filas vacías se ignoran al importar.'],
            [''],
            ['Instructoría:'],
            ['  Instructor: '.($assignment->instructor?->user?->name ?? '—')],
            ['  Grupo:      '.($assignment->classGroup?->name ?? '—')],
            ['  Ciclo:      '.($assignment->classGroup?->semester ?? '—')],
            [''],
            ['Preguntas:'],
        ];
        foreach ($questions as $i => $q) {
            $kind = $q->question_type === EvaluationQuestionTemplate::TYPE_SCORE
                ? 'puntaje 1-'.($q->max_score ?? 5)
                : 'texto libre';
            $lines[] = ['  '.($i + 1).'. '.$q->question_text.' ('.$kind.')'];
        }
        foreach ($lines as $row => $cols) {
            foreach ($cols as $col => $val) {
                $cell = Coordinate::stringFromColumnIndex($col + 1).($row + 1);
                $instructions->setCellValue($cell, $val);
            }
        }
        $instructions->getColumnDimension('A')->setWidth(80);
        $instructions->getStyle('A1')->getFont()->setBold(true)->setSize(13);

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean() ?: '';

        $filename = sprintf(
            'plantilla_%s_%s.xlsx',
            $type->slug,
            preg_replace('/[^A-Za-z0-9_-]+/', '_', $assignment->classGroup?->name ?? 'grupo')
        );

        return ['filename' => $filename, 'content' => $content];
    }

    /**
     * Procesa el archivo subido y crea un EvaluationResult por cada fila válida.
     *
     * @return array{imported:int,skipped:int,errors:array<int,string>}
     */
    public function importFromUploadedFile(
        string $localPath,
        InstructorAssignment $assignment,
        EvaluationType $type,
    ): array {
        if ($assignment->status !== EvaluationService::FINALIZED_STATUS) {
            throw new RuntimeException('La instructoría todavía está activa. Debe finalizarse antes de importar evaluaciones.');
        }

        $spreadsheet = IOFactory::load($localPath);
        $sheet = $spreadsheet->getSheetByName('Respuestas') ?? $spreadsheet->getActiveSheet();

        $questions = $type->questions()->get()->values(); // orden por order_index gracias al scope.
        $questionsCount = $questions->count();
        if ($questionsCount === 0) {
            throw new RuntimeException('Este tipo de evaluación no tiene preguntas configuradas.');
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];

        $highestRow = $sheet->getHighestDataRow();

        DB::transaction(function () use (
            $sheet, $highestRow, $questions, $questionsCount, $type, $assignment, &$imported, &$skipped, &$errors
        ) {
            // Empieza en 2 porque la fila 1 es header.
            for ($row = 2; $row <= $highestRow; $row++) {
                $rowPayload = $this->readRow($sheet, $row, $questionsCount);

                if ($this->isEmptyRow($rowPayload)) {
                    $skipped++;

                    continue;
                }

                try {
                    $result = EvaluationResult::create([
                        'assignment_id' => $assignment->id,
                        'instructor_id' => $assignment->instructor_id,
                        'evaluation_type_id' => $type->id,
                        'evaluator_user_id' => null,
                        'source' => EvaluationResult::SOURCE_CSV,
                        'total_score' => null,
                        'submitted_at' => now(),
                        'reviewed_by_admin' => false,
                    ]);

                    $sumScore = 0.0;
                    $scoreCount = 0;
                    foreach ($questions as $i => $template) {
                        $raw = $rowPayload['questions'][$i] ?? null;

                        if ($template->question_type === EvaluationQuestionTemplate::TYPE_SCORE) {
                            $score = $this->parseScore($raw, $template->max_score ?? 5);
                            if ($score !== null) {
                                $sumScore += $score;
                                $scoreCount++;
                            }
                            EvaluationAnswer::create([
                                'evaluation_result_id' => $result->id,
                                'question_template_id' => $template->id,
                                'score_value' => $score,
                                'text_value' => null,
                                'selected_option' => null,
                            ]);
                        } else {
                            $text = $raw === null ? null : trim((string) $raw);
                            if ($text === '') {
                                $text = null;
                            }
                            if ($text !== null && mb_strlen($text) > 2000) {
                                $text = mb_substr($text, 0, 2000);
                            }
                            EvaluationAnswer::create([
                                'evaluation_result_id' => $result->id,
                                'question_template_id' => $template->id,
                                'score_value' => null,
                                'text_value' => $text,
                                'selected_option' => null,
                            ]);
                        }
                    }

                    $result->total_score = $scoreCount > 0 ? round($sumScore / $scoreCount, 2) : null;
                    $result->save();

                    $imported++;
                } catch (\Throwable $e) {
                    $errors[] = "Fila $row: ".$e->getMessage();
                }
            }
        });

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Lee una fila del Excel y devuelve la estructura normalizada:
     *   ['id' => ..., 'name' => ..., 'questions' => [valor_pregunta_1, valor_pregunta_2, ...]]
     *
     * @return array{id: mixed, name: mixed, questions: array<int, mixed>}
     */
    private function readRow(Worksheet $sheet, int $row, int $questionsCount): array
    {
        $id = $sheet->getCell('A'.$row)->getValue();
        $name = $sheet->getCell('B'.$row)->getValue();

        $values = [];
        for ($i = 0; $i < $questionsCount; $i++) {
            // Columnas C, D, E, ... (índice 3, 4, 5, ...).
            $col = Coordinate::stringFromColumnIndex($i + 3);
            $values[] = $sheet->getCell($col.$row)->getValue();
        }

        return ['id' => $id, 'name' => $name, 'questions' => $values];
    }

    /**
     * Una fila se considera vacía si TODAS las respuestas están vacías
     * (no importa que el identificador o el nombre estén llenos).
     *
     * @param  array{id: mixed, name: mixed, questions: array<int, mixed>}  $payload
     */
    private function isEmptyRow(array $payload): bool
    {
        foreach ($payload['questions'] as $v) {
            if ($v !== null && trim((string) $v) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Convierte el valor de una celda a un score válido [1..max]. Devuelve
     * NULL si la celda está vacía o no es numérica.
     */
    private function parseScore(mixed $value, int $max): ?float
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
