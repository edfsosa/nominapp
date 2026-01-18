document.addEventListener("DOMContentLoaded", () => {
    // ============================================================================
    // ELEMENTOS DEL DOM
    // ============================================================================
    const screens = {
        loading: document.getElementById("loadingScreen"),
        typeSelection: document.getElementById("typeSelectionScreen"),
        identification: document.getElementById("identificationScreen"),
        success: document.getElementById("successScreen"),
        error: document.getElementById("errorScreen"),
    };

    // Loading screen elements
    const loadingMessage = document.getElementById("loadingMessage");
    const loadingProgress = document.getElementById("loadingProgress");
    const loadingPercentage = document.getElementById("loadingPercentage");
    const loadingStep1 = document.getElementById("step1");
    const loadingStep2 = document.getElementById("step2");
    const loadingStep3 = document.getElementById("step3");

    const video = document.getElementById("terminalVideo");
    const overlay = document.getElementById("terminalOverlay");
    const ctx = overlay?.getContext("2d");

    const identificationTitle = document.getElementById("identificationTitle");
    const identificationStatus = document.getElementById("identificationStatus");

    const typeButtons = document.querySelectorAll(".terminal-type-btn");
    const btnCancel = document.getElementById("btnCancelIdentification");
    const btnMarkAnother = document.getElementById("btnMarkAnother");
    const btnRetry = document.getElementById("btnRetry");

    // Success screen elements
    const successEmployeeName = document.getElementById("successEmployeeName");
    const successEmployeeCI = document.getElementById("successEmployeeCI");
    const successEventType = document.getElementById("successEventType");
    const successTime = document.getElementById("successTime");
    const countdownEl = document.getElementById("countdown");

    // Error screen elements
    const errorMessage = document.getElementById("errorMessage");

    // CSRF Token
    const csrfToken = document.querySelector("meta[name=csrf-token]");
    const CSRF = csrfToken ? csrfToken.content : "";

    if (!CSRF) {
        console.warn("Token CSRF no encontrado.");
    }

    const MODELS_URI = "/models";

    // ============================================================================
    // ESTADO GLOBAL DEL TERMINAL
    // ============================================================================
    let terminalState = {
        selectedType: null,
        selectedTypeName: null,
        stream: null,
        modelsLoaded: false,
        identifyInterval: null,
        drawLoopActive: false,
        employee: null,
        countdownTimer: null,
        isProcessing: false, // Flag para bloquear procesamiento simultáneo
    };

    const tinyOptions = new faceapi.TinyFaceDetectorOptions({
        inputSize: 416,      // Aumentado de 320 para mejor precisión
        scoreThreshold: 0.6, // Aumentado de 0.5 para rechazar detecciones de baja calidad
    });

    const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

    // Traducciones de tipos de evento
    const eventTypeNames = {
        check_in: "ENTRADA",
        break_start: "INICIO DESCANSO",
        break_end: "FIN DESCANSO",
        check_out: "SALIDA",
    };

    // ============================================================================
    // FUNCIONES DE PANTALLA DE CARGA
    // ============================================================================
    function updateLoadingProgress(percentage, message, stepNumber) {
        if (loadingProgress) {
            loadingProgress.style.width = `${percentage}%`;
        }
        if (loadingPercentage) {
            loadingPercentage.textContent = `${percentage}%`;
        }
        if (loadingMessage && message) {
            loadingMessage.textContent = message;
        }

        // Actualizar estado de los pasos
        const steps = [loadingStep1, loadingStep2, loadingStep3];
        steps.forEach((step, index) => {
            if (!step) return;

            step.classList.remove("active", "completed");

            if (index + 1 < stepNumber) {
                step.classList.add("completed");
            } else if (index + 1 === stepNumber) {
                step.classList.add("active");
            }
        });
    }

    async function initializeSystem() {
        try {
            // Paso 1: Verificar compatibilidad
            updateLoadingProgress(10, "Verificando compatibilidad del navegador...", 1);
            await sleep(300);

            // Verificar que face-api esté disponible
            if (typeof faceapi === "undefined") {
                throw new Error("La biblioteca face-api.js no está disponible");
            }

            // Verificar soporte de getUserMedia
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                throw new Error("Tu navegador no soporta acceso a la cámara");
            }

            updateLoadingProgress(30, "Navegador compatible ✓", 1);
            await sleep(200);

            // Paso 2: Cargar modelos
            updateLoadingProgress(40, "Cargando modelos de reconocimiento facial...", 2);

            if (!terminalState.modelsLoaded) {
                await Promise.all([
                    faceapi.nets.tinyFaceDetector.loadFromUri(MODELS_URI),
                    faceapi.nets.faceLandmark68Net.loadFromUri(MODELS_URI),
                    faceapi.nets.faceRecognitionNet.loadFromUri(MODELS_URI),
                ]);
                terminalState.modelsLoaded = true;
            }

            updateLoadingProgress(80, "Modelos cargados correctamente ✓", 2);
            await sleep(300);

            // Paso 3: Preparar sistema
            updateLoadingProgress(90, "Preparando sistema de marcación...", 3);
            await sleep(300);

            updateLoadingProgress(100, "Sistema listo ✓", 3);
            await sleep(500);

            // Ocultar pantalla de carga y mostrar selección de tipo
            console.log("Sistema inicializado correctamente");
            showScreen("typeSelection");

        } catch (error) {
            console.error("Error en la inicialización:", error);

            // Mostrar error en la pantalla de carga
            if (loadingMessage) {
                loadingMessage.textContent = `Error: ${error.message}`;
                loadingMessage.style.color = "#ef4444";
            }

            // Después de 3 segundos, mostrar pantalla de error
            await sleep(3000);
            showError("Error al inicializar el sistema. " + error.message + " Por favor, recargue la página.");
        }
    }

    // ============================================================================
    // FUNCIONES DE NAVEGACIÓN ENTRE PANTALLAS
    // ============================================================================
    function showScreen(screenName) {
        // Ocultar todas las pantallas
        Object.values(screens).forEach((screen) => {
            if (screen) screen.classList.add("hidden");
        });

        // Mostrar la pantalla solicitada
        if (screens[screenName]) {
            screens[screenName].classList.remove("hidden");
        }
    }

    // ============================================================================
    // FUNCIONES DE CÁMARA Y FACE-API
    // ============================================================================
    async function loadModels() {
        if (terminalState.modelsLoaded) return true;

        try {
            updateStatus("🔄 Cargando modelos...", "loading");
            await Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri(MODELS_URI),
                faceapi.nets.faceLandmark68Net.loadFromUri(MODELS_URI),
                faceapi.nets.faceRecognitionNet.loadFromUri(MODELS_URI),
            ]);
            terminalState.modelsLoaded = true;
            console.log("Modelos Face-API cargados correctamente");
            return true;
        } catch (error) {
            console.error("Error cargando modelos Face-API:", error);
            showError("Error al cargar el sistema de reconocimiento facial. Por favor, recargue la página.");
            return false;
        }
    }

    async function startCamera() {
        if (terminalState.stream) {
            console.log("Cámara ya está activa");
            return true;
        }

        try {
            updateStatus("📷 Iniciando cámara...", "loading");

            terminalState.stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: "user" },
                audio: false,
            });

            video.srcObject = terminalState.stream;
            await new Promise((resolve) => {
                video.onloadedmetadata = resolve;
            });

            // Ajustar tamaño del canvas
            overlay.width = video.videoWidth;
            overlay.height = video.videoHeight;

            console.log("Cámara iniciada correctamente");
            return true;
        } catch (error) {
            console.error("Error al iniciar cámara:", error);

            let message = "No se pudo acceder a la cámara. ";
            if (error.name === "NotAllowedError") {
                message += "Por favor, permita el acceso a la cámara.";
            } else if (error.name === "NotFoundError") {
                message += "No se encontró ninguna cámara conectada.";
            } else if (error.name === "NotReadableError") {
                message += "La cámara está siendo usada por otra aplicación.";
            } else {
                message += "Error desconocido: " + error.message;
            }

            showError(message);
            return false;
        }
    }

    function stopCamera() {
        if (terminalState.stream) {
            terminalState.stream.getTracks().forEach((track) => track.stop());
            terminalState.stream = null;
            video.srcObject = null;
            console.log("Cámara detenida");
        }
        stopDrawLoop();
    }

    function startDrawLoop() {
        if (terminalState.drawLoopActive) return;
        terminalState.drawLoopActive = true;
        drawLoop();
    }

    function stopDrawLoop() {
        terminalState.drawLoopActive = false;
    }

    async function drawLoop() {
        if (!terminalState.drawLoopActive) return;

        try {
            const detection = await faceapi
                .detectSingleFace(video, tinyOptions)
                .withFaceLandmarks()
                .withFaceDescriptor();

            // Limpiar canvas
            ctx.clearRect(0, 0, overlay.width, overlay.height);

            if (detection) {
                // Dibujar detección
                const dims = faceapi.matchDimensions(overlay, video, true);
                const resized = faceapi.resizeResults(detection, dims);
                faceapi.draw.drawDetections(overlay, resized);
                faceapi.draw.drawFaceLandmarks(overlay, resized);
            }
        } catch (error) {
            console.error("Error en drawLoop:", error);
        }

        setTimeout(drawLoop, 200);
    }

    async function captureDescriptor(samples = 5, intervalMs = 150) {
        const descriptors = [];
        const MIN_FACE_SIZE = 100; // Tamaño mínimo de rostro en píxeles

        for (let i = 0; i < samples; i++) {
            try {
                const detection = await faceapi
                    .detectSingleFace(video, tinyOptions)
                    .withFaceLandmarks()
                    .withFaceDescriptor();

                if (detection && detection.descriptor) {
                    // Validar tamaño del rostro detectado
                    const box = detection.detection.box;
                    if (box.width >= MIN_FACE_SIZE && box.height >= MIN_FACE_SIZE) {
                        descriptors.push(Array.from(detection.descriptor));
                    } else {
                        console.warn(`Rostro muy pequeño: ${Math.round(box.width)}x${Math.round(box.height)}px (mínimo ${MIN_FACE_SIZE}px)`);
                    }
                }
            } catch (error) {
                console.error(`Error capturando muestra ${i + 1}:`, error);
            }

            if (i < samples - 1) {
                await sleep(intervalMs);
            }
        }

        // Requerir mínimo 3 muestras válidas de las 5 intentadas
        if (descriptors.length < 3) {
            throw new Error(`Solo se capturaron ${descriptors.length} muestras válidas (mínimo 3). Acerque el rostro a la cámara.`);
        }

        // Promediar descriptores
        const averaged = new Array(128).fill(0);
        for (let i = 0; i < 128; i++) {
            let sum = 0;
            for (const desc of descriptors) {
                sum += desc[i];
            }
            averaged[i] = sum / descriptors.length;
        }

        return averaged;
    }

    function updateStatus(text, icon = "loading") {
        if (!identificationStatus) return;

        const icons = {
            loading: "🔄",
            searching: "👤",
            found: "✅",
            error: "❌",
        };

        identificationStatus.innerHTML = `
            <div class="status-icon">${icons[icon] || icons.loading}</div>
            <div class="status-text">${text}</div>
        `;
    }

    // ============================================================================
    // FUNCIONES DE IDENTIFICACIÓN
    // ============================================================================
    async function identifyEmployee(descriptor) {
        try {
            const response = await fetch("/marcar/identificar", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": CSRF,
                },
                body: JSON.stringify({
                    face_descriptor: descriptor,
                }),
            });

            const data = await response.json();
            return data;
        } catch (error) {
            console.error("Error en identificación:", error);
            return { ok: false, message: "Error de conexión" };
        }
    }

    async function startAutoIdentification() {
        // Cargar modelos
        const modelsLoaded = await loadModels();
        if (!modelsLoaded) return;

        // Iniciar cámara
        const cameraStarted = await startCamera();
        if (!cameraStarted) return;

        // Iniciar draw loop
        startDrawLoop();

        updateStatus("Buscando rostro...", "searching");

        // Intentar identificar cada 3 segundos
        terminalState.identifyInterval = setInterval(async () => {
            // Verificar si ya hay un proceso en curso
            if (terminalState.isProcessing) {
                console.log("Proceso en curso, omitiendo ciclo de identificación");
                return;
            }

            try {
                // Marcar inicio de procesamiento
                terminalState.isProcessing = true;

                updateStatus("Analizando rostro...", "loading");

                const descriptor = await captureDescriptor(5, 150);
                const result = await identifyEmployee(descriptor);

                if (result.ok && result.employee) {
                    // Empleado identificado correctamente
                    console.log("Empleado identificado:", result.employee);
                    stopAutoIdentification();
                    await registerMark(result.employee);
                } else {
                    // No se pudo identificar, seguir intentando
                    updateStatus("Rostro no reconocido. Intente de nuevo...", "searching");
                    console.log("No se pudo identificar:", result.message);
                }
            } catch (error) {
                console.error("Error en auto-identificación:", error);
                updateStatus("Error al analizar. Reintentando...", "error");
            } finally {
                // Liberar el bloqueo siempre (excepto si ya se detuvo por éxito)
                if (terminalState.identifyInterval) {
                    terminalState.isProcessing = false;
                }
            }
        }, 3000);
    }

    function stopAutoIdentification() {
        if (terminalState.identifyInterval) {
            clearInterval(terminalState.identifyInterval);
            terminalState.identifyInterval = null;
        }
        stopCamera();
    }

    // ============================================================================
    // FUNCIONES DE REGISTRO DE MARCACIÓN
    // ============================================================================
    async function registerMark(employee) {
        try {
            updateStatus("Registrando marcación...", "loading");

            const response = await fetch("/marcar", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": CSRF,
                },
                body: JSON.stringify({
                    employee_id: employee.id,
                    event_type: terminalState.selectedType,
                    // NO enviar location - el backend usará las coordenadas de la sucursal
                }),
            });

            const data = await response.json();

            if (data.ok) {
                showSuccessScreen(employee, data.data);
            } else {
                showError(data.message || "No se pudo registrar la marcación.");
            }
        } catch (error) {
            console.error("Error al registrar marcación:", error);
            showError("Error de conexión al registrar la marcación.");
        }
    }

    // ============================================================================
    // FUNCIONES DE PANTALLAS DE RESULTADO
    // ============================================================================
    function showSuccessScreen(employee, markData) {
        // Actualizar información del empleado
        const fullName = `${employee.first_name || ""} ${employee.last_name || ""}`.trim();
        successEmployeeName.textContent = fullName || "Empleado";
        successEmployeeCI.textContent = employee.ci ? `CI: ${employee.ci}` : "";

        // Actualizar tipo de marcación
        successEventType.textContent = terminalState.selectedTypeName || "";

        // Actualizar hora
        const now = new Date();
        successTime.textContent = now.toLocaleTimeString("es-BO", {
            hour: "2-digit",
            minute: "2-digit",
            second: "2-digit",
        });

        // Mostrar pantalla de éxito
        showScreen("success");

        // Iniciar countdown de 5 segundos
        startCountdown(5);
    }

    function showError(message) {
        errorMessage.textContent = message;
        showScreen("error");
    }

    function startCountdown(seconds) {
        let remaining = seconds;
        countdownEl.textContent = remaining;

        terminalState.countdownTimer = setInterval(() => {
            remaining--;
            countdownEl.textContent = remaining;

            if (remaining <= 0) {
                clearInterval(terminalState.countdownTimer);
                resetTerminal();
            }
        }, 1000);
    }

    function stopCountdown() {
        if (terminalState.countdownTimer) {
            clearInterval(terminalState.countdownTimer);
            terminalState.countdownTimer = null;
        }
    }

    // ============================================================================
    // FUNCIONES DE RESET
    // ============================================================================
    function resetTerminal() {
        stopAutoIdentification();
        stopCountdown();

        terminalState = {
            selectedType: null,
            selectedTypeName: null,
            stream: null,
            modelsLoaded: terminalState.modelsLoaded, // Mantener modelos cargados
            identifyInterval: null,
            drawLoopActive: false,
            employee: null,
            countdownTimer: null,
            isProcessing: false, // Reset del flag de procesamiento
        };

        showScreen("typeSelection");
    }

    // ============================================================================
    // EVENT LISTENERS
    // ============================================================================

    // Botones de selección de tipo
    typeButtons.forEach((button) => {
        button.addEventListener("click", () => {
            const eventType = button.getAttribute("data-event-type");
            const eventName = eventTypeNames[eventType] || eventType;

            terminalState.selectedType = eventType;
            terminalState.selectedTypeName = eventName;

            // Actualizar título de identificación
            identificationTitle.textContent = eventName;

            // Ir a pantalla de identificación
            showScreen("identification");

            // Iniciar auto-identificación
            startAutoIdentification();
        });
    });

    // Botón cancelar identificación
    if (btnCancel) {
        btnCancel.addEventListener("click", () => {
            stopAutoIdentification();
            resetTerminal();
        });
    }

    // Botón marcar otra persona (desde pantalla de éxito)
    if (btnMarkAnother) {
        btnMarkAnother.addEventListener("click", () => {
            resetTerminal();
        });
    }

    // Botón reintentar (desde pantalla de error)
    if (btnRetry) {
        btnRetry.addEventListener("click", () => {
            resetTerminal();
        });
    }

    // ============================================================================
    // INICIALIZACIÓN
    // ============================================================================
    console.log("Terminal de marcación inicializado");

    // Mostrar pantalla de carga e iniciar el sistema
    showScreen("loading");
    initializeSystem();
});
