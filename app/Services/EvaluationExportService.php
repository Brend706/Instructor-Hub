<?php

namespace App\Services;

use App\Models\EvaluationQuestionTemplate;
use App\Models\EvaluationResult;
use App\Models\EvaluationType;
use App\Models\InstructorAssignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Genera el .xlsx consolidado de un instructor_assignment con TODAS las
 * evaluaciones registradas (autoevaluación, coordinador, estudiantes, docente).
 *
 * Estructura del archivo:
 *   - Hoja 1: "Resumen"  -> promedios por tipo + promedio general + #evaluaciones.
 *   - Hoja N: una por cada EvaluationType que tenga al menos un result asociado.
 *     Filas = un result. Columnas: # | Fecha | Fuente | Promedio | preguntas...
 */
class EvaluationExportService
{
    /**
     * @return array{filename:string,content:string}
     */
    public function buildConsolidated(InstructorAssignment $assignment): array
    {
        $assignment->loadMissing(['classGroup', 'instructor.user']);

        $types = EvaluationType::query()
            ->with(['questions'])
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        $resultsByType = EvaluationResult::query()
            ->where('assignment_id', $assignment->id)
            ->with(['answers.questionTemplate', 'evaluator'])
            ->get()
            ->groupBy('evaluation_type_id');

        $spreadsheet = new Spreadsheet;
        $summary = $spreadsheet->getActiveSheet();
        $summary->setTitle('Resumen');

        $this->renderSummary($summary, $assignment, $types, $resultsByType);

        foreach ($types as $type) {
            $typeResults = $resultsByType[$type->id] ?? collect();
            if ($typeResults->isEmpty()) {
                continue;
            }
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($this->sheetSafeName($type->name));
            $this->renderTypeSheet($sheet, $type, $typeResults);
        }

        $spreadsheet->setActiveSheetIndex(0);

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean() ?: '';

        $name = $assignment->instructor?->user?->name ?? 'instructor';
        $group = $assignment->classGroup?->name ?? 'grupo';
        $filename = sprintf(
            'evaluaciones_%s_%s.xlsx',
            preg_replace('/[^A-Za-z0-9_-]+/', '_', $name),
            preg_replace('/[^A-Za-z0-9_-]+/', '_', $group),
        );

        return ['filename' => $filename, 'content' => $content];
    }

    /**
     * Hoja "Resumen": metadatos del assignment + tabla con promedios por tipo.
     *
     * @param  \Illuminate\Support\Collection<int,EvaluationType>  $types
     * @param  \Illuminate\Support\Collection<int,\Illuminate\Support\Collection<int,EvaluationResult>>  $resultsByType
     */
    private function renderSummary(Worksheet $sheet, InstructorAssignment $assignment, $types, $resultsByType): void
    {
        $sheet->setCellValue('A1', 'Consolidado de evaluaciones');
        $sheet->mergeCells('A1:D1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getRowDimension(1)->setRowHeight(22);

        $meta = [
            ['Instructor', $assignment->instructor?->user?->name ?? '—'],
            ['Grupo', $assignment->classGroup?->name ?? '—'],
            ['Ciclo', $assignment->classGroup?->semester ?? '—'],
            ['Estado', $assignment->status ?? '—'],
        ];
        $row = 3;
        foreach ($meta as [$k, $v]) {
            $sheet->setCellValue('A'.$row, $k);
            $sheet->setCellValue('B'.$row, $v);
            $sheet->getStyle('A'.$row)->getFont()->setBold(true);
            $row++;
        }

        $row += 1;
        $headerRow = $row;
        $sheet->setCellValue('A'.$row, 'Tipo de evaluación');
        $sheet->setCellValue('B'.$row, 'Evaluaciones');
        $sheet->setCellValue('C'.$row, 'Promedio');
        $sheet->setCellValue('D'.$row, 'Última fecha');
        $sheet->getStyle('A'.$row.':D'.$row)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF4F46E5'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCBD5E1']],
            ],
        ]);
        $row++;

        $totalAvgWeighted = 0.0;
        $totalCount = 0;
        $firstDataRow = $row;
        foreach ($types as $type) {
            $list = $resultsByType[$type->id] ?? collect();
            $count = $list->count();
            $avg = $count > 0
                ? round((float) $list->whereNotNull('total_score')->avg('total_score'), 2)
                : null;
            $lastDate = $count > 0
                ? optional($list->sortByDesc('submitted_at')->first())->submitted_at
                : null;

            $sheet->setCellValue('A'.$row, $type->name);
            $sheet->setCellValue('B'.$row, $count);
            $sheet->setCellValue('C'.$row, $avg !== null ? $avg : '—');
            $sheet->setCellValue('D'.$row, $lastDate?->translatedFormat('d M Y H:i') ?? '—');

            if ($avg !== null && $count > 0) {
                $totalAvgWeighted += $avg * $count;
                $totalCount += $count;
            }
            $row++;
        }

        // Fila total
        $sheet->setCellValue('A'.$row, 'Promedio general');
        $sheet->setCellValue('B'.$row, $totalCount);
        $sheet->setCellValue('C'.$row, $totalCount > 0
            ? round($totalAvgWeighted / $totalCount, 2)
            : '—'
        );
        $sheet->setCellValue('D'.$row, '');
        $sheet->getStyle('A'.$row.':D'.$row)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFEEF2FF'],
            ],
        ]);

        // Bordes a toda la tabla.
        $sheet->getStyle('A'.$headerRow.':D'.$row)->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCBD5E1']],
            ],
        ]);

        $sheet->getColumnDimension('A')->setWidth(34);
        $sheet->getColumnDimension('B')->setWidth(16);
        $sheet->getColumnDimension('C')->setWidth(16);
        $sheet->getColumnDimension('D')->setWidth(22);
    }

    /**
     * Una hoja por tipo: cada result en una fila con todas sus respuestas.
     *
     * @param  \Illuminate\Support\Collection<int,EvaluationResult>  $results
     */
    private function renderTypeSheet(Worksheet $sheet, EvaluationType $type, $results): void
    {
        $questions = $type->questions->values();

        $headers = ['#', 'Fecha', 'Fuente', 'Promedio'];
        foreach ($questions as $q) {
            $headers[] = $q->question_text;
        }

        foreach ($headers as $i => $h) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue($col.'1', $h);
            $sheet->getColumnDimension($col)->setWidth($i < 4 ? 14 : 28);
        }
        $sheet->getRowDimension(1)->setRowHeight(32);

        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle('A1:'.$lastCol.'1')->applyFromArray([
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
        $sheet->freezePane('E2');

        $row = 2;
        $index = 1;
        foreach ($results as $result) {
            $answersByTpl = $result->answers->keyBy('question_template_id');

            $sheet->setCellValue('A'.$row, $index++);
            $sheet->setCellValue('B'.$row, $result->submitted_at?->translatedFormat('d M Y H:i') ?? '—');
            $sheet->setCellValue('C'.$row, $this->sourceLabel($result->source));
            $sheet->setCellValue('D'.$row, $result->total_score !== null
                ? number_format((float) $result->total_score, 2)
                : '—'
            );

            foreach ($questions as $i => $q) {
                $col = Coordinate::stringFromColumnIndex($i + 5);
                $answer = $answersByTpl[$q->id] ?? null;
                if (! $answer) {
                    $sheet->setCellValue($col.$row, '—');

                    continue;
                }
                if ($q->question_type === EvaluationQuestionTemplate::TYPE_SCORE) {
                    $sheet->setCellValue($col.$row, $answer->score_value !== null ? (string) $answer->score_value : '—');
                } else {
                    $sheet->setCellValue($col.$row, $answer->text_value ?? '');
                }
            }

            $row++;
        }

        $lastRow = $row - 1;
        if ($lastRow >= 2) {
            $sheet->getStyle('A2:'.$lastCol.$lastRow)->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['argb' => 'FFE2E8F0']],
                ],
                'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
            ]);
        }
    }

    /**
     * Excel no permite ciertos caracteres en el nombre de hoja (\\/?*[]:) y la
     * longitud máxima es 31 caracteres.
     */
    private function sheetSafeName(string $name): string
    {
        $clean = preg_replace('/[\\\\\\/?*\\[\\]:]/', '-', $name) ?? $name;

        return mb_substr($clean, 0, 31);
    }

    private function sourceLabel(?string $source): string
    {
        return match ($source) {
            EvaluationResult::SOURCE_INTERNAL => 'Sistema',
            EvaluationResult::SOURCE_CSV => 'Excel',
            EvaluationResult::SOURCE_FORMS => 'Forms',
            default => '—',
        };
    }
}
