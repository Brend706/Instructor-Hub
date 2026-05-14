@extends('layouts.coordinator', ['title' => 'Agregar estudiantes'])

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/coordinator/students.css') }}">
@endpush

@section('content')

<a href="{{ route('coordinator.groups.index') }}" class="back-link">
    <i class="ti ti-arrow-left" aria-hidden="true"></i> Volver a grupos
</a>

<div class="page-header">
    <h1 class="page-title">Agregar estudiantes</h1>
    <p class="page-sub">Importa la lista de estudiantes desde un archivo Excel</p>
</div>

@if(session('success'))
    <div class="alert-success" role="alert">
        <i class="ti ti-circle-check" aria-hidden="true"></i> {{ session('success') }}
    </div>
@endif

@if($errors->any())
    <div class="errors-section visible" role="alert" style="margin-bottom:16px;">
        <strong>No se pudo completar la acción:</strong>
        <ul id="globalErrorsList">
            @foreach($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="group-info-card">
    <div>
        <div class="info-item-label">Materia</div>
        <div class="info-item-value">{{ $group->name }}</div>
        <div class="info-item-sub">{{ $group->professor }}</div>
    </div>
    <div>
        <div class="info-item-label">Ciclo</div>
        <div class="info-item-value">{{ $group->semester }}</div>
    </div>
    <div>
        <div class="info-item-label">Modalidad</div>
        <div class="info-item-value">{{ $group->modality }}</div>
        <div class="info-item-sub">{{ $group->classroom }}</div>
    </div>
    <div>
        <div class="info-item-label">Instructor</div>
        <div class="info-item-value">{{ $instructorName ?? 'Sin asignar' }}</div>
        @if(!empty($instructorMajor))
            <div class="info-item-sub">{{ $instructorMajor }}</div>
        @endif
    </div>
</div>

<div class="upload-section">
    <div class="section-title">
        <i class="ti ti-file-spreadsheet" aria-hidden="true"></i>
        Subir archivo Excel
    </div>
    <p class="section-sub">
        El archivo debe contener las columnas: <strong>carnet</strong>, <strong>nombre completo</strong> y <strong>correo</strong> (en ese orden si no hay fila de encabezado).
        No se importan filas con el mismo carnet o correo repetidos en el archivo, ni si ya existen en este grupo.
    </p>

    <div class="drop-zone" id="dropZone"
         onclick="document.getElementById('fileInput').click()"
         ondragover="onDragOver(event)"
         ondragleave="onDragLeave(event)"
         ondrop="onDrop(event)"
         role="button"
         tabindex="0"
         aria-label="Zona para subir archivo Excel">

        <form method="POST" id="importForm" action="{{ route('coordinator.groups.students.import', $group) }}" enctype="multipart/form-data">
            @csrf
            <input type="file" id="fileInput" name="file" accept=".xlsx,.xls,.csv"
                   style="display:none" onchange="onFileSelected(this)">
        </form>

        <div class="drop-icon" id="dropIcon">
            <i class="ti ti-upload" id="dropIconInner" aria-hidden="true"></i>
        </div>
        <div class="drop-title" id="dropTitle">Arrastra tu archivo aqui</div>
        <div class="drop-sub" id="dropSub">o haz clic para seleccionarlo desde tu equipo</div>
        <button type="button" class="browse-btn" id="browseBtn"
                onclick="event.stopPropagation();document.getElementById('fileInput').click()">
            <i class="ti ti-folder-open" aria-hidden="true"></i> Seleccionar archivo
        </button>
    </div>

    <div class="template-tip">
        <i class="ti ti-info-circle" aria-hidden="true"></i>
        <span>
            La primera fila puede ser encabezado con los nombres:
            <strong>carnet</strong> (o carne), <strong>nombre completo del estudiante</strong> y <strong>correo</strong>.
        </span>
    </div>

    <div class="errors-section" id="errorsSection" role="alert" aria-live="polite">
        <strong>Se encontraron errores en el archivo:</strong>
        <ul id="errorsList"></ul>
    </div>

    {{-- Confirmar import para xlsx/xls: POST con matrix_json (misma matriz que SheetJS envió a preview-matrix). CSV usa importForm. --}}
    <form id="importMatrixForm" method="POST" action="{{ route('coordinator.groups.students.import-matrix', $group) }}" style="display:none;">
        @csrf
        <input type="hidden" name="matrix_json" id="matrixJsonInput" value="">
    </form>
</div>

<div class="preview-section" id="previewSection">
    <div class="preview-header">
        <div class="preview-title">
            <i class="ti ti-table-check" aria-hidden="true"></i>
            Vista previa de estudiantes
        </div>
        <span class="preview-count" id="previewCount">0 estudiantes</span>
    </div>
    <div class="preview-table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Carnet</th>
                    <th>Nombre completo</th>
                    <th>Correo</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody id="previewBody"></tbody>
        </table>
    </div>
</div>

<div class="actions-footer">
    <div class="actions-left" id="footerMsg">Sube un archivo Excel para continuar.</div>
    <div class="actions-right">
        <a href="{{ route('coordinator.groups.index') }}" class="btn btn-ghost">Cancelar</a>
        <button type="button" class="btn btn-success" id="btnImport" disabled>
            <i class="ti ti-file-import" aria-hidden="true"></i> Confirmar importacion
        </button>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
    const previewUrl = @json(route('coordinator.groups.students.preview', $group));
    const previewMatrixUrl = @json(route('coordinator.groups.students.preview-matrix', $group));
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    let useClientSheet = false;
    let lastMatrix = null;

    // Flujo de archivos:
    // 1) Usuario elige archivo (input o arrastre).
    // 2) Vista previa: .csv y otros legibles en servidor → POST multipart a previewUrl (parse → parseMatrix).
    //    .xlsx / .xls → SheetJS en el navegador → POST JSON a previewMatrixUrl (solo parseMatrix).
    // 3) Confirmar: multipart importForm → import; si useClientSheet → importMatrixForm con matrix_json → import-matrix.

    function onDragOver(e) {
        e.preventDefault();
        document.getElementById('dropZone').classList.add('drag-over');
    }
    function onDragLeave() {
        document.getElementById('dropZone').classList.remove('drag-over');
    }
    function onDrop(e) {
        e.preventDefault();
        document.getElementById('dropZone').classList.remove('drag-over');
        const file = e.dataTransfer.files[0];
        if (file) uploadPreview(file);
    }
    window.onDragOver = onDragOver;
    window.onDragLeave = onDragLeave;
    window.onDrop = onDrop;

    window.onFileSelected = function (input) {
        if (input.files[0]) uploadPreview(input.files[0]);
    };

    function extOf(file) {
        const n = file.name || '';
        const i = n.lastIndexOf('.');
        return i >= 0 ? n.slice(i + 1).toLowerCase() : '';
    }

    /** Carga xlsx.full.min.js desde CDN la primera vez que hace falta leer .xlsx/.xls en el cliente. */
    function loadSheetJs() {
        return new Promise(function (resolve, reject) {
            if (typeof window.XLSX !== 'undefined') return resolve();
            const s = document.createElement('script');
            s.src = 'https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js';
            s.async = true;
            s.onload = function () { resolve(); };
            s.onerror = function () {
                reject(new Error('No se pudo cargar el lector de Excel (comprueba tu conexión o intenta con archivo CSV).'));
            };
            document.head.appendChild(s);
        });
    }

    /** Primera hoja del libro → matriz de celdas (misma forma que espera el backend en preview-matrix). */
    async function workbookToMatrix(file) {
        await loadSheetJs();
        const buf = await file.arrayBuffer();
        const wb = window.XLSX.read(buf, { type: 'array' });
        const name = wb.SheetNames && wb.SheetNames[0];
        if (!name) throw new Error('El archivo Excel no tiene hojas.');
        const sheet = wb.Sheets[name];
        return window.XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '', blankrows: false });
    }

    /** Pide vista previa al backend; bifurca entre multipart (p. ej. CSV) y JSON matrix (xlsx/xls). */
    async function uploadPreview(file) {
        const zone = document.getElementById('dropZone');
        useClientSheet = false;
        lastMatrix = null;

        zone.classList.add('has-file');
        document.getElementById('dropIconInner').className = 'ti ti-loader-2';
        document.getElementById('dropTitle').textContent = 'Procesando…';
        document.getElementById('dropSub').textContent = file.name;

        const ext = extOf(file);

        try {
            if (ext === 'xlsx' || ext === 'xls') {
                const matrix = await workbookToMatrix(file);
                lastMatrix = matrix;
                useClientSheet = true;

                const res = await fetch(previewMatrixUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ matrix: matrix, filename: file.name }),
                });

                const data = await res.json().catch(function () { return {}; });

                if (!res.ok) {
                    throw new Error(data.message || (data.errors && (data.errors.matrix && data.errors.matrix[0])) || 'No se pudo leer el archivo.');
                }

                renderPreview(data);
                return;
            }

            const fd = new FormData();
            fd.append('file', file);
            fd.append('_token', csrfToken);

            const res = await fetch(previewUrl, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const data = await res.json().catch(function () { return {}; });

            if (!res.ok) {
                throw new Error(data.message || data.errors?.file?.[0] || 'No se pudo leer el archivo.');
            }

            renderPreview(data);
        } catch (err) {
            zone.classList.remove('has-file');
            document.getElementById('dropIconInner').className = 'ti ti-upload';
            document.getElementById('dropTitle').textContent = 'Arrastra tu archivo aqui';
            document.getElementById('dropSub').textContent = 'o haz clic para seleccionarlo desde tu equipo';
            alert(err.message || 'Error al subir el archivo.');
        }
    }

    function renderPreview(data) {
        document.getElementById('dropIconInner').className = 'ti ti-circle-check';
        document.getElementById('dropTitle').textContent = data.filename || 'Archivo';
        const rows = data.rows || [];
        const summary = data.summary || { total: rows.length, valid: 0 };
        document.getElementById('dropSub').textContent =
            'Archivo cargado — ' + summary.total + ' fila(s) detectada(s)';
        document.getElementById('browseBtn').innerHTML =
            '<i class="ti ti-refresh"></i> Cambiar archivo';

        const errSec = document.getElementById('errorsSection');
        const apiErrors = data.errors || [];
        if (apiErrors.length) {
            errSec.classList.add('visible');
            document.getElementById('errorsList').innerHTML = apiErrors
                .map(function (line) { return '<li>' + escapeHtml(line) + '</li>'; })
                .join('');
        } else {
            errSec.classList.remove('visible');
            document.getElementById('errorsList').innerHTML = '';
        }

        const tbody = document.getElementById('previewBody');
        tbody.innerHTML = '';

        // Filas ya validadas en servidor (mismo formato en preview y preview-matrix): solo pintamos el resultado.
        rows.forEach(function (r, i) {
            const tr = document.createElement('tr');
            const ok = !!r.ok;
            tr.innerHTML =
                '<td style="color:var(--text-muted)">' + (i + 1) + '</td>' +
                '<td style="font-family:monospace;font-size:11px">' + escapeHtml(r.carnet) + '</td>' +
                '<td class="td-name">' + escapeHtml(r.name) + '</td>' +
                '<td style="font-size:11px">' + escapeHtml(r.email) + '</td>' +
                '<td>' + (ok
                    ? '<span class="badge-ok"><i class="ti ti-check" style="font-size:10px"></i> Valido</span>'
                    : '<span class="badge-err"><i class="ti ti-x" style="font-size:10px"></i> Error</span>'
                ) + '</td>';
            tbody.appendChild(tr);
        });

        document.getElementById('previewCount').textContent = summary.total + ' estudiantes';
        document.getElementById('previewSection').classList.add('visible');

        const validCount = rows.filter(function (r) { return r.ok; }).length;
        document.getElementById('footerMsg').textContent =
            validCount + ' de ' + summary.total + ' estudiante(s) listos para importar.';
        document.getElementById('btnImport').disabled = validCount === 0;
    }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    document.getElementById('btnImport').addEventListener('click', function () {
        const file = document.getElementById('fileInput').files[0];
        if (!file) {
            alert('Selecciona un archivo primero.');
            return;
        }
        if (useClientSheet && lastMatrix) {
            document.getElementById('matrixJsonInput').value = JSON.stringify(lastMatrix);
            document.getElementById('importMatrixForm').submit();
            return;
        }
        this.disabled = true;
        document.getElementById('importForm').submit();
    });
})();
</script>
@endpush
