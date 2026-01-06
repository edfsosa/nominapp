<section id="identificationScreen" class="terminal-screen hidden" role="region" aria-label="Identificación facial">
    <div class="terminal-content">
        <h1 class="terminal-title" id="identificationTitle">ENTRADA</h1>
        <p class="terminal-subtitle">Muestre su rostro frente a la cámara</p>

        <div class="terminal-video-container">
            <div class="video-wrapper">
                <video id="terminalVideo" autoplay playsinline muted disablepictureinpicture
                    aria-label="Vista previa de la cámara">
                    Tu navegador no soporta video HTML5.
                </video>
                <canvas id="terminalOverlay" aria-hidden="true"></canvas>
            </div>

            <div class="identification-status" id="identificationStatus" role="status" aria-live="polite">
                <div class="status-icon">🔄</div>
                <div class="status-text">Buscando rostro...</div>
            </div>
        </div>

        <div class="terminal-actions">
            <button
                type="button"
                id="btnCancelIdentification"
                class="terminal-btn terminal-btn-secondary"
                aria-label="Cancelar y volver a selección de tipo">
                ❌ CANCELAR
            </button>
        </div>
    </div>
</section>
