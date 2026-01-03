<!doctype html>
<html lang="es">

<head>
    <!-- Configuración básica del documento -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Captura y registro de descriptores faciales para empleados">

    <!-- Título de la página -->
    <title>Capturar Rostro - {{ config('app.name', 'Laravel') }}</title>

    <!-- Token CSRF para seguridad en formularios -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Preconexión a dominios externos para optimizar rendimiento -->
    <link rel="preconnect" href="https://unpkg.com">

    <!-- Carga de estilos específicos para la funcionalidad -->
    @vite('resources/css/employees/capture-face.css')
</head>

<body>
    <!-- Contenedor principal de la aplicación -->
    <div class="container">
        <!-- Encabezado principal de la página -->
        <header>
            <h1>Capturar Rostro del Empleado</h1>
        </header>

        <!-- Grid principal con dos columnas -->
        <main class="grid" role="main">
            <!-- Columna izquierda: Captura de video y controles -->
            <section class="card capture-section" aria-labelledby="capture-heading">
                <h2 id="capture-heading">Paso 1 · Captura del Rostro</h2>

                <!-- Contenedor para la cámara y la superposición de detección -->
                <div class="video-wrap" role="img" aria-label="Vista previa de la cámara web">
                    <video id="video" autoplay playsinline muted
                        aria-label="Vista previa de la cámara para captura facial"
                        poster="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='640' height='480'%3E%3Crect width='100%25' height='100%25' fill='%23f0f0f0'/%3E%3Ctext x='50%25' y='50%25' text-anchor='middle' dy='.3em' fill='%23999'%3ECámara no iniciada%3C/text%3E%3C/svg%3E">
                    </video>
                    <!-- Canvas para mostrar la detección facial superpuesta -->
                    <canvas id="overlay" aria-hidden="true" role="presentation">
                    </canvas>
                </div>

                <!-- Controles de captura -->
                <div class="controls row">
                    <!-- Botón para iniciar la cámara -->
                    <button id="btnStart" type="button" class="btn btn-gray" aria-label="Iniciar cámara web">
                        <span aria-hidden="true">📷</span> Iniciar Cámara
                    </button>

                    <!-- Botón para capturar el descriptor facial -->
                    <button id="btnCapture" type="button" class="btn btn-blue" disabled
                        aria-label="Capturar descriptor facial del rostro detectado">
                        <span aria-hidden="true">🎯</span> Capturar Descriptor
                    </button>
                </div>

                <!-- Área de estado y mensajes -->
                <div class="status-area">
                    <div id="status" class="status" role="status" aria-live="polite" aria-atomic="true">
                        Presiona "Iniciar Cámara" para comenzar
                    </div>
                </div>
            </section>

            <!-- Columna derecha: Información del empleado y confirmación -->
            <section class="card confirmation-section" aria-labelledby="confirmation-heading">
                <h2 id="confirmation-heading">Paso 2 · Confirmación y Guardado</h2>

                <!-- Información del empleado -->
                <div class="employee-info" role="region" aria-labelledby="employee-info-heading">
                    <h3 id="employee-info-heading" class="sr-only">Información del Empleado</h3>

                    <div class="employee-details">
                        <!-- Nombre completo del empleado con fallback robusto -->
                        <div class="employee-name">
                            @php
                                // Construcción más robusta del nombre del empleado
                                $employeeName = '';

                                if (!empty($employee->name)) {
                                    $employeeName = trim($employee->name);
                                } else {
                                    $firstName = trim($employee->first_name ?? '');
                                    $lastName = trim($employee->last_name ?? '');
                                    $employeeName = trim($firstName . ' ' . $lastName);
                                }

                                // Fallback si no hay nombre disponible
                                if (empty($employeeName)) {
                                    $employeeName = 'Empleado #' . $employee->id;
                                }
                            @endphp

                            <strong>{{ $employeeName }}</strong>
                        </div>

                        <!-- Documento de identidad del empleado -->
                        @php
                            // Búsqueda más robusta del documento
                            $documentNumber =
                                $employee->document_number ??
                                ($employee->document ?? ($employee->dni ?? ($employee->ci ?? null)));
                        @endphp

                        @if ($documentNumber)
                            <div class="employee-document">
                                <small class="text-muted">Documento: {{ $documentNumber }}</small>
                            </div>
                        @endif

                        <!-- Estado actual del descriptor facial -->
                        <div class="descriptor-status">
                            <small id="descState" class="status-text">
                                <span aria-label="Estado del descriptor">Descriptor:</span>
                                <span id="descValue">No capturado</span>
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Formulario para guardar el descriptor facial -->
                <form id="saveForm" method="POST" action="{{ route('face.capture.store', $employee) }}"
                    class="save-form" novalidate>

                    @csrf

                    <!-- Campo oculto para el descriptor facial -->
                    <input type="hidden" name="face_descriptor" id="faceDescriptor" required>

                    <!-- Controles del formulario -->
                    <div class="form-actions row">
                        <!-- Botón para guardar el descriptor -->
                        <button type="submit" id="btnSave" class="btn btn-green" disabled
                            aria-describedby="save-help">
                            <span aria-hidden="true">💾</span> Guardar
                        </button>

                        <!-- Botón para cancelar y regresar -->
                        <button type="button" id="btnCancel" class="btn btn-red" onclick="handleCancel()"
                            aria-label="Cancelar proceso y regresar a la página anterior">
                            <span aria-hidden="true">❌</span> Cancelar
                        </button>
                    </div>

                    <!-- Texto de ayuda para el guardado -->
                    <small id="save-help" class="help-text">
                        <span aria-hidden="true">ℹ️</span>
                        Se promedian múltiples muestras faciales para mayor precisión y estabilidad.
                    </small>
                </form>
            </section>
        </main>
    </div>

    <!-- Modal de confirmación accesible -->
    <div id="confirmationModal" class="modal" style="display: none;" role="dialog" aria-modal="true"
        aria-labelledby="modal-title" aria-describedby="modal-description" tabindex="-1">

        <div class="modal-backdrop"></div>

        <div class="modal-content">
            <!-- Encabezado del modal -->
            <header class="modal-header">
                <h2 id="modal-title">¡Descriptor Guardado Exitosamente!</h2>
            </header>

            <!-- Contenido del modal -->
            <div class="modal-body">
                <p id="modal-description">
                    El descriptor facial del empleado se ha registrado correctamente en el sistema.
                </p>
            </div>

            <!-- Pie del modal -->
            <footer class="modal-footer">
                <button id="closeModal" type="button" class="btn btn-primary"
                    aria-label="Cerrar modal de confirmación y continuar">
                    Aceptar
                </button>
            </footer>
        </div>
    </div>

    <!-- Scripts externos y de la aplicación -->
    <!-- Carga diferida de la biblioteca de reconocimiento facial -->
    <script defer src="https://unpkg.com/face-api.js@0.22.2/dist/face-api.min.js" crossorigin="anonymous"
        onerror="handleScriptError('face-api')"></script>

    <!-- Script de funcionalidad específica -->
    @vite('resources/js/employees/capture-face.js')

    <!-- Script inline para funciones auxiliares -->
    <script>
        // Función para manejar la cancelación
        function handleCancel() {
            if (confirm('¿Está seguro de que desea cancelar la captura? Se perderá el progreso actual.')) {
                window.history.back();
            }
        }

        // Manejo de errores de carga de scripts
        function handleScriptError(scriptName) {
            console.error(`Error cargando ${scriptName}`);
            document.getElementById('status').textContent =
                `Error: No se pudo cargar la biblioteca de reconocimiento facial.`;
        }
    </script>
</body>

</html>
