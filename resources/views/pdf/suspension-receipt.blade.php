<!DOCTYPE html>
{{--
    Comprobante de Aprobación de Solicitud de Suspensión.
    Plantilla pensada para ser renderizada con DomPDF (no es una vista web).
    DomPDF soporta CSS muy limitado, por eso todo está inline y con unidades simples.
--}}
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $documentTitle }} · Solicitud #{{ $request->id }}</title>
    <style>
        @page { margin: 30px 36px; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1F2937;
            line-height: 1.45;
            margin: 0;
        }

        /* ── Encabezado ───────────────────────────────────────────── */
        .header {
            border-bottom: 3px solid #7A1B47;
            padding-bottom: 14px;
            margin-bottom: 22px;
        }
        .header-top {
            width: 100%;
        }
        .brand {
            font-size: 9px;
            color: #6B7280;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .title {
            font-size: 19px;
            font-weight: bold;
            color: #7A1B47;
            margin: 0 0 4px;
        }
        .subtitle {
            font-size: 11px;
            color: #4B5563;
            margin: 0;
        }
        .folio-box {
            float: right;
            text-align: right;
            border: 1px solid #E5E7EB;
            padding: 8px 12px;
            border-radius: 6px;
            background: #F9FAFB;
            font-size: 10px;
            line-height: 1.5;
        }
        .folio-label {
            color: #6B7280;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .folio-value {
            font-size: 14px;
            font-weight: bold;
            color: #111827;
        }

        /* ── Sello de resolución (color dinámico por estado) ──────── */
        .stamp {
            text-align: center;
            margin: 6px 0 22px;
        }
        .stamp-inner {
            display: inline-block;
            border: 3px solid {{ $stampColor }};
            color: {{ $stampColor }};
            padding: 8px 22px;
            font-size: 18px;
            font-weight: bold;
            letter-spacing: 3px;
            text-transform: uppercase;
            transform: rotate(-3deg);
        }

        /* ── Bloques de información ───────────────────────────────── */
        .section {
            margin-bottom: 18px;
        }
        .section-title {
            font-size: 11px;
            font-weight: bold;
            color: #7A1B47;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 1px solid #E5E7EB;
            padding-bottom: 4px;
            margin: 0 0 8px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 5px 8px;
            vertical-align: top;
            font-size: 11px;
        }
        .info-table .label {
            width: 32%;
            color: #6B7280;
            font-weight: 600;
        }
        .info-table .value {
            color: #111827;
        }

        .reason-box {
            background: #F9FAFB;
            border: 1px solid #E5E7EB;
            border-left: 3px solid #7A1B47;
            padding: 10px 12px;
            border-radius: 4px;
            font-size: 11px;
            color: #374151;
            white-space: pre-wrap;
        }

        .notes-box {
            background: {{ $isApproved ? '#F0FDF4' : '#FEF2F2' }};
            border: 1px solid {{ $isApproved ? '#BBF7D0' : '#FECACA' }};
            border-left: 3px solid {{ $accentColor }};
            padding: 10px 12px;
            border-radius: 4px;
            font-size: 11px;
            color: {{ $isApproved ? '#166534' : '#991B1B' }};
            margin-top: 6px;
            white-space: pre-wrap;
        }

        /* ── Firma ────────────────────────────────────────────────── */
        .signature {
            margin-top: 36px;
            width: 100%;
        }
        .signature-line {
            border-top: 1px solid #1F2937;
            width: 60%;
            margin: 0 auto;
            padding-top: 6px;
            text-align: center;
            font-size: 10px;
        }
        .signature-name {
            font-weight: bold;
            color: #111827;
            font-size: 11px;
        }
        .signature-role {
            color: #6B7280;
            font-size: 10px;
        }

        /* ── Pie de página ────────────────────────────────────────── */
        .footer {
            position: fixed;
            bottom: -16px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8.5px;
            color: #9CA3AF;
            border-top: 1px solid #E5E7EB;
            padding-top: 6px;
        }
        .verify {
            font-family: DejaVu Sans Mono, monospace;
            color: #6B7280;
        }

        /* ── Logo institucional ───────────────────────────────────── */
        .pdf-logo-wrap {
            text-align: center;
            margin-bottom: 14px;
        }
        .pdf-logo {
            max-height: 110px;
            max-width: 480px;
        }
    </style>
</head>
<body>

    {{-- Logo institucional (UTEC) embebido como base64 desde el controlador. --}}
    @if(!empty($logoSrc))
        <div class="pdf-logo-wrap">
            <img src="{{ $logoSrc }}" alt="Universidad Tecnológica de El Salvador" class="pdf-logo">
        </div>
    @endif

    {{-- Encabezado con logo textual + folio --}}
    <div class="header">
        <div class="folio-box">
            <div class="folio-label">Folio</div>
            <div class="folio-value">SUS-{{ str_pad($request->id, 6, '0', STR_PAD_LEFT) }}</div>
        </div>
        <div class="header-top">
            <div class="brand">Instructor Hub · Sistema institucional</div>
            <h1 class="title">{{ $documentTitle }}</h1>
            <p class="subtitle">Solicitud de suspensión de instructoría</p>
        </div>
    </div>

    {{-- Sello visible de la resolución: verde "APROBADA" o rojo "RECHAZADA" --}}
    <div class="stamp">
        <span class="stamp-inner">{{ $stampLabel }}</span>
    </div>

    {{-- Datos del solicitante (instructor) --}}
    <div class="section">
        <h2 class="section-title">Datos del solicitante</h2>
        <table class="info-table">
            <tr>
                <td class="label">Nombre completo</td>
                <td class="value">{{ $instructorName }}</td>
            </tr>
            <tr>
                <td class="label">Correo institucional</td>
                <td class="value">{{ $instructorEmail ?: '—' }}</td>
            </tr>
            <tr>
                <td class="label">Coordinación asignada</td>
                <td class="value">{{ $coordinationName }}</td>
            </tr>
        </table>
    </div>

    {{-- Detalle de la solicitud --}}
    <div class="section">
        <h2 class="section-title">Detalle de la solicitud</h2>
        <table class="info-table">
            <tr>
                <td class="label">Tipo de solicitud</td>
                <td class="value">{{ $typeLabel }}</td>
            </tr>
            <tr>
                <td class="label">Grupo afectado</td>
                <td class="value">{{ $groupName }}</td>
            </tr>
            <tr>
                <td class="label">Fecha de solicitud</td>
                <td class="value">{{ $requestedAt }}</td>
            </tr>
            <tr>
                <td class="label">{{ $isApproved ? 'Fecha de aprobación' : 'Fecha de rechazo' }}</td>
                <td class="value">{{ $reviewedAt }}</td>
            </tr>
        </table>

        <div style="margin-top:10px">
            <div style="font-size:10px;color:#6B7280;font-weight:600;margin-bottom:4px;text-transform:uppercase;letter-spacing:1px">
                Motivo expuesto
            </div>
            <div class="reason-box">{{ $request->reason ?: '—' }}</div>
        </div>

        @if(!empty($request->admin_notes))
            <div style="margin-top:10px">
                <div style="font-size:10px;color:{{ $accentColor }};font-weight:600;margin-bottom:4px;text-transform:uppercase;letter-spacing:1px">
                    {{ $isApproved ? 'Observaciones del revisor' : 'Motivo del rechazo' }}
                </div>
                <div class="notes-box">{{ $request->admin_notes }}</div>
            </div>
        @endif
    </div>

    {{-- Resolución (texto generado en el controller según estado) --}}
    <div class="section">
        <h2 class="section-title">Resolución</h2>
        <p style="margin:0;font-size:11px;color:#1F2937;text-align:justify;line-height:1.6">
            {!! $resolutionText !!}
        </p>
    </div>

    {{-- Firma del revisor --}}
    <div class="signature">
        <div class="signature-line">
            <div class="signature-name">{{ $reviewerName }}</div>
            <div class="signature-role">{{ $reviewerRole }}</div>
        </div>
    </div>

    {{-- Pie con verificación --}}
    <div class="footer">
        Comprobante generado por Instructor Hub el {{ $generatedAt }}.
        <br>
        <span class="verify">Folio: SUS-{{ str_pad($request->id, 6, '0', STR_PAD_LEFT) }} · Verificación: {{ $verificationHash }}</span>
    </div>

</body>
</html>
