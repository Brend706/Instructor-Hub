<?php

namespace App\Services;

use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

class StudentExcelImportService
{
    /**
     * Lee un archivo subido al servidor con PhpSpreadsheet y delega en {@see parseMatrix()}.
     *
     * Uso típico en esta app: **CSV** vía `POST …/students/preview` e `import` (multipart).
     * Para **.xlsx / .xls**, la vista suele leer el libro en el navegador (SheetJS) y enviar
     * solo la matriz a `preview-matrix` / `import-matrix`, sin pasar por aquí — así no hace falta
     * `extension=zip` en PHP para ese flujo.
     *
     * Si alguien sube un xlsx/xls por multipart y PhpSpreadsheet falla (p. ej. sin ZIP en PHP),
     * el mensaje de error orienta a usar CSV o el flujo de lectura en cliente.
     *
     * @param  int|null  $classGroupId  Si se indica, se marcan duplicados respecto a la BD del grupo y dentro del archivo.
     * @return array{rows: list<array{sheet_row: int, carnet: string, name: string, email: string, ok: bool, error: ?string}>, summary: array{total: int, valid: int, invalid: int}}
     */
    public function parse(UploadedFile $file, ?int $classGroupId = null): array
    {
        $path = $this->resolveUploadedPath($file);

        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException(
                'No se pudo leer el archivo en el servidor. Para Excel (.xlsx/.xls) sin extension ZIP en PHP, sube el mismo archivo desde esta pantalla: se procesará en el navegador. También puedes usar CSV.',
                0,
                $e
            );
        }

        $sheet = $spreadsheet->getActiveSheet();

        return $this->parseMatrix($sheet->toArray(), $classGroupId);
    }

    /**
     * Convierte una matriz filas/columnas (como `sheet_to_json(..., { header: 1 })` o `toArray()` de PhpSpreadsheet)
     * en filas validadas listas para vista previa e importación.
     *
     * Es la **única** fuente de verdad para columnas, encabezados y reglas por fila; tanto el servidor
     * (tras leer archivo) como el cliente (tras SheetJS) deben terminar llamando esto en backend.
     *
     * Columnas persistidas en `students`: **carnet**, **nombre**, **correo** (el grupo viene del contexto).
     *
     * @param  array<int, mixed>  $matrix
     * @param  int|null  $classGroupId  Si se indica, se rechazan carnet/correo ya guardados en ese grupo y duplicados en el archivo.
     * @return array{rows: list<array{sheet_row: int, carnet: string, name: string, email: string, ok: bool, error: ?string}>, summary: array{total: int, valid: int, invalid: int}}
     */
    public function parseMatrix(array $matrix, ?int $classGroupId = null): array
    {
        $matrix = $this->normalizeMatrix($matrix);

        if ($matrix === [] || $matrix === [[]]) {
            return [
                'rows' => [],
                'summary' => ['total' => 0, 'valid' => 0, 'invalid' => 0],
            ];
        }

        $first = $matrix[0] ?? [];
        $headerMap = $this->mapHeaderRow($first);
        $hasHeader = $headerMap !== null;

        if ($hasHeader) {
            array_shift($matrix);
            $col = $headerMap;
        } else {
            $col = ['carnet' => 0, 'name' => 1, 'email' => 2];
        }

        $rows = [];
        $sheetRow = $hasHeader ? 2 : 1;

        foreach ($matrix as $line) {
            if (! is_array($line)) {
                $sheetRow++;

                continue;
            }

            $carnet = trim((string) ($line[$col['carnet']] ?? ''));
            $name = trim((string) ($line[$col['name']] ?? ''));
            $email = trim((string) ($line[$col['email']] ?? ''));

            if ($carnet === '' && $name === '' && $email === '') {
                $sheetRow++;

                continue;
            }

            $validation = $this->validateRow($carnet, $name, $email);
            $rows[] = [
                'sheet_row' => $sheetRow,
                'carnet' => $carnet,
                'name' => $name,
                'email' => $email,
                'ok' => $validation['ok'],
                'error' => $validation['error'],
            ];
            $sheetRow++;
        }

        if ($classGroupId !== null) {
            $rows = $this->applyDuplicateRules($rows, $classGroupId);
        }

        $valid = count(array_filter($rows, fn ($r) => $r['ok']));
        $invalid = count($rows) - $valid;

        return [
            'rows' => $rows,
            'summary' => [
                'total' => count($rows),
                'valid' => $valid,
                'invalid' => $invalid,
            ],
        ];
    }

    /**
     * Alinea cada fila a índices de columna 0…N conservando huecos (celdas vacías entre columnas).
     * Sin esto, `array_values` podría correr columnas si hay celdas vacías en medio del Excel.
     *
     * @param  array<int, mixed>  $matrix
     * @return array<int, array<int, mixed>>
     */
    private function normalizeMatrix(array $matrix): array
    {
        $out = [];
        foreach ($matrix as $row) {
            if (! is_array($row)) {
                continue;
            }

            $maxIdx = -1;
            foreach ($row as $k => $_) {
                if (is_int($k) && $k > $maxIdx) {
                    $maxIdx = $k;
                }
            }

            $norm = [];
            for ($i = 0; $i <= $maxIdx; $i++) {
                $norm[$i] = $row[$i] ?? '';
            }

            $out[] = $norm;
        }

        return $out;
    }

    /**
     * Validación mínima para importación.
     * Nota: validamos aquí (y no solo en el frontend) porque el import debe ser confiable y repetible.
     *
     * @return array{ok: bool, error: ?string}
     */
    public function validateRow(string $carnet, string $name, string $email): array
    {
        if ($carnet === '') {
            return ['ok' => false, 'error' => 'carnet vacío'];
        }
        if ($name === '') {
            return ['ok' => false, 'error' => 'nombre vacío'];
        }
        if ($email === '') {
            return ['ok' => false, 'error' => 'correo vacío'];
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'correo inválido: "'.$email.'"'];
        }

        if ($this->emailBelongsToExistingUser($email)) {
            return ['ok' => false, 'error' => 'correo ya registrado: existe un usuario con ese correo'];
        }

        return ['ok' => true, 'error' => null];
    }

    /**
     * Detecta la fila de encabezados y mapea nombres de columnas a índices.
     * Solo se usa como cabecera si aparecen **las tres** columnas esperadas (carnet, nombre, correo);
     * si falta alguna o hay pocas coincidencias, devuelve null y se interpretan datos por posición fija (columnas 0–2).
     *
     * @return array{carnet: int, name: int, email: int}|null
     */
    private function mapHeaderRow(array $headerCells): ?array
    {
        $rolesFound = [];
        foreach ($headerCells as $idx => $cell) {
            $role = $this->matchColumnRole($this->normalizeHeader((string) $cell));
            if ($role !== null && ! isset($rolesFound[$role])) {
                $rolesFound[$role] = (int) $idx;
            }
        }

        if (count($rolesFound) < 3) {
            return null;
        }

        if (! isset($rolesFound['carnet'], $rolesFound['name'], $rolesFound['email'])) {
            return null;
        }

        return [
            'carnet' => $rolesFound['carnet'],
            'name' => $rolesFound['name'],
            'email' => $rolesFound['email'],
        ];
    }

    private function normalizeHeader(string $h): string
    {
        $h = mb_strtolower(trim($h));
        $map = ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n'];
        $h = strtr($h, $map);

        return (string) preg_replace('/\s+/', ' ', $h);
    }

    private function matchColumnRole(string $label): ?string
    {
        if (preg_match('/\b(carnet|carne)\b/u', $label) === 1) {
            return 'carnet';
        }
        if (str_contains($label, 'nombre') && (str_contains($label, 'completo') || str_contains($label, 'estudiante'))) {
            return 'name';
        }
        if ($label === 'nombre') {
            return 'name';
        }
        if (str_contains($label, 'correo') || $label === 'email' || $label === 'e-mail') {
            return 'email';
        }

        return null;
    }

    /**
     * Ruta legible del temporal de subida.
     * En Windows, `getRealPath()` puede devolver null; `getPathname()` suele ser confiable.
     */
    private function resolveUploadedPath(UploadedFile $file): string
    {
        foreach ([$file->getRealPath(), $file->getPathname()] as $path) {
            if (is_string($path) && $path !== '' && is_readable($path)) {
                return $path;
            }
        }

        throw new \InvalidArgumentException('No se pudo acceder al archivo subido. Reintenta o prueba con otro navegador.');
    }

    private function emailBelongsToExistingUser(string $email): bool
    {
        $normalized = mb_strtolower(trim($email));

        return User::query()->whereRaw('LOWER(email) = ?', [$normalized])->exists();
    }

    /**
     * Rechaza filas que repiten carnet o correo respecto a datos ya guardados en el grupo, o entre sí en el mismo archivo.
     *
     * @param  list<array{sheet_row: int, carnet: string, name: string, email: string, ok: bool, error: ?string}>  $rows
     * @return list<array{sheet_row: int, carnet: string, name: string, email: string, ok: bool, error: ?string}>
     */
    private function applyDuplicateRules(array $rows, int $classGroupId): array
    {
        $existing = Student::query()
            ->where('class_group_id', $classGroupId)
            ->get(['carnet', 'email']);

        $dbCarnets = [];
        $dbEmails = [];
        foreach ($existing as $s) {
            $c = trim((string) $s->carnet);
            if ($c !== '') {
                $dbCarnets[$c] = true;
            }
            $e = mb_strtolower(trim((string) $s->email));
            if ($e !== '') {
                $dbEmails[$e] = true;
            }
        }

        $seenFileCarnets = [];
        $seenFileEmails = [];

        $out = [];
        foreach ($rows as $row) {
            if (! $row['ok']) {
                $out[] = $row;

                continue;
            }

            $c = trim((string) $row['carnet']);
            $e = mb_strtolower(trim((string) $row['email']));

            if (isset($seenFileCarnets[$c])) {
                $out[] = [...$row, 'ok' => false, 'error' => 'carnet repetido en el archivo'];

                continue;
            }
            if (isset($seenFileEmails[$e])) {
                $out[] = [...$row, 'ok' => false, 'error' => 'correo repetido en el archivo'];

                continue;
            }
            if (isset($dbCarnets[$c])) {
                $out[] = [...$row, 'ok' => false, 'error' => 'carnet ya existe en este grupo'];

                continue;
            }
            if (isset($dbEmails[$e])) {
                $out[] = [...$row, 'ok' => false, 'error' => 'correo ya existe en este grupo'];

                continue;
            }

            $seenFileCarnets[$c] = true;
            $seenFileEmails[$e] = true;
            $out[] = $row;
        }

        return $out;
    }
}
