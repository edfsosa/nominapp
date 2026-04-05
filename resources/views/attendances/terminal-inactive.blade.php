<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Arial, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 2rem;
        }
        .container { max-width: 480px; }
        .icon {
            width: 80px; height: 80px;
            border-radius: 50%;
            background: #1e293b;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.5rem;
            border: 2px solid #ef4444;
        }
        .icon svg { width: 40px; height: 40px; color: #ef4444; }
        h1 { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.75rem; }
        p { font-size: 1rem; color: #94a3b8; line-height: 1.6; margin-bottom: 0.5rem; }
        .terminal-name { font-size: 0.875rem; color: #64748b; margin-top: 2rem; }
    </style>
</head>

<body>
    <div class="container">
        <div class="icon">
            {{-- Icono de terminal/pantalla apagada --}}
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>
        <h1>Terminal fuera de servicio</h1>
        <p>Esta terminal no está disponible en este momento.</p>
        <p>Por favor, comuníquese con el administrador.</p>
        <div class="terminal-name">{{ $terminal->name }} — {{ $terminal->branch?->name }}</div>
    </div>
</body>

</html>
