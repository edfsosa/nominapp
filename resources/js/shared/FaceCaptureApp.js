/**
 * =============================================================================
 * MÓDULO COMPARTIDO - CAPTURA FACIAL
 * =============================================================================
 *
 * @fileoverview Clase base y configuración para captura de descriptores faciales.
 *               Usado tanto por el enrolamiento de admin como por el auto-registro
 *               de empleados.
 *
 * @requires face-api.js - Biblioteca de reconocimiento facial
 * @requires Los modelos deben estar en /models (tinyFaceDetector, faceLandmark68, faceRecognition)
 *
 * @author Sistema RRHH
 * @version 2.1.0
 */

/**
 * Configuración global de la aplicación
 */
export const CONFIG = {
    // Ruta a los modelos de face-api.js
    MODELS_URI: "/models",

    // Configuración de detección facial (sincronizado con terminal.js y mark.js)
    DETECTION: {
        inputSize: 416,      // Aumentado de 320 para mejor precisión
        scoreThreshold: 0.6, // Aumentado de 0.5 para rechazar detecciones de baja calidad
        maxFaces: 1,
    },

    // Configuración de captura (más muestras para registro que para identificación)
    CAPTURE: {
        samples: 7,          // Número de muestras a intentar capturar
        minSamples: 5,       // Mínimo de muestras válidas requeridas (flexible)
        intervalMs: 120,     // Intervalo entre capturas en ms
        descriptorLength: 128, // Longitud del descriptor facial (fijo)
        minFaceSize: 120,    // Tamaño mínimo de rostro (más estricto que terminal)
    },

    // Configuración de video/cámara
    VIDEO: {
        facingMode: "user",
        width: { ideal: 640 },
        height: { ideal: 480 },
        frameRate: { ideal: 30, max: 30 },
    },

    // Configuración de rendimiento
    PERFORMANCE: {
        drawLoopInterval: 100, // ms entre frames de dibujo
        maxRetries: 3,         // Reintentos máximos
        timeoutMs: 10000,      // Timeout para operaciones
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

// =============================================================================
// CLASE PRINCIPAL
// =============================================================================

/**
 * Clase principal para el manejo de captura facial.
 *
 * @class FaceCaptureApp
 * @description Gestiona todo el proceso de captura de descriptores faciales:
 *              inicialización de cámara, detección en tiempo real, captura
 *              de múltiples muestras, promediado de descriptores y guardado.
 */
export class FaceCaptureApp {
    // =========================================================================
    // CONSTRUCTOR E INICIALIZACIÓN
    // =========================================================================

    constructor() {
        this.initializeElements();
        this.initializeState();
        this.setupEventListeners();
        this.setupFaceApiOptions();
    }

    /**
     * Inicializa las referencias a elementos del DOM necesarios.
     */
    initializeElements() {
        // Elementos de pantalla de carga
        this.loadingOverlay = document.getElementById("loadingOverlay");
        this.loadingMessage = document.getElementById("loadingMessage");
        this.loadingProgressBar = document.getElementById("loadingProgressBar");
        this.loadingProgressText = document.getElementById("loadingProgressText");

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
     * Valida que los elementos críticos del DOM existan.
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
     * Inicializa el estado interno de la aplicación.
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
     * Configura todos los event listeners de la aplicación.
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
     * Configura las opciones de face-api.js para detección.
     */
    setupFaceApiOptions() {
        if (typeof faceapi !== "undefined") {
            this.tinyOptions = new faceapi.TinyFaceDetectorOptions({
                inputSize: CONFIG.DETECTION.inputSize,
                scoreThreshold: CONFIG.DETECTION.scoreThreshold,
            });
        }
    }

    // =========================================================================
    // HANDLERS DE EVENTOS PRINCIPALES
    // =========================================================================

    async handleStartCamera() {
        try {
            const isRestarting = this.currentDescriptor !== null;
            const buttonText = isRestarting ? "Reiniciando..." : "Iniciando...";

            this.setButtonState(this.btnStart, false, buttonText);
            this.updateStatus(CONFIG.MESSAGES.CAMERA_STARTING);

            if (!this.modelsLoaded) {
                await this.loadModels();
            }

            await this.startCamera();

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

    async handleCaptureDescriptor() {
        if (this.isCapturing) return;

        try {
            this.isCapturing = true;
            this.setButtonState(this.btnCapture, false, "Capturando...");
            this.updateStatus(CONFIG.MESSAGES.CAPTURING);

            const descriptor = await this.captureDescriptor();
            this.currentDescriptor = descriptor;
            this.hiddenDescriptor.value = JSON.stringify(descriptor);

            await this.stopCamera();

            this.setButtonState(this.btnSave, true);
            this.setButtonState(this.btnStart, true, "Reiniciar Cámara");
            this.setButtonState(this.btnCapture, false, "Capturar Descriptor");

            this.updateDescriptorState("Capturado ✓");
            this.updateStatus(
                "Descriptor capturado exitosamente. Cámara detenida para ahorrar recursos."
            );
        } catch (error) {
            this.handleError("Error en captura", error);
        } finally {
            this.isCapturing = false;
        }
    }

    async handleSaveDescriptor(event) {
        if (event) {
            event.preventDefault();
        }

        if (!this.currentDescriptor) {
            this.updateStatus("No hay descriptor para guardar");
            return;
        }

        try {
            this.setButtonState(this.btnSave, false, "Guardando...");
            this.setButtonState(this.btnStart, false);
            this.updateStatus(CONFIG.MESSAGES.SAVING);

            await this.saveDescriptorAjax();

            console.log("Guardado exitoso, mostrando modal...");
            this.showSuccessModal();
        } catch (error) {
            this.handleError("Error al guardar", error);
            this.setButtonState(this.btnSave, true, "Guardar");
        }
    }

    async handleRecapture() {
        try {
            this.currentDescriptor = null;
            this.hiddenDescriptor.value = "";

            if (!this.isDetecting) {
                await this.handleStartCamera();
            }

            this.setButtonState(this.btnSave, false, "Guardar");
            this.setButtonState(this.btnCapture, true, "Capturar Descriptor");
            this.updateDescriptorState("No capturado");
            this.updateStatus("Listo para nueva captura");
        } catch (error) {
            this.handleError("Error al reiniciar captura", error);
        }
    }

    // =========================================================================
    // MÉTODOS DE COMUNICACIÓN CON SERVIDOR
    // =========================================================================

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

    // =========================================================================
    // MÉTODOS DE PANTALLA DE CARGA
    // =========================================================================

    updateLoadingProgress(percentage, message) {
        if (this.loadingProgressBar) {
            this.loadingProgressBar.style.width = `${percentage}%`;
        }
        if (this.loadingProgressText) {
            this.loadingProgressText.textContent = `${percentage}%`;
        }
        if (this.loadingMessage && message) {
            this.loadingMessage.textContent = message;
        }
    }

    hideLoadingScreen() {
        if (this.loadingOverlay) {
            this.loadingOverlay.classList.add("hidden");
        }
    }

    async initializeSystem() {
        try {
            this.updateLoadingProgress(10, "Verificando compatibilidad del navegador...");
            await this.sleep(200);

            if (typeof faceapi === "undefined") {
                throw new Error("La biblioteca face-api.js no está disponible");
            }

            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                throw new Error("Tu navegador no soporta acceso a la cámara");
            }

            this.updateLoadingProgress(30, "Navegador compatible ✓");
            await this.sleep(200);

            this.updateLoadingProgress(40, "Cargando modelos de reconocimiento facial...");

            if (!this.modelsLoaded) {
                await Promise.all([
                    faceapi.nets.tinyFaceDetector.loadFromUri(CONFIG.MODELS_URI),
                    faceapi.nets.faceLandmark68Net.loadFromUri(CONFIG.MODELS_URI),
                    faceapi.nets.faceRecognitionNet.loadFromUri(CONFIG.MODELS_URI),
                ]);
                this.modelsLoaded = true;
            }

            this.updateLoadingProgress(80, "Modelos cargados correctamente ✓");
            await this.sleep(300);

            this.updateLoadingProgress(100, "Sistema listo ✓");
            await this.sleep(500);

            console.log("Sistema inicializado correctamente");
            this.hideLoadingScreen();
            this.updateStatus("Sistema listo. Presiona 'Iniciar Cámara' para comenzar.");

        } catch (error) {
            console.error("Error en la inicialización:", error);

            if (this.loadingMessage) {
                this.loadingMessage.textContent = `Error: ${error.message}`;
                this.loadingMessage.style.color = "#ef4444";
            }

            await this.sleep(3000);
            this.hideLoadingScreen();
            this.updateStatus(`Error: ${error.message}. Por favor, recarga la página.`);
        }
    }

    // =========================================================================
    // MÉTODOS DE CARGA DE MODELOS Y CÁMARA
    // =========================================================================

    async loadModels() {
        if (this.modelsLoaded) {
            console.log("Modelos ya están cargados");
            return;
        }
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

    async startCamera() {
        if (!navigator.mediaDevices?.getUserMedia) {
            throw new Error("Tu navegador no soporta acceso a la cámara");
        }

        try {
            await this.stopCamera();

            this.stream = await navigator.mediaDevices.getUserMedia({
                video: CONFIG.VIDEO,
                audio: false,
            });

            this.video.srcObject = this.stream;

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

    setupCanvas() {
        this.overlay.width = this.video.videoWidth;
        this.overlay.height = this.video.videoHeight;

        this.ctx.imageSmoothingEnabled = true;
        this.ctx.imageSmoothingQuality = "high";
    }

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

    // =========================================================================
    // MÉTODOS DE DETECCIÓN FACIAL
    // =========================================================================

    startDetection() {
        this.isDetecting = true;
        this.detectLoop();
    }

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

        this.detectionAnimationId = setTimeout(() => {
            requestAnimationFrame(() => this.detectLoop());
        }, CONFIG.PERFORMANCE.drawLoopInterval);
    }

    clearCanvas() {
        this.ctx.clearRect(0, 0, this.overlay.width, this.overlay.height);
    }

    drawDetection(detection) {
        const displaySize = {
            width: this.video.videoWidth,
            height: this.video.videoHeight,
        };

        const resizedDetection = faceapi.resizeResults(detection, displaySize);

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

    async captureDescriptor(
        samples = CONFIG.CAPTURE.samples,
        intervalMs = CONFIG.CAPTURE.intervalMs
    ) {
        const descriptors = [];
        let attempts = 0;
        const maxAttempts = samples * 3;
        const minFaceSize = CONFIG.CAPTURE.minFaceSize || 120;
        const minRequired = CONFIG.CAPTURE.minSamples || Math.ceil(samples * 0.7);

        while (descriptors.length < samples && attempts < maxAttempts) {
            attempts++;

            try {
                const detection = await faceapi
                    .detectSingleFace(this.video, this.tinyOptions)
                    .withFaceLandmarks()
                    .withFaceDescriptor();

                if (detection?.descriptor) {
                    const box = detection.detection.box;
                    if (box.width >= minFaceSize && box.height >= minFaceSize) {
                        descriptors.push(detection.descriptor);
                        this.updateStatus(
                            `Capturando (${descriptors.length}/${samples})... mantén la pose`
                        );
                    } else {
                        console.warn(`Rostro muy pequeño: ${Math.round(box.width)}x${Math.round(box.height)}px (mínimo ${minFaceSize}px)`);
                        this.updateStatus(
                            `Acerca el rostro a la cámara (${descriptors.length}/${samples})`
                        );
                    }
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

        if (descriptors.length < minRequired) {
            throw new Error(
                `Solo se capturaron ${descriptors.length} de ${samples} muestras (mínimo ${minRequired}). Acerque el rostro a la cámara.`
            );
        }

        if (descriptors.length < samples) {
            console.info(`Capturadas ${descriptors.length} de ${samples} muestras (mínimo ${minRequired} cumplido)`);
        }

        return this.averageDescriptors(descriptors);
    }

    averageDescriptors(descriptors) {
        const length = CONFIG.CAPTURE.descriptorLength;
        const averaged = new Float32Array(length).fill(0);

        for (const descriptor of descriptors) {
            for (let i = 0; i < length; i++) {
                averaged[i] += descriptor[i];
            }
        }

        for (let i = 0; i < length; i++) {
            averaged[i] /= descriptors.length;
        }

        return Array.from(averaged);
    }

    // =========================================================================
    // MÉTODOS DE UI - MODALES
    // =========================================================================

    showSuccessModal() {
        console.log("Intentando mostrar el modal de éxito...");
        if (this.modal) {
            console.log("Modal encontrado, configurando estilos...");
            this.modal.style.display = "flex";
            this.modal.style.opacity = "1";
            this.modal.style.visibility = "visible";

            setTimeout(() => {
                console.log("Enfocando el botón de cerrar...");
                this.closeModalBtn?.focus();
            }, 100);
        } else {
            console.error("Modal de éxito no encontrado");
        }
    }

    hideModal() {
        if (this.modal) {
            this.modal.style.display = "none";
            this.modal.style.opacity = "0";
            this.modal.style.visibility = "hidden";

            window.location.href = "/employees";
        }
    }

    // =========================================================================
    // HANDLERS DE EVENTOS SECUNDARIOS
    // =========================================================================

    handleCloseModal() {
        this.hideModal();
        this.resetCaptureState();
        this.stopCamera();
    }

    handleModalBackdropClick(event) {
        if (event.target === this.modal) {
            this.hideModal();
        }
    }

    handleFormSubmit(event) {
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

    // =========================================================================
    // MÉTODOS UTILITARIOS
    // =========================================================================

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
            const emojiSpan = button.querySelector('span[aria-hidden="true"]');

            if (emojiSpan) {
                const emoji = emojiSpan.textContent || emojiSpan.innerText;
                button.innerHTML = `<span aria-hidden="true">${emoji}</span> ${text}`;
            } else {
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

        if (context.includes("modelos") || context.includes("cámara")) {
            alert(message);
        }
    }

    sleep(ms) {
        return new Promise((resolve) => setTimeout(resolve, ms));
    }
}
