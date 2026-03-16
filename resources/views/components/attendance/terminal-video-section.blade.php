<section id="identificationScreen" class="terminal-screen hidden" role="region" aria-label="Identificación facial">
    <div class="screen-body">
        <div class="identification-layout">
            <div class="video-wrapper" id="terminalVideoWrap">
                <video id="terminalVideo" autoplay playsinline muted disablepictureinpicture
                    aria-label="Vista previa de la cámara">
                    Tu navegador no soporta video HTML5.
                </video>
                <canvas id="terminalOverlay" aria-hidden="true"></canvas>
                <div class="terminal-blur-overlay" aria-hidden="true"></div>
                <div class="terminal-face-guide" aria-hidden="true">
                    <div class="terminal-face-oval"></div>
                </div>
                {{-- Progreso de captura superpuesto en la parte inferior del video --}}
                <div id="terminalCaptureProgress" class="capture-progress hidden" aria-hidden="true">
                    <span class="capture-dot"></span>
                    <span class="capture-dot"></span>
                    <span class="capture-dot"></span>
                    <span class="capture-dot"></span>
                    <span class="capture-dot"></span>
                </div>
            </div>

            <div class="identification-status" id="identificationStatus" role="status" aria-live="polite">
                <span class="id-status-dot" id="idStatusDot"></span>
                <span class="id-status-text">Posicione su rostro dentro del óvalo...</span>
            </div>

            {{-- Cancel button kept in DOM for JS compatibility, hidden via CSS --}}
            <button
                type="button"
                id="btnCancelIdentification"
                class="terminal-btn terminal-btn-secondary"
                aria-label="Cancelar y volver a selección de tipo">
                Cancelar
            </button>
        </div>
    </div>
</section>
