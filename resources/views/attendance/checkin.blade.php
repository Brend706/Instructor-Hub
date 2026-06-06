{{--
    Pantalla del ESTUDIANTE al escanear el QR o abrir el enlace de asistencia.
    Tres estados posibles (sin login):
    1) Formulario: pide carnet y botón "Confirmar asistencia".
    2) Éxito: solo mensaje verde + nombre (ya no pide nada más).
    3) Duplicado: carnet ya registrado en esta sesión → mensaje azul + nombre.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar asistencia — {{ $groupName }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            font-family: "DM Sans", system-ui, sans-serif;
            background: #F1EFE8;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .wrap {
            width: 100%;
            max-width: 420px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        /* Branding top */
        .brand {
            text-align: center;
            margin-bottom: 4px;
        }
        .brand-logo {
            width: 44px; height: 44px;
            border-radius: 12px;
            background: #5A1533;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 20px; font-weight: 700; color: #fff;
            margin-bottom: 8px;
        }
        .brand-name { font-size: 13px; font-weight: 600; color: #5A1533; }

        /* Card principal */
        .card {
            background: #fff;
            border-radius: 16px;
            padding: 28px 24px;
            box-shadow: 0 8px 32px rgba(90, 21, 51, .10);
            border: 1px solid #EEC4CF;
        }

        .card-icon {
            width: 48px; height: 48px;
            border-radius: 12px;
            background: #F9EFF2;
            border: 1px solid #EEC4CF;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; color: #5A1533;
            margin: 0 auto 14px;
        }

        h1 {
            font-size: 1.15rem;
            font-weight: 600;
            color: #2C2C32;
            text-align: center;
            margin-bottom: 4px;
        }
        .sub {
            font-size: 0.85rem;
            color: #9A8FA0;
            text-align: center;
            margin-bottom: 6px;
        }
        .code {
            font-family: monospace;
            font-size: 0.75rem;
            color: #C47A90;
            text-align: center;
            background: #F9EFF2;
            padding: 4px 12px;
            border-radius: 20px;
            display: inline-block;
            margin: 0 auto 20px;
        }
        .code-wrap { text-align: center; margin-bottom: 20px; }

        /* Formulario */
        .form-block { display: flex; flex-direction: column; gap: 12px; }

        label {
            font-size: 0.82rem;
            font-weight: 600;
            color: #6B6472;
            display: block;
            margin-bottom: 6px;
        }
        input {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #E8E4DC;
            border-radius: 10px;
            font-size: 1rem;
            font-family: inherit;
            color: #2C2C32;
            background: #FAF8F4;
            outline: none;
            transition: border .15s, box-shadow .15s;
        }
        input:focus {
            border-color: #7F77DD;
            box-shadow: 0 0 0 3px #EEEDF9;
        }
        input::placeholder { color: #9A8FA0; }

        button[type="submit"] {
            width: 100%;
            padding: 13px;
            background: #5A1533;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            transition: background .15s, transform .1s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        button[type="submit"]:hover { background: #3D0D22; }
        button[type="submit"]:active { transform: scale(.98); }

        /* Error */
        .alert-error {
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 0.88rem;
            background: #FEE2E2;
            color: #991B1B;
            border: 1px solid #FECACA;
            display: flex; align-items: flex-start; gap: 8px;
        }
        .alert-error i { font-size: 16px; flex-shrink: 0; margin-top: 1px; }

        /* Éxito */
        .success-panel {
            padding: 24px 20px;
            border-radius: 12px;
            background: #E8F5EE;
            border: 1px solid #A7F3D0;
            text-align: center;
        }
        .panel-icon {
            width: 48px; height: 48px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; margin: 0 auto 12px;
        }
        .panel-icon.green { background: #DCFCE7; color: #166534; }
        .panel-icon.lavanda { background: #EEEDF9; color: #7F77DD; }

        .success-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: #166534;
            margin-bottom: 8px;
        }
        .already-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: #7F77DD;
            margin-bottom: 8px;
        }
        .student-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #5A1533;
        }

        /* Ya registrado */
        .already-panel {
            padding: 24px 20px;
            border-radius: 12px;
            background: #EEEDF9;
            border: 1px solid #D3D1F0;
            text-align: center;
        }

        /* Footer */
        .footer {
            text-align: center;
            font-size: 11px;
            color: #9A8FA0;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="brand">
            <div class="brand-logo">IH</div>
            <div class="brand-name">Instructor Hub</div>
        </div>

        <div class="card">
            <div class="card-icon">
                <i class="ti ti-clipboard-check"></i>
            </div>
            <h1>Registrar asistencia</h1>
            <p class="sub">{{ $groupName }}</p>
            <div class="code-wrap">
                <span class="code">Sesión: {{ $session->session_code }}</span>
            </div>

            {{-- Carnet ya registrado antes en esta misma sesión --}}
            @if(session('registered') && session('alreadyRegistered'))
                <div class="already-panel" role="status">
                    <div class="panel-icon lavanda"><i class="ti ti-info-circle"></i></div>
                    <p class="already-title">Ya registraste tu asistencia</p>
                    <p class="student-name">{{ session('studentName') }}</p>
                </div>

            {{-- Primera vez que marca: confirmación --}}
            @elseif(session('registered'))
                <div class="success-panel" role="status">
                    <div class="panel-icon green"><i class="ti ti-circle-check"></i></div>
                    <p class="success-title">Asistencia registrada correctamente</p>
                    <p class="student-name">{{ session('studentName') }}</p>
                </div>

            {{-- Formulario --}}
            @else
                @if($errors->any())
                    <div class="alert-error" role="alert">
                        <i class="ti ti-alert-triangle"></i>
                        <div>
                            @foreach($errors->all() as $err)
                                <div>{{ $err }}</div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('attendance.store', $token) }}" class="form-block">
                    @csrf
                    <div>
                        <label for="carnet">Número de carnet</label>
                        <input type="text" id="carnet" name="carnet"
                               value="{{ old('carnet') }}"
                               placeholder="Ej. 20210001"
                               autocomplete="off" required autofocus>
                    </div>
                    <button type="submit">
                        <i class="ti ti-check"></i> Confirmar asistencia
                    </button>
                </form>
            @endif
        </div>

        <p class="footer">Sistema de Instructorías · FICA</p>
    </div>
</body>
</html>
