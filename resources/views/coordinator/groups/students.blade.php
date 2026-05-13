@extends('layouts.coordinator', ['title' => 'Agregar estudiantes'])

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/coordinator/students.css') }}">
@endpush

@section('content')

{{-- Volver --}}
<a href="{{ route('coordinator.groups.index') }}" class="back-link">
    <i class="ti ti-arrow-left" aria-hidden="true"></i> Volver a grupos
</a>

{{-- Header --}}
<div class="page-header">
    <h1 class="page-title">Agregar estudiantes</h1>
    <p class="page-sub">Importa la lista de estudiantes desde un archivo Excel</p>
</div>

{{-- Info del grupo --}}
{{-- Al integrar backend: reemplazar datos ficticios con $group->subject, $group->cycle, etc. --}}
<div class="group-info-card">
    <div>
        <div class="info-item-label">Materia</div>
        <div class="info-item-value">Programacion I</div>
        <div class="info-item-sub">Ing. R. Chavez</div>
    </div>
    <div>
        <div class="info-item-label">Ciclo</div>
        <div class="info-item-value">01-2026</div>
    </div>
    <div>
        <div class="info-item-label">Modalidad</div>
        <div class="info-item-value">Presencial</div>
        <div class="info-item-sub">Aula 204</div>
    </div>
    <div>
        <div class="info-item-label">Instructor</div>
        <div class="info-item-value">Ana Mejia</div>
        <div class="info-item-sub">Ing. Sistemas</div>
    </div>
</div>

{{-- Upload --}}
<div class="upload-section">
    <div class="section-title">
        <i class="ti ti-file-spreadsheet" aria-hidden="true"></i>
        Subir archivo Excel
    </div>
    <p class="section-sub">
        El archivo debe contener las columnas: <strong>carne, nombre completo, correo</strong> y <strong>codigo de materia</strong>.
    </p>

    {{-- Zona drag & drop --}}
    <div class="drop-zone" id="dropZone"
         onclick="document.getElementById('fileInput').click()"
         ondragover="onDragOver(event)"
         ondragleave="onDragLeave(event)"
         ondrop="onDrop(event)"
         role="button"
         tabindex="0"
         aria-label="Zona para subir archivo Excel">

        {{-- Al integrar backend: action="{{ route('coordinator.groups.students.import', $group->id) }}" --}}
        <form method="POST" id="importForm" action="#" enctype="multipart/form-data">
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

    {{-- Tip plantilla --}}
    <div class="template-tip">
        <i class="ti ti-info-circle" aria-hidden="true"></i>
        <span>
            No tienes el formato correcto?
            {{-- Al integrar backend: href="{{ route('coordinator.groups.students.template') }}" --}}
            <a href="#" class="template-link">Revisa antes el modelo de tu archivo</a>
            debe tener las columnas: carnet, nombre completo del estudiante, correo y codigo de materia.
        </span>
    </div>

    {{-- Errores de validacion --}}
    <div class="errors-section" id="errorsSection" role="alert" aria-live="polite">
        <strong>Se encontraron errores en el archivo:</strong>
        <ul id="errorsList"></ul>
    </div>
</div>

{{-- Preview --}}
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
                    <th>Carne</th>
                    <th>Nombre completo</th>
                    <th>Correo</th>
                    <th>Codigo materia</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody id="previewBody"></tbody>
        </table>
    </div>
</div>

{{-- Footer acciones --}}
<div class="actions-footer">
    <div class="actions-left" id="footerMsg">Sube un archivo Excel para continuar.</div>
    <div class="actions-right">
        <a href="{{ route('coordinator.groups.index') }}" class="btn btn-ghost">Cancelar</a>
        <button type="button" class="btn btn-success" id="btnImport" disabled
                onclick="document.getElementById('importForm').submit()">
            <i class="ti ti-file-import" aria-hidden="true"></i> Confirmar importacion
        </button>
    </div>
</div>

@endsection

@push('scripts')
<script>
    // ── Datos ficticios para preview frontend ──────────────
    // Al integrar backend: reemplazar con respuesta del servidor al subir el archivo
    const fakeStudents = [
        { carne: '2021001', name: 'Roberto Aleman',   email: 'r.aleman@fica.edu.sv',   code: 'PRG101', ok: true },
        { carne: '2021042', name: 'Sofia Martinez',   email: 's.martinez@fica.edu.sv',  code: 'PRG101', ok: true },
        { carne: '2020088', name: 'Diego Portillo',   email: 'd.portillo@fica.edu.sv',  code: 'PRG101', ok: true },
        { carne: '2021015', name: 'Fernanda Cruz',    email: 'correo-invalido',          code: 'PRG101', ok: false },
        { carne: '2022033', name: 'Luis Hernandez',   email: 'l.hernandez@fica.edu.sv', code: 'PRG101', ok: true },
        { carne: '2021077', name: 'Karla Ramos',      email: 'k.ramos@fica.edu.sv',     code: 'PRG101', ok: true },
    ];

    // ── Drag & drop ────────────────────────────────────────
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
        if (file) simulateLoad(file.name);
    }
    function onFileSelected(input) {
        if (input.files[0]) simulateLoad(input.files[0].name);
    }

    // ── Simular carga y preview ────────────────────────────
    function simulateLoad(filename) {
        const zone = document.getElementById('dropZone');
        zone.classList.add('has-file');

        document.getElementById('dropIconInner').className = 'ti ti-circle-check';
        document.getElementById('dropTitle').textContent   = filename;
        document.getElementById('dropSub').textContent     = 'Archivo cargado — ' + fakeStudents.length + ' filas detectadas';
        document.getElementById('browseBtn').innerHTML     = '<i class="ti ti-refresh"></i> Cambiar archivo';

        // Errores
        const hasErrors = fakeStudents.some(s => !s.ok);
        const errSec    = document.getElementById('errorsSection');
        if (hasErrors) {
            errSec.classList.add('visible');
            document.getElementById('errorsList').innerHTML =
                fakeStudents
                    .filter(s => !s.ok)
                    .map((s, i) => `<li>Fila ${fakeStudents.indexOf(s) + 1} — correo invalido: "${s.email}"</li>`)
                    .join('');
        } else {
            errSec.classList.remove('visible');
        }

        // Preview
        const tbody = document.getElementById('previewBody');
        tbody.innerHTML = '';
        fakeStudents.forEach((s, i) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td style="color:var(--text-muted)">${i + 1}</td>
                <td style="font-family:monospace;font-size:11px">${s.carne}</td>
                <td class="td-name">${s.name}</td>
                <td style="font-size:11px">${s.email}</td>
                <td style="font-size:11px;font-family:monospace">${s.code}</td>
                <td>${s.ok
                    ? '<span class="badge-ok"><i class="ti ti-check" style="font-size:10px"></i> Valido</span>'
                    : '<span class="badge-err"><i class="ti ti-x" style="font-size:10px"></i> Error</span>'
                }</td>
            `;
            tbody.appendChild(tr);
        });

        document.getElementById('previewCount').textContent = fakeStudents.length + ' estudiantes';
        document.getElementById('previewSection').classList.add('visible');

        const validCount = fakeStudents.filter(s => s.ok).length;
        document.getElementById('footerMsg').textContent =
            validCount + ' de ' + fakeStudents.length + ' estudiantes listos para importar.';
        document.getElementById('btnImport').disabled = false;
    }
</script>
@endpush