<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Registro de Marcación</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    @vite(['resources/css/attendance/styles.css'])
</head>

<body>
    <h1>Registro Biométrico</h1>

    <div class="container">
        <form id="attendanceForm" method="POST" action="{{ route('mark.store') }}">
            @csrf
            <!-- Selección de sucursal -->
            <label for="branch">Sucursal:</label>
            <select id="branch" name="branch" required>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                @endforeach
            </select>

            <!-- Selección de tipo existente -->
            <label for="type">Tipo de Marcación:</label>
            <select id="type" name="event_type" required>
                <option value="" disabled selected>Seleccione tipo</option>
                <option value="check_in">Entrada de Jornada</option>
                <option value="break_start">Inicio de Descanso</option>
                <option value="break_end">Fin de Descanso</option>
                <option value="check_out">Salida de Jornada</option>
            </select>
        </form>

        <!-- Video y canvas para la cámara -->
        <div class="video-container">
            <video id="video" width="320" height="240" autoplay muted></video>
            <canvas id="overlay" width="320" height="240" style="position: absolute; top: 0; left: 0;"></canvas>
        </div>

        <div id="messageBox" class="alert alert-warning">Inicializando...</div>
        <div class="time" id="clock"></div>
    </div>

    <audio id="successSound" src="{{ asset('sounds/success.mp3') }}" preload="auto" aria-label="Sonido de correcto" hidden></audio>
    <audio id="errorSound" src="{{ asset('sounds/error.mp3') }}" preload="auto" aria-label="Sonido de error" hidden></audio>

    <script defer src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
    @vite(['resources/js/attendance/index.js'])
</body>

</html>
