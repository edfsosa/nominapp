<section id="typeSelectionScreen" class="terminal-screen hidden" role="region" aria-label="Selección de tipo de marcación">
    <div class="terminal-content">
        <h1 class="terminal-title">MARCACIÓN DE ASISTENCIA</h1>
        <p class="terminal-subtitle">Seleccione el tipo de marcación</p>

        <div class="terminal-buttons">
            <button
                type="button"
                class="terminal-type-btn btn-check-in"
                data-event-type="check_in"
                aria-label="Marcar entrada">
                <span class="btn-icon">🟢</span>
                <span class="btn-text">ENTRADA</span>
            </button>

            <button
                type="button"
                class="terminal-type-btn btn-break-start"
                data-event-type="break_start"
                aria-label="Marcar inicio de descanso">
                <span class="btn-icon">⏸️</span>
                <span class="btn-text">INICIO DESCANSO</span>
            </button>

            <button
                type="button"
                class="terminal-type-btn btn-break-end"
                data-event-type="break_end"
                aria-label="Marcar fin de descanso">
                <span class="btn-icon">▶️</span>
                <span class="btn-text">FIN DESCANSO</span>
            </button>

            <button
                type="button"
                class="terminal-type-btn btn-check-out"
                data-event-type="check_out"
                aria-label="Marcar salida">
                <span class="btn-icon">🔴</span>
                <span class="btn-text">SALIDA</span>
            </button>
        </div>
    </div>
</section>
