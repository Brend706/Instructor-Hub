{{-- Se muestra si el estudiante abre el QR después de que el instructor finalizó la sesión (is_open = false). --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sesión no disponible — Instructor Hub</title>
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
            padding: 24px;
        }

        .wrap {
            width: 100%;
            max-width: 380px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 14px;
        }

        .brand-logo {
            width: 44px; height: 44px;
            border-radius: 12px;
            background: #5A1533;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 20px; font-weight: 700; color: #fff;
        }
        .brand-name {
            font-size: 13px; font-weight: 600;
            color: #5A1533; margin-top: 6px;
            text-align: center;
        }

        .card {
            width: 100%;
            background: #fff;
            border-radius: 16px;
            padding: 36px 28px;
            box-shadow: 0 8px 32px rgba(90, 21, 51, .10);
            border: 1px solid #EEC4CF;
            text-align: center;
        }

        .icon-wrap {
            width: 56px; height: 56px;
            border-radius: 14px;
            background: #F9EFF2;
            border: 1px solid #EEC4CF;
            display: flex; align-items: center; justify-content: center;
            font-size: 26px; color: #5A1533;
            margin: 0 auto 18px;
        }

        h1 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2C2C32;
            margin-bottom: 10px;
        }

        p {
            font-size: 0.88rem;
            color: #6B6472;
            line-height: 1.6;
        }

        .footer {
            font-size: 11px;
            color: #9A8FA0;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div>
            <div class="brand-logo">IH</div>
            <div class="brand-name">Instructor Hub</div>
        </div>

        <div class="card">
            <div class="icon-wrap">
                <i class="ti ti-lock"></i>
            </div>
            <h1>Sesión cerrada</h1>
            <p>{{ $message }}</p>
        </div>

        <p class="footer">Sistema de Instructorías · FICA</p>
    </div>
</body>
</html>
