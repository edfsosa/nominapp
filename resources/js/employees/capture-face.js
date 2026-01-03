/**
 * ==========================================================================
 * CAPTURA FACIAL PARA EMPLEADOS
 * Archivo: capture-face.js
 * Descripción: Manejo de captura y procesamiento de descriptores faciales
 * Dependencias: face-api.js
 * ==========================================================================
 */

/**
 * Configuración global de la aplicación
 */
const CONFIG = {
    // Rutas y recursos
    MODELS_URI: "/models", // Asegúrate de que esta carpeta exista en public/models

    // Configuración de detección facial
    DETECTION: {
        inputSize: 320,
        scoreThreshold: 0.5,
        maxFaces: 1,
    },

    // Configuración de captura
    CAPTURE: {
        samples: 5, // Número de muestras para promediar
        intervalMs: 160, // Intervalo entre muestras
        descriptorLength: 128, // Longitud del descriptor facial
    },

    // Configuración de video
    VIDEO: {
        facingMode: "user",
        width: { ideal: 640 },
        height: { ideal: 480 },
        frameRate: { ideal: 30, max: 30 },
    },

    // Configuración de rendimiento
    PERFORMANCE: {
        drawLoopInterval: 100, // ms entre frames de dibujo
        maxRetries: 3, // Reintentos máximos
        timeoutMs: 10000, // Timeout para operaciones
    },

    // Mensajes de la aplicación
    MESSAGES: {
        LOADING_MODELS: "Cargando modelos de reconocimiento facial...",
        MODELS_LOADED: "Modelos cargados correctamente",
        MODELS_ERROR: "Error al cargar los modelos de reconocimiento",
        CAMERA_STARTING: "Iniciando cámara...",
        CAMERA_READY: "Cámara lista. Posiciona tu rostro en el marco",
        CAMERA_ERROR: "No se pudo acceder a la cámara",
        CAMERA_STOPPED: "Cámara detenida",
        FACE_DETECTED: "Rostro detectado correctamente",
        NO_FACE: "No se detecta ningún rostro. Ajusta tu posición",
        CAPTURING: "Capturando descriptor facial... mantén la pose",
        CAPTURE_SUCCESS: "Descriptor capturado exitosamente",
        CAPTURE_ERROR: "Error durante la captura",
        SAVING: "Guardando descriptor en el servidor...",
        SAVE_SUCCESS: "Descriptor guardado correctamente",
        SAVE_ERROR: "Error al guardar el descriptor",
    },
};

/**
 * Clase principal para manejo de captura facial
 */
class FaceCaptureApp {
    constructor() {
        this.initializeElements();
        this.initializeState();
        this.setupEventListeners();
        this.setupFaceApiOptions();
    }

    /**
     * Inicializa las referencias a elementos DOM
     */
    initializeElements() {
        // Elementos de video y canvas
        this.video = document.getElementById("video");
        this.overlay = document.getElementById("overlay");
        this.ctx = this.overlay?.getContext("2d");

        // Botones de control
        this.btnStart = document.getElementById("btnStart");
        this.btnCapture = document.getElementById("btnCapture");
        this.btnSave = document.getElementById("btnSave");
        this.btnCancel = document.getElementById("btnCancel");

        // Elementos de estado
        this.statusEl = document.getElementById("status");
        this.descStateEl =
            document.getElementById("descState") ||
            document.getElementById("descValue");
        this.hiddenDescriptor = document.getElementById("faceDescriptor");

        // Modal
        this.modal = document.getElementById("confirmationModal");
        this.closeModalBtn = document.getElementById("closeModal");

        // Formulario
        this.saveForm = document.getElementById("saveForm");

        // Validar elementos críticos
        this.validateCriticalElements();
    }

    /**
     * Valida que los elementos críticos existan
     */
    validateCriticalElements() {
        const critical = [
            { element: this.video, name: "video" },
            { element: this.overlay, name: "overlay" },
            { element: this.ctx, name: "canvas context" },
            { element: this.statusEl, name: "status element" },
        ];

        const missing = critical.filter((item) => !item.element);
        if (missing.length > 0) {
            const missingNames = missing.map((item) => item.name).join(", ");
            throw new Error(
                `Elementos críticos no encontrados: ${missingNames}`
            );
        }
    }

    /**
     * Inicializa el estado de la aplicación
     */
    initializeState() {
        this.stream = null;
        this.isDetecting = false;
        this.isCapturing = false;
        this.modelsLoaded = false;
        this.currentDescriptor = null;
        this.detectionAnimationId = null;
        this.retryCount = 0;
    }

    /**
     * Configura los event listeners
     */
    setupEventListeners() {
        // Botones principales
        this.btnStart?.addEventListener(
            "click",
            this.handleStartCamera.bind(this)
        );
        this.btnCapture?.addEventListener(
            "click",
            this.handleCaptureDescriptor.bind(this)
        );
        this.btnSave?.addEventListener(
            "click",
            this.handleSaveDescriptor.bind(this)
        );

        // Modal
        this.closeModalBtn?.addEventListener(
            "click",
            this.handleCloseModal.bind(this)
        );
        this.modal?.addEventListener(
            "click",
            this.handleModalBackdropClick.bind(this)
        );

        // Formulario
        this.saveForm?.addEventListener(
            "submit",
            this.handleFormSubmit.bind(this)
        );

        // Eventos de ventana
        window.addEventListener(
            "beforeunload",
            this.handleBeforeUnload.bind(this)
        );
        window.addEventListener("resize", this.handleResize.bind(this));

        // Eventos de teclado para accesibilidad
        document.addEventListener("keydown", this.handleKeydown.bind(this));

        // Eventos de video
        this.video?.addEventListener(
            "loadedmetadata",
            this.handleVideoLoaded.bind(this)
        );
        this.video?.addEventListener("error", this.handleVideoError.bind(this));
    }

    /**
     * Configura las opciones de face-api
     */
    setupFaceApiOptions() {
        if (typeof faceapi !== "undefined") {
            this.tinyOptions = new faceapi.TinyFaceDetectorOptions({
                inputSize: CONFIG.DETECTION.inputSize,
                scoreThreshold: CONFIG.DETECTION.scoreThreshold,
            });
        }
    }

    /**
     * Maneja el inicio de la cámara
     */
    async handleStartCamera() {
        try {
            const isRestarting = this.currentDescriptor !== null;
            const buttonText = isRestarting ? "Reiniciando..." : "Iniciando...";

            this.setButtonState(this.btnStart, false, buttonText);
            this.updateStatus(CONFIG.MESSAGES.CAMERA_STARTING);

            // Cargar modelos si no están cargados
            if (!this.modelsLoaded) {
                await this.loadModels();
            }

            await this.startCamera();

            // Actualizar UI según el contexto
            if (isRestarting) {
                this.setButtonState(this.btnStart, false, "Cámara Activa");
                this.setButtonState(
                    this.btnCapture,
                    true,
                    "Recapturar Descriptor"
                );
                this.updateStatus(
                    "Cámara reiniciada. Puedes recapturar el descriptor si es necesario."
                );
            } else {
                this.setButtonState(this.btnStart, false, "Cámara Activa");
                this.setButtonState(this.btnCapture, true);
                this.updateStatus(CONFIG.MESSAGES.CAMERA_READY);
            }
        } catch (error) {
            this.handleError("Error al iniciar cámara", error);
            this.setButtonState(this.btnStart, true, "Reintentar");
        }
    }

    /**
     * Maneja la captura del descriptor
     */
    async handleCaptureDescriptor() {
        if (this.isCapturing) return;

        try {
            this.isCapturing = true;
            this.setButtonState(this.btnCapture, false, "Capturando...");
            this.updateStatus(CONFIG.MESSAGES.CAPTURING);

            const descriptor = await this.captureDescriptor();
            this.currentDescriptor = descriptor;
            this.hiddenDescriptor.value = JSON.stringify(descriptor);

            // NUEVO: Detener cámara después de captura exitosa
            await this.stopCamera();

            // Actualizar UI para estado post-captura
            this.setButtonState(this.btnSave, true);
            this.setButtonState(this.btnStart, true, "Reiniciar Cámara"); // Cambiar texto
            this.setButtonState(this.btnCapture, false, "Capturar Descriptor"); // Deshabilitar hasta reiniciar

            this.updateDescriptorState("Capturado ✅");
            this.updateStatus(
                "Descriptor capturado exitosamente. Cámara detenida para ahorrar recursos."
            );
        } catch (error) {
            this.handleError("Error en captura", error);
        } finally {
            this.isCapturing = false;
        }
    }

    /**
     * Maneja el guardado del descriptor
     */
    async handleSaveDescriptor(event) {
        if (event) {
            event.preventDefault();
        }

        if (!this.currentDescriptor) {
            this.updateStatus("No hay descriptor para guardar");
            return;
        }

        try {
            // Deshabilitar botones y mostrar estado de guardado
            this.setButtonState(this.btnSave, false, "Guardando...");
            this.setButtonState(this.btnStart, false); // Deshabilitar botón de iniciar cámara
            this.updateStatus(CONFIG.MESSAGES.SAVING);

            // Guardar el descriptor usando AJAX
            await this.saveDescriptorAjax();

            // Mostrar modal de éxito
            console.log("Guardado exitoso, mostrando modal...");
            this.showSuccessModal();
        } catch (error) {
            // Manejar errores y reactivar el botón de guardar
            this.handleError("Error al guardar", error);
            this.setButtonState(this.btnSave, true, "Guardar");
        }
    }

    /**
     * NUEVO: Método para manejar recaptura
     */
    async handleRecapture() {
        try {
            // Limpiar descriptor actual
            this.currentDescriptor = null;
            this.hiddenDescriptor.value = "";

            // Reiniciar cámara si no está activa
            if (!this.isDetecting) {
                await this.handleStartCamera();
            }

            // Actualizar UI
            this.setButtonState(this.btnSave, false, "Guardar");
            this.setButtonState(this.btnCapture, true, "Capturar Descriptor");
            this.updateDescriptorState("No capturado");
            this.updateStatus("Listo para nueva captura");
        } catch (error) {
            this.handleError("Error al reiniciar captura", error);
        }
    }

    /**
     * Guarda el descriptor usando AJAX
     */
    async saveDescriptorAjax() {
        const csrfToken = document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute("content");
        const formData = new FormData();
        formData.append(
            "face_descriptor",
            JSON.stringify(this.currentDescriptor)
        );
        formData.append("_token", csrfToken);

        const response = await fetch(this.saveForm.action, {
            method: "POST",
            body: formData,
            headers: {
                "X-Requested-With": "XMLHttpRequest",
            },
        });

        console.log("Respuesta del servidor:", response);

        if (!response.ok) {
            throw new Error(`Error del servidor: ${response.status}`);
        }

        const data = await response.json();
        console.log("Datos recibidos:", data);
        if (data.success) {
            this.updateStatus(CONFIG.MESSAGES.SAVE_SUCCESS);
        } else {
            throw new Error(data.message || "Error desconocido al guardar");
        }

        return data;
    }

    /**
     * Carga los modelos de face-api
     */
    async loadModels() {
        if (typeof faceapi === "undefined") {
            throw new Error("La biblioteca face-api.js no está disponible");
        }

        this.updateStatus(CONFIG.MESSAGES.LOADING_MODELS);

        try {
            await Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri(CONFIG.MODELS_URI),
                faceapi.nets.faceLandmark68Net.loadFromUri(CONFIG.MODELS_URI),
                faceapi.nets.faceRecognitionNet.loadFromUri(CONFIG.MODELS_URI),
            ]);

            this.modelsLoaded = true;
            this.updateStatus(CONFIG.MESSAGES.MODELS_LOADED);
        } catch (error) {
            throw new Error(
                `${CONFIG.MESSAGES.MODELS_ERROR}: ${error.message}`
            );
        }
    }

    /**
     * Inicia la cámara
     */
    async startCamera() {
        if (!navigator.mediaDevices?.getUserMedia) {
            throw new Error("Tu navegador no soporta acceso a la cámara");
        }

        try {
            // Detener cámara anterior si existe
            await this.stopCamera();

            this.stream = await navigator.mediaDevices.getUserMedia({
                video: CONFIG.VIDEO,
                audio: false,
            });

            this.video.srcObject = this.stream;

            // Esperar a que el video esté listo
            await new Promise((resolve, reject) => {
                const timeout = setTimeout(() => {
                    reject(new Error("Timeout al cargar video"));
                }, CONFIG.PERFORMANCE.timeoutMs);

                this.video.onloadedmetadata = () => {
                    clearTimeout(timeout);
                    resolve();
                };

                this.video.onerror = () => {
                    clearTimeout(timeout);
                    reject(new Error("Error al cargar video"));
                };
            });

            this.setupCanvas();
            this.startDetection();
            this.updateStatus(CONFIG.MESSAGES.CAMERA_READY);
        } catch (error) {
            await this.stopCamera();
            throw new Error(
                `${CONFIG.MESSAGES.CAMERA_ERROR}: ${error.message}`
            );
        }
    }

    /**
     * Configura el canvas de overlay
     */
    setupCanvas() {
        this.overlay.width = this.video.videoWidth;
        this.overlay.height = this.video.videoHeight;

        // Configurar contexto para mejor calidad
        this.ctx.imageSmoothingEnabled = true;
        this.ctx.imageSmoothingQuality = "high";
    }

    /**
     * Inicia el bucle de detección
     */
    startDetection() {
        this.isDetecting = true;
        this.detectLoop();
    }

    /**
     * Bucle principal de detección facial
     */
    async detectLoop() {
        if (!this.isDetecting) return;

        try {
            const detection = await faceapi
                .detectSingleFace(this.video, this.tinyOptions)
                .withFaceLandmarks();

            this.clearCanvas();

            if (detection) {
                this.drawDetection(detection);
                if (!this.isCapturing) {
                    this.updateStatus(CONFIG.MESSAGES.FACE_DETECTED);
                }
            } else {
                if (!this.isCapturing) {
                    this.updateStatus(CONFIG.MESSAGES.NO_FACE);
                }
            }
        } catch (error) {
            console.warn("Error en detección:", error);
        }

        // Programar siguiente frame
        this.detectionAnimationId = setTimeout(() => {
            requestAnimationFrame(() => this.detectLoop());
        }, CONFIG.PERFORMANCE.drawLoopInterval);
    }

    /**
     * Limpia el canvas
     */
    clearCanvas() {
        this.ctx.clearRect(0, 0, this.overlay.width, this.overlay.height);
    }

    /**
     * Dibuja la detección en el canvas
     */
    drawDetection(detection) {
        const displaySize = {
            width: this.video.videoWidth,
            height: this.video.videoHeight,
        };

        const resizedDetection = faceapi.resizeResults(detection, displaySize);

        // Personalizar colores para mejor visibilidad
        const drawOptions = {
            lineWidth: 2,
            color: "rgba(0, 255, 0, 0.8)",
        };

        faceapi.draw.drawDetections(
            this.overlay,
            [resizedDetection],
            drawOptions
        );
        faceapi.draw.drawFaceLandmarks(
            this.overlay,
            [resizedDetection],
            drawOptions
        );
    }

    /**
     * Captura y promedia múltiples descriptores
     */
    async captureDescriptor(
        samples = CONFIG.CAPTURE.samples,
        intervalMs = CONFIG.CAPTURE.intervalMs
    ) {
        const descriptors = [];
        let attempts = 0;
        const maxAttempts = samples * 3; // Permitir más intentos

        while (descriptors.length < samples && attempts < maxAttempts) {
            attempts++;

            try {
                const detection = await faceapi
                    .detectSingleFace(this.video, this.tinyOptions)
                    .withFaceLandmarks()
                    .withFaceDescriptor();

                if (detection?.descriptor) {
                    descriptors.push(detection.descriptor);
                    this.updateStatus(
                        `Capturando (${descriptors.length}/${samples})... mantén la pose`
                    );
                } else {
                    this.updateStatus(
                        `Buscando rostro... (${descriptors.length}/${samples})`
                    );
                }

                await this.sleep(intervalMs);
            } catch (error) {
                console.warn("Error en captura individual:", error);
                await this.sleep(intervalMs * 2);
            }
        }

        if (descriptors.length < samples) {
            throw new Error(
                `Solo se capturaron ${descriptors.length} de ${samples} muestras`
            );
        }

        return this.averageDescriptors(descriptors);
    }

    /**
     * Calcula el promedio de múltiples descriptores
     */
    averageDescriptors(descriptors) {
        const length = CONFIG.CAPTURE.descriptorLength;
        const averaged = new Float32Array(length).fill(0);

        // Sumar todos los descriptors
        for (const descriptor of descriptors) {
            for (let i = 0; i < length; i++) {
                averaged[i] += descriptor[i];
            }
        }

        // Calcular promedio
        for (let i = 0; i < length; i++) {
            averaged[i] /= descriptors.length;
        }

        return Array.from(averaged);
    }

    /**
     * Detiene la cámara
     */
    async stopCamera() {
        if (this.stream) {
            this.stream.getTracks().forEach((track) => {
                track.stop();
            });
            this.stream = null;
        }

        this.isDetecting = false;

        if (this.detectionAnimationId) {
            clearTimeout(this.detectionAnimationId);
            this.detectionAnimationId = null;
        }

        this.clearCanvas();
        this.updateStatus(CONFIG.MESSAGES.CAMERA_STOPPED);
    }

    /**
     * Muestra el modal de éxito
     */
    showSuccessModal() {
        console.log("Intentando mostrar el modal de éxito...");
        if (this.modal) {
            console.log("Modal encontrado, configurando estilos...");
            this.modal.style.display = "flex"; // Cambiar display a flex
            this.modal.style.opacity = "1"; // Hacerlo visible
            this.modal.style.visibility = "visible"; // Asegurar que sea visible

            // Enfocar el botón de cerrar para accesibilidad
            setTimeout(() => {
                console.log("Enfocando el botón de cerrar...");
                this.closeModalBtn?.focus();
            }, 100);
        } else {
            console.error("Modal de éxito no encontrado");
        }
    }

    /**
     * Cierra el modal
     */
    hideModal() {
        if (this.modal) {
            this.modal.style.display = "none";;
            this.modal.style.opacity = "0";
            this.modal.style.visibility = "hidden";

            window.location.href = "/employees"; // Redirigir a la lista de empleados
        }
    }

    /**
     * Event handlers
     */
    handleCloseModal() {
        this.hideModal();
        this.resetCaptureState();
        // Asegurar que la cámara esté detenida
        this.stopCamera();
    }

    handleModalBackdropClick(event) {
        if (event.target === this.modal) {
            this.hideModal();
        }
    }

    handleFormSubmit(event) {
        // El manejo se hace en handleSaveDescriptor
        return true;
    }

    async handleBeforeUnload() {
        await this.stopCamera();
    }

    handleResize() {
        if (this.video && this.overlay && this.video.videoWidth > 0) {
            this.setupCanvas();
        }
    }

    handleKeydown(event) {
        // Cerrar modal con Escape
        if (event.key === "Escape" && this.modal?.style.display !== "none") {
            event.preventDefault();
            this.hideModal();
        }
    }

    handleVideoLoaded() {
        this.setupCanvas();
    }

    handleVideoError(event) {
        this.handleError(
            "Error de video",
            new Error("Error al reproducir video")
        );
    }

    /**
     * Métodos de utilidad
     */
    updateStatus(message) {
        if (this.statusEl) {
            this.statusEl.textContent = message;
            this.statusEl.setAttribute("aria-live", "polite");
            console.log(`Status: ${message}`);
        }
    }

    updateDescriptorState(state) {
        if (this.descStateEl) {
            if (this.descStateEl.tagName === "SPAN") {
                this.descStateEl.textContent = state;
            } else {
                this.descStateEl.innerHTML = `Descriptor: <span id="descValue">${state}</span>`;
            }
        }
    }

    setButtonState(button, enabled, text = null) {
        if (!button) return;

        button.disabled = !enabled;
        button.setAttribute("aria-disabled", (!enabled).toString());

        if (text) {
            // Encontrar el span con emoji (aria-hidden="true")
            const emojiSpan = button.querySelector('span[aria-hidden="true"]');

            if (emojiSpan) {
                // Si hay emoji, mantenerlo y reemplazar solo el texto
                const emoji = emojiSpan.textContent || emojiSpan.innerText;
                button.innerHTML = `<span aria-hidden="true">${emoji}</span> ${text}`;
            } else {
                // Si no hay emoji, solo establecer el texto
                button.textContent = text;
            }
        }
    }

    resetCaptureState() {
        this.currentDescriptor = null;
        this.hiddenDescriptor.value = "";
        this.setButtonState(this.btnSave, false, "Guardar");
        this.setButtonState(this.btnStart, true, "Iniciar Cámara");
        this.setButtonState(this.btnCapture, false, "Capturar Descriptor");
        this.updateDescriptorState("No capturado");
        this.updateStatus("Presiona 'Iniciar Cámara' para comenzar");
    }

    handleError(context, error) {
        const message = `${context}: ${error.message}`;
        console.error(message, error);
        this.updateStatus(message);

        // Mostrar error en UI si es crítico
        if (context.includes("modelos") || context.includes("cámara")) {
            alert(message);
        }
    }

    sleep(ms) {
        return new Promise((resolve) => setTimeout(resolve, ms));
    }
}

/**
 * Inicialización de la aplicación
 */
document.addEventListener("DOMContentLoaded", () => {
    try {
        // Verificar que face-api esté disponible
        if (typeof faceapi === "undefined") {
            console.error("face-api.js no está cargado");
            document.getElementById("status").textContent =
                "Error: No se pudo cargar la biblioteca de reconocimiento facial";
            return;
        }

        // Inicializar aplicación
        window.faceCaptureApp = new FaceCaptureApp();
        console.log("Aplicación de captura facial inicializada");
    } catch (error) {
        console.error("Error al inicializar la aplicación:", error);
        document.getElementById(
            "status"
        ).textContent = `Error de inicialización: ${error.message}`;
    }
});

/**
 * Exportar para testing (si es necesario)
 */
if (typeof module !== "undefined" && module.exports) {
    module.exports = { FaceCaptureApp, CONFIG };
}
