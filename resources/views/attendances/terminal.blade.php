<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">
    @vite('resources/css/attendances/terminal.css')
    @vite('resources/js/attendances/terminal.js')
</head>

<body>
    <noscript>
        <div class="no-js-warning">Esta página requiere JavaScript para funcionar correctamente.</div>
    </noscript>

    <main class="terminal-container">
        <x-attendance.terminal-loading />
        <x-attendance.terminal-type-selector />
        <x-attendance.terminal-video-section />
        <x-attendance.terminal-success />
    </main>

    <script defer src="https://unpkg.com/face-api.js@0.22.2/dist/face-api.min.js" referrerpolicy="no-referrer"></script>
</body>

</html>
