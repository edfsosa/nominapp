<section id="successScreen" class="terminal-screen hidden" role="region" aria-label="Confirmación de marcación">
    <div class="terminal-content">
        <div class="terminal-success-card">
            <div class="success-icon">✅</div>
            <h1 class="success-title">¡REGISTRADO!</h1>

            <div class="employee-info">
                <div class="employee-name" id="successEmployeeName">Juan Pérez</div>
                <div class="employee-detail" id="successEmployeeCI">CI: 12345678</div>
            </div>

            <div class="mark-details">
                <div class="mark-detail-item">
                    <span class="mark-label">Tipo:</span>
                    <span class="mark-value" id="successEventType">ENTRADA</span>
                </div>
                <div class="mark-detail-item">
                    <span class="mark-label">Hora:</span>
                    <span class="mark-value" id="successTime">08:23:45</span>
                </div>
            </div>

            <div class="auto-close-message" id="autoCloseMessage">
                Volviendo al inicio en <span id="countdown">5</span> segundos...
            </div>

            <div class="terminal-actions">
                <button
                    type="button"
                    id="btnMarkAnother"
                    class="terminal-btn terminal-btn-primary"
                    aria-label="Marcar otra persona inmediatamente">
                    ✨ MARCAR OTRA PERSONA
                </button>
            </div>
        </div>
    </div>
</section>

<section id="errorScreen" class="terminal-screen hidden" role="region" aria-label="Error en marcación">
    <div class="terminal-content">
        <div class="terminal-error-card">
            <div class="error-icon">❌</div>
            <h1 class="error-title">ERROR</h1>

            <div class="error-message" id="errorMessage">
                No se pudo completar la marcación. Por favor, intente nuevamente.
            </div>

            <div class="terminal-actions">
                <button
                    type="button"
                    id="btnRetry"
                    class="terminal-btn terminal-btn-primary"
                    aria-label="Intentar nuevamente">
                    🔄 INTENTAR NUEVAMENTE
                </button>
            </div>
        </div>
    </div>
</section>
