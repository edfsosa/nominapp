<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">
    @vite('resources/css/attendances/styles.css')
    @vite('resources/js/attendances/mark.js')
</head>

<body>
    <x-skip-links />

    <noscript>
        <div class="no-js-warning">Esta página requiere JavaScript para funcionar correctamente.</div>
    </noscript>

    <x-attendance.mark-loading />

    <main id="main-content" class="container">
        <h1>Marcación facial</h1>

        <x-alert-container />

        <div class="grid">
            <x-attendance.video-section />
            <x-attendance.form-section />
        </div>
    </main>

    <x-modal
        id="successModal"
        type="success"
        title="¡Marcación registrada!"
        description="Su marcación se ha registrado correctamente."
        buttonText="Aceptar"
    />

    <x-modal
        id="errorModal"
        type="error"
        title="Error"
        description=""
        buttonText="Cerrar"
    />

    <script defer src="https://unpkg.com/face-api.js@0.22.2/dist/face-api.min.js" referrerpolicy="no-referrer"></script>
</body>

</html>
