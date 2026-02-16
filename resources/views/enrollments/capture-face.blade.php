<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registro Facial - {{ config('app.name', 'RRHH') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://unpkg.com">
    @vite('resources/css/enrollments/capture-face.css')
</head>

<body>
    <x-employee.capture-loading />

    <div class="container">
        <!-- Header con info del empleado -->
        <header class="enrollment-header">
            <h1>Registro Facial</h1>
            <p class="greeting">{{ $employee->first_name }} {{ $employee->last_name }}</p>
            <p class="subtitle">CI: {{ $employee->ci }}</p>
        </header>

        <main>
            <!-- Sección de captura -->
            <section class="card" aria-labelledby="capture-heading">
                <h2 id="capture-heading">Captura del Rostro</h2>

                <div class="video-wrap" role="img" aria-label="Vista previa de la cámara">
                    <video id="video" autoplay playsinline muted
                        aria-label="Vista previa de la cámara para registro facial"></video>
                    <canvas id="overlay" aria-hidden="true"></canvas>
                </div>

                <div class="controls">
                    <button id="btnStart" type="button" class="btn-gray" aria-label="Iniciar cámara">
                        Iniciar Cámara
                    </button>
                    <button id="btnCapture" type="button" class="btn-blue" disabled
                        aria-label="Capturar rostro">
                        Capturar Rostro
                    </button>
                </div>

                <div class="status-area">
                    <div id="status" class="status" role="status" aria-live="polite">
                        Presiona "Iniciar Cámara" para comenzar
                    </div>
                </div>
            </section>

            <!-- Formulario de envío -->
            <form id="saveForm" method="POST"
                  action="{{ route('face-enrollment.store', $enrollment->token) }}" novalidate>
                @csrf
                <input type="hidden" name="face_descriptor" id="faceDescriptor">
                <div class="form-actions">
                    <button type="submit" id="btnSave" class="btn-green" disabled
                        aria-describedby="save-help">
                        Enviar Registro
                    </button>
                </div>
                <small id="save-help" class="help-text">
                    Se capturan múltiples muestras de tu rostro para mayor precisión.
                    El administrador revisará y aprobará tu registro.
                </small>
            </form>
        </main>

        <footer class="enrollment-footer">
            <small>Este enlace expira el {{ $enrollment->expires_at->format('d/m/Y H:i') }}</small>
        </footer>
    </div>

    <!-- Modal de éxito -->
    <div id="confirmationModal" class="modal" style="display: none;" role="dialog" aria-modal="true"
        aria-labelledby="modal-title" tabindex="-1">
        <div class="modal-backdrop"></div>
        <div class="modal-content">
            <header class="modal-header">
                <h2 id="modal-title">Rostro Registrado</h2>
            </header>
            <div class="modal-body">
                <p id="modal-description">Su rostro fue capturado exitosamente.</p>
                <p>El administrador revisará y aprobará su registro antes de que pueda marcar asistencia.</p>
            </div>
            <footer class="modal-footer">
                <button id="closeModal" type="button" class="btn-primary">Entendido</button>
            </footer>
        </div>
    </div>

    <script defer src="https://unpkg.com/face-api.js@0.22.2/dist/face-api.min.js" crossorigin="anonymous"
        referrerpolicy="no-referrer" onerror="handleScriptError()"></script>
    @vite('resources/js/enrollments/capture-face.js')

    <script>
        function handleScriptError() {
            document.getElementById('status').textContent =
                'Error: No se pudo cargar la biblioteca de reconocimiento facial.';
        }
    </script>
</body>

</html>
