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
    <noscript>
        <div class="no-js-warning">Esta página requiere JavaScript para funcionar correctamente.</div>
    </noscript>

    <main class="container">
        <h1>Marcación facial</h1>

        <div class="grid">
            <section class="card" aria-labelledby="step1-title">
                <h2 id="step1-title">Paso 1 · Identificación</h2>

                <div class="video-wrap">
                    <video id="video" autoplay playsinline muted disablepictureinpicture
                        aria-label="Vista previa de la cámara">
                        Tu navegador no soporta video HTML5.
                    </video>
                    <canvas id="overlay" aria-hidden="true"></canvas>
                </div>

                <div class="row">
                    <button id="btnStart" type="button" class="btn btn-gray" aria-label="Iniciar cámara">Iniciar
                        cámara</button>
                    <button id="btnIdentify" type="button" class="btn btn-blue" aria-label="Identificar empleado"
                        disabled>Identificar</button>
                    <span id="status" class="status" role="status" aria-live="polite" aria-atomic="true"></span>
                </div>

                <div id="empCard" class="emp" style="display:none" aria-live="polite" aria-atomic="true">
                    <div>
                        <div id="empName" style="font-weight:600"></div>
                        <div id="empDoc" class="status"></div>
                        <div id="empInfo" class="status"></div>
                    </div>
                </div>
            </section>

            <section class="card" aria-labelledby="step2-title">
                <h2 id="step2-title">Paso 2 · Datos de marcación</h2>

                <div class="row">
                    <div>
                        <label for="eventType">Tipo de marcación</label><br>
                        <select id="eventType" disabled>
                            <option value="">— primero identifícate —</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <button id="btnGeo" type="button" class="btn btn-blue" aria-label="Obtener ubicación"
                        disabled>Obtener ubicación</button>
                    <button id="btnMark" type="button" class="btn btn-green" aria-label="Confirmar marcación"
                        disabled>Confirmar marcación</button>
                </div>

                <div class="row">
                    <div>
                        <label for="lat">Lat.</label><br>
                        <input id="lat" type="text" readonly inputmode="numeric" aria-readonly="true">
                    </div>
                    <div>
                        <label for="lng">Lng.</label><br>
                        <input id="lng" type="text" readonly inputmode="numeric" aria-readonly="true">
                    </div>
                </div>
                <p class="status" style="margin-top:8px">La ubicación es <strong>obligatoria</strong> para confirmar.
                </p>
            </section>
        </div>
    </main>

    <div id="successModal" class="modal" style="display: none;" role="dialog" aria-modal="true"
        aria-labelledby="successModalTitle" aria-describedby="successModalDesc">
        <div class="modal-content">
            <h2 id="successModalTitle">¡Marcación registrada!</h2>
            <p id="successModalDesc">Su marcación se ha registrado correctamente.</p>
            <button id="closeModal" type="button">Aceptar</button>
        </div>
    </div>

    <script defer src="https://unpkg.com/face-api.js@0.22.2/dist/face-api.min.js" referrerpolicy="no-referrer"></script>
</body>

</html>
