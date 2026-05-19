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
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: "DM Sans", system-ui, sans-serif;
            background: linear-gradient(160deg, #f0f4f8 0%, #e8eef5 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            width: 100%;
            max-width: 400px;
            background: #fff;
            border-radius: 16px;
            padding: 28px 24px;
            box-shadow: 0 8px 32px rgba(27, 78, 139, 0.12);
            text-align: center;
        }
        h1 { font-size: 1.25rem; margin: 0 0 6px; color: #1B4E8B; }
        .sub { color: #64748b; font-size: 0.9rem; margin-bottom: 12px; }
        .code { font-family: monospace; font-size: 0.8rem; color: #94a3b8; margin-bottom: 20px; }
        label { display: block; font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 8px; text-align: left; }
        input {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 1rem;
            margin-bottom: 16px;
        }
        input:focus { outline: 2px solid #1B4E8B; border-color: #1B4E8B; }
        button {
            width: 100%;
            padding: 14px;
            background: #1B4E8B;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
        }
        button:hover { background: #163d6d; }
        .alert-error {
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 0.9rem;
            margin-bottom: 16px;
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
            text-align: left;
        }
        .success-panel {
            padding: 24px 16px;
            border-radius: 12px;
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
        }
        .success-panel .success-title {
            font-size: 1rem;
            font-weight: 600;
            color: #065f46;
            margin: 0 0 12px;
        }
        .success-panel .student-name {
            font-size: 1.15rem;
            font-weight: 600;
            color: #1B4E8B;
            margin: 0;
        }
        .already-panel {
            padding: 24px 16px;
            border-radius: 12px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
        }
        .already-panel .already-title {
            font-size: 1rem;
            font-weight: 600;
            color: #1e40af;
            margin: 0 0 12px;
        }
        .already-panel .student-name {
            font-size: 1.15rem;
            font-weight: 600;
            color: #1B4E8B;
            margin: 0;
        }
        .form-block { text-align: left; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Registrar asistencia</h1>
        <p class="sub">{{ $groupName }}</p>
        <p class="code">Sesión: {{ $session->session_code }}</p>

        {{-- Carnet ya registrado antes en esta misma sesión: no mostrar formulario otra vez --}}
        @if(session('registered') && session('alreadyRegistered'))
            <div class="already-panel" role="status">
                <p class="already-title">Ya se registró tu asistencia</p>
                <p class="student-name">{{ session('studentName') }}</p>
            </div>
        {{-- Primera vez que marca en esta sesión: confirmación verde + nombre --}}
        @elseif(session('registered'))
            <div class="success-panel" role="status">
                <p class="success-title">Asistencia registrada correctamente</p>
                <p class="student-name">{{ session('studentName') }}</p>
            </div>
        @else
            @if($errors->any())
                <div class="alert-error" role="alert">
                    @foreach($errors->all() as $err)
                        {{ $err }}
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('attendance.store', $token) }}" class="form-block">
                @csrf
                <label for="carnet">Número de carnet</label>
                <input type="text" id="carnet" name="carnet" value="{{ old('carnet') }}"
                       placeholder="Ej. 20210001" autocomplete="off" required autofocus>
                <button type="submit">Confirmar asistencia</button>
            </form>
        @endif
    </div>
</body>
</html>
