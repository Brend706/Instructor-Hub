<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\Instructor;
use App\Models\InstructorAssignment;
use App\Models\StudentAttendance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Genera los archivos .xlsx descargables del módulo de asistencia.
 *
 * Centraliza la lógica de Excel (PhpSpreadsheet) para no ensuciar los controladores.
 * Devuelve siempre un array { filename, content } y deja que el controlador arme
 * la respuesta HTTP con los headers correctos (Content-Type, Content-Disposition).
 *
 * Dos puntos de entrada según la vista:
 *  - buildInstructorMatrix(): para la vista "Asistencia" del instructor.
 *    Genera la matriz estudiantes × sesiones de una sola instructoría.
 *  - buildCoordinatorSessions(): para la vista "Instructorías" del coordinador.
 *    Genera la lista de sesiones que ha dado un instructor (todas sus tutorías).
 *
 * Cada archivo trae dos hojas en el mismo libro:
 *  - Hoja 1 "Detalle": tabla con todos los datos.
 *  - Hoja 2 "Resumen": totales y promedios listos para leer de un vistazo.
 */
class AttendanceExcelExportService
{
    // Paleta usada para encabezados y celdas. Se centraliza aquí para mantener un look uniforme.
    private const COLOR_PRIMARY = '1B4E8B';   // azul de encabezados de tabla
    private const COLOR_HEADER_BG = 'E7EEF7'; // fondo claro (no se usa actualmente, queda como reserva)
    private const COLOR_GREEN = '166534';     // texto del ✓ de asistencia
    private const COLOR_GREEN_BG = 'DCFCE7';  // fondo verde de la celda "asistió"

    /**
     * Construye el .xlsx para una instructoría concreta (entrada desde la vista del instructor).
     *
     * Pasos:
     *  1. Carga grupo + estudiantes + sesiones cronológicamente ordenadas.
     *  2. Arma un map { session_id => [student_id, …] } con quiénes asistieron,
     *     para no consultar la BD dentro del bucle de la matriz.
     *  3. Llena la hoja "Matriz" (estudiantes × sesiones) y la hoja "Resumen".
     *  4. Devuelve el contenido binario del archivo y un nombre seguro de archivo.
     *
     * @return array{filename: string, content: string}
     */
    public function buildInstructorMatrix(InstructorAssignment $assignment): array
    {
        // Asegura que las relaciones existan antes de usarlas (evita queries N+1).
        $assignment->loadMissing(['classGroup', 'instructor.user']);

        $group = $assignment->classGroup;

        // Sesiones de la instructoría en orden cronológico (fecha y luego hora de inicio).
        // Ese orden es el que tendrán las columnas en la matriz.
        $sessions = $assignment->classSessions()
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        // Estudiantes inscritos al grupo, alfabéticos. Cada uno será una fila.
        $students = $group?->students()->orderBy('name')->get() ?? collect();

        // Map de asistencia: para cada sesión, lista de student_id que marcaron asistencia.
        // Se construye en una sola query y luego se consulta en memoria con in_array().
        $attendedMap = StudentAttendance::query()
            ->whereIn('session_id', $sessions->pluck('id'))
            ->where('attended', true)
            ->get(['session_id', 'student_id'])
            ->groupBy('session_id')
            ->map(fn ($rows) => $rows->pluck('student_id')->all());

        // Libro de Excel en blanco; las hojas se rellenan abajo.
        $spreadsheet = new Spreadsheet;

        $this->buildMatrixSheet($spreadsheet, $group, $sessions, $students, $attendedMap, $assignment->instructor?->user?->name);
        $this->buildSummarySheet($spreadsheet, $group, $sessions, $students, $attendedMap);

        // Que al abrirlo Excel muestre primero la "Matriz", no el "Resumen".
        $spreadsheet->setActiveSheetIndex(0);

        // Nombre amigable: "asistencia-{grupo}-YYYY-MM-DD.xlsx".
        $safeGroup = $this->slug($group?->name ?? 'instructoria');
        $filename = "asistencia-{$safeGroup}-".now()->format('Y-m-d').'.xlsx';

        return [
            'filename' => $filename,
            'content' => $this->dump($spreadsheet),
        ];
    }

    /**
     * Construye el .xlsx para un instructor (entrada desde la vista del coordinador).
     *
     * A diferencia del anterior, no muestra estudiantes uno por uno: lista todas
     * las sesiones que ha dado el instructor en cualquiera de sus instructorías,
     * con sus fechas, horarios y cuántos estudiantes asistieron a cada una.
     *
     * Pasos:
     *  1. Obtiene los IDs de todas las instructorías del instructor.
     *  2. Carga las sesiones de esas instructorías con su grupo y un conteo de
     *     asistentes (withCount sobre la relación attendances).
     *  3. Llena la hoja "Sesiones" y la hoja "Resumen".
     *
     * @return array{filename: string, content: string}
     */
    public function buildCoordinatorSessions(Instructor $instructor): array
    {
        // Carga relaciones necesarias para los nombres en la cabecera del Excel.
        $instructor->loadMissing(['user', 'instructorAssignments.classGroup']);

        $assignmentIds = $instructor->instructorAssignments->pluck('id');

        // Sesiones del instructor (ordenadas de la más reciente a la más vieja).
        // attendees_count viene precalculado por withCount → no hacemos consultas extra.
        $sessions = ClassSession::query()
            ->whereIn('instructor_assignment_id', $assignmentIds)
            ->with(['instructorAssignment.classGroup'])
            ->withCount(['attendances as attendees_count' => fn ($q) => $q->where('attended', true)])
            ->orderByDesc('date')
            ->orderByDesc('start_time')
            ->get();

        $spreadsheet = new Spreadsheet;

        $this->buildSessionsSheet($spreadsheet, $instructor, $sessions);
        $this->buildSessionsSummarySheet($spreadsheet, $instructor, $sessions);

        $spreadsheet->setActiveSheetIndex(0);

        // Nombre amigable: "instructorias-{nombre-instructor}-YYYY-MM-DD.xlsx".
        $safeName = $this->slug($instructor->user?->name ?? 'instructor');
        $filename = "instructorias-{$safeName}-".now()->format('Y-m-d').'.xlsx';

        return [
            'filename' => $filename,
            'content' => $this->dump($spreadsheet),
        ];
    }

    // ───────────────────────────────────────────────────────────
    //  Hojas: Matriz instructor
    // ───────────────────────────────────────────────────────────

    /**
     * Llena la hoja principal con la matriz estudiantes × sesiones.
     *
     * Estructura visual de la hoja:
     *  - A1: título grande combinado con todas las columnas.
     *  - Filas 3-8: bloque de información (grupo, profesor, ciclo, modalidad, instructor, fecha de generación).
     *  - Fila headerRow: encabezado azul con "#", "Carnet", "Estudiante", una columna por sesión
     *    (fecha + hora) y al final "Total" y "%".
     *  - Filas de datos: una por estudiante; cada celda de sesión es ✓ (verde) si asistió o "—".
     *  - Fila final: total de asistentes por sesión (resumen vertical).
     *
     *  Al final se aplica bordes a toda la tabla, anchos cómodos y se congelan las
     *  3 primeras columnas + el encabezado para que al hacer scroll se vean los datos del estudiante.
     */
    private function buildMatrixSheet(
        Spreadsheet $spreadsheet,
        $group,
        Collection $sessions,
        Collection $students,
        Collection $attendedMap,
        ?string $instructorName,
    ): void {
        // La primera hoja ya existe en un Spreadsheet nuevo; le ponemos nombre.
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Matriz');

        // Título: se combina desde A1 hasta la última columna de la tabla (3 fijas + sesiones + Total + %).
        $sheet->setCellValue('A1', 'Asistencia por instructoría');
        $sheet->mergeCells('A1:'.$this->colLetter(3 + $sessions->count() + 2).'1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        // Bloque de metadatos: dos columnas (etiqueta en negrita | valor) bajo el título.
        $info = [
            ['Grupo', $group?->name ?? '—'],
            ['Profesor', $group?->professor ?? '—'],
            ['Ciclo', $group?->semester ?? '—'],
            ['Modalidad', $group?->modality ?? '—'],
            ['Instructor', $instructorName ?? '—'],
            ['Generado', now()->translatedFormat('d M Y · H:i')],
        ];
        $row = 3;
        foreach ($info as [$label, $value]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $value);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;
        }

        // headerRow queda una fila por debajo del bloque de info para dejar un respiro visual.
        $headerRow = $row + 1;
        $sheet->setCellValue("A{$headerRow}", '#');
        $sheet->setCellValue("B{$headerRow}", 'Carnet');
        $sheet->setCellValue("C{$headerRow}", 'Estudiante');

        // Una columna por sesión, en el mismo orden cronológico que vino del query.
        // El contenido es "dd Mes\n HH:MM" para que con wrapText se vea en dos líneas.
        $col = 4;
        foreach ($sessions as $session) {
            $date = $session->date ? Carbon::parse($session->date)->translatedFormat('d M Y') : '—';
            $hour = $session->start_time ? Carbon::parse($session->start_time)->format('H:i') : '';
            $letter = $this->colLetter($col);
            $sheet->setCellValue("{$letter}{$headerRow}", trim("{$date}\n{$hour}"));
            $sheet->getColumnDimension($letter)->setWidth(13);
            $col++;
        }

        // Dos columnas finales: total absoluto (X/Y) y porcentaje.
        $totalLetter = $this->colLetter($col);
        $pctLetter = $this->colLetter($col + 1);
        $sheet->setCellValue("{$totalLetter}{$headerRow}", 'Total');
        $sheet->setCellValue("{$pctLetter}{$headerRow}", '%');

        // Estilo de la fila de encabezado: azul, texto blanco, centrado y con wrap.
        $lastCol = $pctLetter;
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")
            ->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_PRIMARY]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(32);

        // Cuerpo: una fila por estudiante. Por cada sesión se decide ✓ o —
        // consultando el map precalculado en buildInstructorMatrix().
        $dataRow = $headerRow + 1;
        $totalSessions = $sessions->count();
        foreach ($students as $i => $student) {
            $sheet->setCellValue("A{$dataRow}", $i + 1);
            $sheet->setCellValue("B{$dataRow}", $student->carnet);
            $sheet->setCellValue("C{$dataRow}", $student->name);

            $col = 4;
            $count = 0;
            foreach ($sessions as $session) {
                $letter = $this->colLetter($col);
                $coord = "{$letter}{$dataRow}";
                $attended = in_array($student->id, $attendedMap[$session->id] ?? [], true);
                if ($attended) {
                    // Asistió: ✓ verde en negrita sobre fondo verde claro.
                    $count++;
                    $sheet->setCellValue($coord, '✓');
                    $sheet->getStyle($coord)->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_GREEN_BG]],
                        'font' => ['color' => ['rgb' => self::COLOR_GREEN], 'bold' => true],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                } else {
                    // No asistió: guion centrado, sin color de fondo.
                    $sheet->setCellValue($coord, '—');
                    $sheet->getStyle($coord)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
                $col++;
            }

            // Total absoluto y porcentaje para este estudiante.
            $totalLetter = $this->colLetter($col);
            $pctLetter = $this->colLetter($col + 1);
            $sheet->setCellValue("{$totalLetter}{$dataRow}", "{$count}/{$totalSessions}");
            $pct = $totalSessions > 0 ? round(($count / $totalSessions) * 100) : 0;
            $sheet->setCellValue("{$pctLetter}{$dataRow}", "{$pct}%");
            $sheet->getStyle("{$totalLetter}{$dataRow}:{$pctLetter}{$dataRow}")
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $dataRow++;
        }

        // Fila de pie: cuenta de asistentes por cada sesión (suma vertical de ✓).
        $sheet->setCellValue("A{$dataRow}", '');
        $sheet->setCellValue("B{$dataRow}", '');
        $sheet->setCellValue("C{$dataRow}", 'Asistentes');
        $sheet->getStyle("C{$dataRow}")->getFont()->setBold(true);
        $col = 4;
        foreach ($sessions as $session) {
            $coord = $this->colLetter($col).$dataRow;
            $sheet->setCellValue($coord, count($attendedMap[$session->id] ?? []));
            $sheet->getStyle($coord)->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => self::COLOR_PRIMARY]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            $col++;
        }

        // Bordes delgados a toda la tabla (encabezado + datos + pie).
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$dataRow}")
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Anchos fijos para columnas conocidas, y anchos finales para Total y %.
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(12);
        $sheet->getColumnDimension('C')->setWidth(30);
        $sheet->getColumnDimension($this->colLetter($col))->setWidth(10);
        $sheet->getColumnDimension($this->colLetter($col + 1))->setWidth(8);

        // Congela todo lo que está a la izquierda de D y por encima del primer estudiante:
        // así, al desplazar a la derecha por muchas sesiones, siguen visibles #, carnet y nombre.
        $sheet->freezePane("D".($headerRow + 1));
    }

    /**
     * Llena la segunda hoja con indicadores agregados de la instructoría.
     *
     * Contenido:
     *  - Identificación del grupo (nombre y ciclo).
     *  - Conteos básicos: sesiones, estudiantes inscritos, asistencias totales.
     *  - Promedios: asistentes por sesión y % de asistencia general.
     *  - Sesión con más / menos asistentes (cuando son distintas).
     *
     * Se presenta como dos columnas: etiqueta en negrita | valor, una fila por métrica.
     */
    private function buildSummarySheet(
        Spreadsheet $spreadsheet,
        $group,
        Collection $sessions,
        Collection $students,
        Collection $attendedMap,
    ): void {
        // createSheet() añade una hoja nueva al libro (la "Matriz" ya existe en índice 0).
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Resumen');

        // Métricas calculadas en memoria a partir del map de asistencia ya cargado.
        $totalSessions = $sessions->count();
        $totalStudents = $students->count();
        $totalAttendances = $sessions->sum(fn ($s) => count($attendedMap[$s->id] ?? []));
        $avgPerSession = $totalSessions > 0 ? round($totalAttendances / $totalSessions, 1) : 0;
        // Asistencia promedio: total de asistencias dividido entre el máximo posible
        // (sesiones * estudiantes). Evita división por cero si aún no hay sesiones ni estudiantes.
        $avgPct = ($totalSessions > 0 && $totalStudents > 0)
            ? round(($totalAttendances / ($totalSessions * $totalStudents)) * 100)
            : 0;

        // Sesión con más y con menos asistentes para resaltar extremos.
        // Se mapea cada sesión a { s, n } para conservar el modelo junto al conteo.
        $bestSession = $sessions
            ->map(fn ($s) => ['s' => $s, 'n' => count($attendedMap[$s->id] ?? [])])
            ->sortByDesc('n')->first();
        $worstSession = $sessions
            ->filter(fn ($s) => true)
            ->map(fn ($s) => ['s' => $s, 'n' => count($attendedMap[$s->id] ?? [])])
            ->sortBy('n')->first();

        $rows = [
            ['Resumen de asistencia', ''],
            ['', ''],
            ['Grupo', $group?->name ?? '—'],
            ['Ciclo', $group?->semester ?? '—'],
            ['', ''],
            ['Sesiones realizadas', $totalSessions],
            ['Estudiantes inscritos', $totalStudents],
            ['Total de asistencias', $totalAttendances],
            ['Promedio de asistentes por sesión', $avgPerSession],
            ['Asistencia promedio (%)', "{$avgPct}%"],
        ];

        // Solo se muestran los extremos si la mejor y la peor son sesiones distintas
        // (con una sola sesión ambas serían iguales y no aporta información).
        if ($bestSession && $bestSession['s']) {
            $date = Carbon::parse($bestSession['s']->date)->translatedFormat('d M Y');
            $rows[] = ['Sesión con más asistentes', "{$date} ({$bestSession['n']})"];
        }
        if ($worstSession && $worstSession['s'] && $worstSession !== $bestSession) {
            $date = Carbon::parse($worstSession['s']->date)->translatedFormat('d M Y');
            $rows[] = ['Sesión con menos asistentes', "{$date} ({$worstSession['n']})"];
        }

        // Volcado: fila 1 funciona como título combinado; las demás son etiqueta | valor.
        $row = 1;
        foreach ($rows as [$label, $value]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $value);
            if ($row === 1) {
                $sheet->mergeCells("A1:B1");
                $sheet->getStyle("A1")->getFont()->setBold(true)->setSize(14);
            } else {
                $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            }
            $row++;
        }

        $sheet->getColumnDimension('A')->setWidth(36);
        $sheet->getColumnDimension('B')->setWidth(24);
    }

    // ───────────────────────────────────────────────────────────
    //  Hojas: Sesiones (coordinador)
    // ───────────────────────────────────────────────────────────

    /**
     * Llena la hoja principal del Excel del coordinador: una fila por sesión dada
     * por el instructor, con grupo, horarios, duración legible y asistentes.
     *
     * Cabecera de la tabla:
     *  Fecha | Grupo | Hora inicio | Hora fin | Duración | Asistentes | Estado
     *
     * La duración se calcula a partir de start_time y end_time y se formatea como
     * "< 1 min", "X min" o "X h Y min" para que no aparezcan números como 0.0833.
     * Si la sesión no tiene end_time se considera "En curso".
     */
    private function buildSessionsSheet(Spreadsheet $spreadsheet, Instructor $instructor, Collection $sessions): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sesiones');

        $name = $instructor->user?->name ?? 'Instructor';

        // Título y subtítulo (fecha de generación) combinados a lo ancho de las 7 columnas.
        $sheet->setCellValue('A1', 'Instructorías de '.$name);
        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $sheet->setCellValue('A2', 'Generado: '.now()->translatedFormat('d M Y · H:i'));
        $sheet->mergeCells('A2:G2');
        $sheet->getStyle('A2')->getFont()->setItalic(true)->getColor()->setRGB('64748B');

        // Encabezado de la tabla: azul con texto blanco, centrado.
        $headers = ['Fecha', 'Grupo', 'Hora inicio', 'Hora fin', 'Duración', 'Asistentes', 'Estado'];
        $headerRow = 4;
        foreach ($headers as $i => $h) {
            $sheet->setCellValue($this->colLetter($i + 1).$headerRow, $h);
        }
        $sheet->getStyle("A{$headerRow}:G{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_PRIMARY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Cuerpo: una fila por sesión, con la duración formateada para humanos.
        $row = $headerRow + 1;
        foreach ($sessions as $session) {
            $group = $session->instructorAssignment?->classGroup;
            $start = $session->start_time ? Carbon::parse($session->start_time) : null;
            $end = $session->end_time ? Carbon::parse($session->end_time) : null;

            // Misma fórmula que la vista web: "< 1 min" / "X min" / "Xh" / "Xh Ymin".
            $duration = '—';
            if ($start && $end) {
                $mins = (int) round(abs($end->diffInSeconds($start)) / 60);
                if ($mins < 1) {
                    $duration = '< 1 min';
                } elseif ($mins < 60) {
                    $duration = "{$mins} min";
                } else {
                    $h = intdiv($mins, 60);
                    $m = $mins % 60;
                    $duration = $m > 0 ? "{$h} h {$m} min" : "{$h} h";
                }
            }

            $sheet->setCellValue("A{$row}", $session->date ? Carbon::parse($session->date)->translatedFormat('d M Y') : '—');
            $sheet->setCellValue("B{$row}", $group?->name ?? '—');
            $sheet->setCellValue("C{$row}", $start?->format('H:i') ?? '—');
            $sheet->setCellValue("D{$row}", $end?->format('H:i') ?? 'En curso');
            $sheet->setCellValue("E{$row}", $duration);
            $sheet->setCellValue("F{$row}", (int) ($session->attendees_count ?? 0));
            $sheet->setCellValue("G{$row}", $session->is_open ? 'Abierta' : 'Finalizada');

            $row++;
        }

        // Bordes y centrado de las columnas numéricas solo si hubo filas.
        if ($sessions->isNotEmpty()) {
            $sheet->getStyle("A{$headerRow}:G".($row - 1))
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle("C5:G".($row - 1))
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        // Anchos ajustados al contenido típico de cada columna.
        $sheet->getColumnDimension('A')->setWidth(14);
        $sheet->getColumnDimension('B')->setWidth(28);
        $sheet->getColumnDimension('C')->setWidth(11);
        $sheet->getColumnDimension('D')->setWidth(11);
        $sheet->getColumnDimension('E')->setWidth(14);
        $sheet->getColumnDimension('F')->setWidth(12);
        $sheet->getColumnDimension('G')->setWidth(14);

        // Congela el encabezado para que al hacer scroll siempre se vean los títulos.
        $sheet->freezePane('A'.($headerRow + 1));
    }

    /**
     * Segunda hoja del Excel del coordinador con totales del instructor.
     *
     * Contenido:
     *  - Identificación: nombre y carrera/coordinación.
     *  - Sesiones totales, asistencias totales y promedio por sesión.
     *  - Grupos atendidos (cantidad y listado con bullet "·").
     */
    private function buildSessionsSummarySheet(Spreadsheet $spreadsheet, Instructor $instructor, Collection $sessions): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Resumen');

        // attendees_count viene precalculado desde el query (withCount), así que se suma directo.
        $total = $sessions->count();
        $totalAttendances = $sessions->sum(fn ($s) => (int) ($s->attendees_count ?? 0));
        $avg = $total > 0 ? round($totalAttendances / $total, 1) : 0;
        // Lista única de grupos atendidos (filtra null y duplicados).
        $groups = $sessions
            ->map(fn ($s) => $s->instructorAssignment?->classGroup?->name)
            ->filter()->unique()->values();

        $rows = [
            ['Resumen', ''],
            ['', ''],
            ['Instructor', $instructor->user?->name ?? '—'],
            ['Carrera / Coordinación', $instructor->major ?? '—'],
            ['', ''],
            ['Sesiones totales', $total],
            ['Asistencias totales', $totalAttendances],
            ['Promedio asistentes por sesión', $avg],
            ['Grupos atendidos', $groups->count()],
        ];

        // Cada grupo se agrega como una fila más, debajo del bloque general.
        foreach ($groups as $g) {
            $rows[] = ['  · '.$g, ''];
        }

        // Mismo patrón que la otra hoja de resumen: A1 título grande combinado;
        // resto, etiqueta en negrita | valor.
        $row = 1;
        foreach ($rows as [$label, $value]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $value);
            if ($row === 1) {
                $sheet->mergeCells("A1:B1");
                $sheet->getStyle("A1")->getFont()->setBold(true)->setSize(14);
            } else {
                $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            }
            $row++;
        }

        $sheet->getColumnDimension('A')->setWidth(34);
        $sheet->getColumnDimension('B')->setWidth(24);
    }

    // ───────────────────────────────────────────────────────────
    //  Helpers compartidos
    // ───────────────────────────────────────────────────────────

    /**
     * Devuelve una versión segura del texto para usar en nombres de archivo:
     *  - en minúsculas,
     *  - sin tildes ni ñ,
     *  - reemplazando cualquier carácter no alfanumérico por guion,
     *  - sin guiones al inicio o final.
     */
    private function slug(string $value): string
    {
        $value = mb_strtolower($value);
        $value = strtr($value, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'ñ' => 'n', 'ü' => 'u', 'ç' => 'c',
        ]);
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? 'archivo';

        return trim($value, '-') ?: 'archivo';
    }

    /**
     * Convierte un índice numérico 1-based a la letra de columna de Excel
     * (1 → "A", 4 → "D", 27 → "AA"). Necesario porque PhpSpreadsheet 5.x
     * trabaja con coordenadas tipo "D5" y ya no acepta (col, row) numérico.
     */
    private function colLetter(int $col): string
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
    }

    /**
     * Convierte el objeto Spreadsheet en los bytes del archivo .xlsx.
     *
     * Usa el writer "Xlsx" y guarda en php://output mientras un output buffer
     * captura todo en memoria. Así el controlador puede devolver el contenido
     * directamente como respuesta HTTP sin tocar el disco.
     */
    private function dump(Spreadsheet $spreadsheet): string
    {
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        ob_start();
        $writer->save('php://output');

        return (string) ob_get_clean();
    }
}
