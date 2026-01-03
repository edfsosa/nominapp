@props(['sectionId' => 'step1-title'])

<section class="card" aria-labelledby="{{ $sectionId }}" role="region">
    <h2 id="{{ $sectionId }}" tabindex="-1">Paso 1 · Identificación</h2>

    <div class="video-wrap" role="img" aria-label="Área de captura de video para reconocimiento facial">
        <video id="video" autoplay playsinline muted disablepictureinpicture
            aria-label="Vista previa de la cámara">
            Tu navegador no soporta video HTML5.
        </video>
        <canvas id="overlay" aria-hidden="true"></canvas>
    </div>

    <div class="row" role="group" aria-label="Controles de identificación">
        <button id="btnStart" type="button" class="btn btn-gray"
            aria-label="Iniciar cámara y cargar modelos de reconocimiento facial"
            aria-describedby="step1-desc">
            Iniciar cámara
        </button>
        <button id="btnIdentify" type="button" class="btn btn-blue"
            aria-label="Identificar empleado mediante reconocimiento facial"
            aria-describedby="step1-desc"
            disabled
            aria-disabled="true">
            Identificar
        </button>
    </div>
    <p id="step1-desc" class="sr-only">
        Primero inicia la cámara, luego posiciona tu rostro frente a ella y presiona el botón Identificar para ser reconocido.
    </p>

    <div id="empCard" class="emp hidden" aria-live="polite" aria-atomic="true" role="status">
        <div>
            <div id="empName" class="emp-name" aria-label="Nombre del empleado"></div>
            <div id="empDoc" aria-label="Documento del empleado"></div>
            <div id="empInfo" aria-label="Información de última marcación"></div>
        </div>
    </div>
</section>
