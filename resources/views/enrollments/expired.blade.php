<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enlace Expirado - {{ config('app.name', 'RRHH') }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0f172a;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            color: #e2e8f0;
            padding: 1rem;
        }
        .container {
            text-align: center;
            max-width: 420px;
        }
        .icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            margin-bottom: 1.5rem;
        }
        .icon svg {
            width: 36px;
            height: 36px;
            color: #ef4444;
        }
        .label {
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: #ef4444;
            margin-bottom: 0.75rem;
        }
        h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #f1f5f9;
            margin-bottom: 0.75rem;
        }
        p {
            font-size: 1rem;
            color: #94a3b8;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
        </div>
        <div class="label">Enlace Expirado</div>
        <h1>Este enlace ya no es válido</h1>
        <p>El enlace de registro facial ha expirado. Contacte al administrador para obtener uno nuevo.</p>
    </div>
</body>
</html>
