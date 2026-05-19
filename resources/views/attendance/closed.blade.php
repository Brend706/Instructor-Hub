{{-- Se muestra si el estudiante abre el QR después de que el instructor finalizó la sesión (is_open = false). --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sesión no disponible</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            font-family: "DM Sans", system-ui, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: #f1f4f8;
            text-align: center;
        }
        .box {
            max-width: 360px;
            background: #fff;
            padding: 32px 24px;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0,0,0,.08);
        }
        h1 { color: #1B4E8B; font-size: 1.2rem; margin: 0 0 12px; }
        p { color: #64748b; margin: 0; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="box">
        <h1>Sesión cerrada</h1>
        <p>{{ $message }}</p>
    </div>
</body>
</html>
