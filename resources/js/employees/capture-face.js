/**
 * =============================================================================
 * CAPTURA FACIAL PARA EMPLEADOS - ENROLAMIENTO
 * =============================================================================
 *
 * @fileoverview Sistema de captura y registro de descriptores faciales para empleados.
 *               Este módulo permite capturar el rostro de un empleado y generar
 *               un descriptor facial de 128 dimensiones que se almacena en la
 *               base de datos para posterior identificación.
 *
 * @description Flujo de uso:
 *              1. Administrador inicia la cámara
 *              2. Empleado se posiciona frente a la cámara
 *              3. Sistema detecta el rostro en tiempo real
 *              4. Administrador presiona "Capturar" para tomar múltiples muestras
 *              5. Sistema promedia las muestras para generar descriptor estable
 *              6. Administrador guarda el descriptor en el servidor
 *
 * @requires face-api.js - Biblioteca de reconocimiento facial
 * @requires Los modelos deben estar en /models (tinyFaceDetector, faceLandmark68, faceRecognition)
 *
 * @author Sistema RRHH
 * @version 2.0.0
 */

// =============================================================================
// DEFINICIONES DE TIPOS (JSDoc TypeDef)
// =============================================================================

/**
 * Configuración de detección facial
 * @typedef {Object} DetectionConfig
 * @property {number} inputSize - Tamaño de entrada para el detector (recomendado: 416)
 * @property {number} scoreThreshold - Umbral de confianza para aceptar detección (0-1)
 * @property {number} maxFaces - Máximo de rostros a detectar simultáneamente
 */

/**
 * Configuración de captura de descriptor
 * @typedef {Object} CaptureConfig
 * @property {number} samples - Número total de muestras a intentar capturar
 * @property {number} minSamples - Mínimo de muestras válidas requeridas para éxito
 * @property {number} intervalMs - Intervalo en milisegundos entre capturas
 * @property {number} descriptorLength - Longitud del vector descriptor (siempre 128)
 * @property {number} minFaceSize - Tamaño mínimo del rostro en píxeles para aceptar
 */

/**
 * Configuración de video/cámara
 * @typedef {Object} VideoConfig
 * @property {string} facingMode - Modo de cámara ("user" para frontal)
 * @property {Object} width - Configuración de ancho {ideal: number}
 * @property {Object} height - Configuración de alto {ideal: number}
 * @property {Object} frameRate - Configuración de FPS {ideal: number, max: number}
 */

/**
 * Configuración de rendimiento
 * @typedef {Object} PerformanceConfig
 * @property {number} drawLoopInterval - Intervalo del bucle de dibujo en ms
 * @property {number} maxRetries - Máximo de reintentos en operaciones
 * @property {number} timeoutMs - Timeout general para operaciones asíncronas
 */

/**
 * Mensajes de la aplicación
 * @typedef {Object} MessagesConfig
 * @property {string} LOADING_MODELS - Mensaje de carga de modelos
 * @property {string} MODELS_LOADED - Mensaje de modelos cargados
 * @property {string} MODELS_ERROR - Mensaje de error en modelos
 * @property {string} CAMERA_STARTING - Mensaje de inicio de cámara
 * @property {string} CAMERA_READY - Mensaje de cámara lista
 * @property {string} CAMERA_ERROR - Mensaje de error de cámara
 * @property {string} CAMERA_STOPPED - Mensaje de cámara detenida
 * @property {string} FACE_DETECTED - Mensaje de rostro detectado
 * @property {string} NO_FACE - Mensaje de no se detecta rostro
 * @property {string} CAPTURING - Mensaje durante captura
 * @property {string} CAPTURE_SUCCESS - Mensaje de captura exitosa
 * @property {string} CAPTURE_ERROR - Mensaje de error en captura
 * @property {string} SAVING - Mensaje durante guardado
 * @property {string} SAVE_SUCCESS - Mensaje de guardado exitoso
 * @property {string} SAVE_ERROR - Mensaje de error al guardar
 */

/**
 * Configuración global de la aplicación
 * @type {Object}
 * @property {string} MODELS_URI - Ruta a los modelos de face-api.js
 * @property {DetectionConfig} DETECTION - Configuración de detección
 * @property {CaptureConfig} CAPTURE - Configuración de captura
 * @property {VideoConfig} VIDEO - Configuración de video
 * @property {PerformanceConfig} PERFORMANCE - Configuración de rendimiento
 * @property {MessagesConfig} MESSAGES - Mensajes de la aplicación
 */
const CONFIG = {
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
 * Clase principal para el manejo de captura facial de empleados.
 *
 * @class FaceCaptureApp
 * @description Gestiona todo el proceso de captura de descriptores faciales:
 *              inicialización de cámara, detección en tiempo real, captura
 *              de múltiples muestras, promediado de descriptores y guardado.
 *
 * @example
 * // Inicialización automática al cargar el DOM
 * document.addEventListener("DOMContentLoaded", () => {
 *     window.faceCaptureApp = new FaceCaptureApp();
 * });
 *
 * @example
 * // Uso programático
 * const app = new FaceCaptureApp();
 * await app.handleStartCamera();
 * await app.handleCaptureDescriptor();
 * await app.handleSaveDescriptor();
 */
class FaceCaptureApp {
    // =========================================================================
    // CONSTRUCTOR E INICIALIZACIÓN
    // =========================================================================

    /**
     * Crea una nueva instancia de FaceCaptureApp.
     * Inicializa elementos del DOM, estado de la aplicación, event listeners
     * y opciones de face-api.js.
     *
     * @constructor
     * @throws {Error} Si faltan elementos críticos del DOM
     */
    constructor() {
        this.initializeElements();
        this.initializeState();
        this.setupEventListeners();
        this.setupFaceApiOptions();
    }

    /**
     * Inicializa las referencias a elementos del DOM necesarios.
     *
     * @method initializeElements
     * @description Obtiene referencias a video, canvas, botones, elementos de estado,
     *              modal y formulario. Valida que los elementos críticos existan.
     * @throws {Error} Si faltan elementos críticos (video, overlay, ctx, status)
     * @returns {void}
     */
    initializeElements() {
        // Elementos de pantalla de carga
        /** @type {HTMLElement|null} */
        this.loadingOverlay = document.getElementById("loadingOverlay");
        /** @type {HTMLElement|null} */
        this.loadingMessage = document.getElementById("loadingMessage");
        /** @type {HTMLElement|null} */
        this.loadingProgressBar = document.getElementById("loadingProgressBar");
        /** @type {HTMLElement|null} */
        this.loadingProgressText = document.getElementById("loadingProgressText");

        // Elementos de video y canvas
        /** @type {HTMLVideoElement} */
        this.video = document.getElementById("video");
        /** @type {HTMLCanvasElement} */
        this.overlay = document.getElementById("overlay");
        /** @type {CanvasRenderingContext2D|null} */
        this.ctx = this.overlay?.getContext("2d");

        // Botones de control
        /** @type {HTMLButtonElement|null} */
        this.btnStart = document.getElementById("btnStart");
        /** @type {HTMLButtonElement|null} */
        this.btnCapture = document.getElementById("btnCapture");
        /** @type {HTMLButtonElement|null} */
        this.btnSave = document.getElementById("btnSave");
        /** @type {HTMLButtonElement|null} */
        this.btnCancel = document.getElementById("btnCancel");

        // Elementos de estado
        /** @type {HTMLElement|null} */
        this.statusEl = document.getElementById("status");
        /** @type {HTMLElement|null} */
        this.descStateEl =
            document.getElementById("descState") ||
            document.getElementById("descValue");
        /** @type {HTMLInputElement|null} */
        this.hiddenDescriptor = document.getElementById("faceDescriptor");

        // Modal
        /** @type {HTMLElement|null} */
        this.modal = document.getElementById("confirmationModal");
        /** @type {HTMLButtonElement|null} */
        this.closeModalBtn = document.getElementById("closeModal");

        // Formulario
        /** @type {HTMLFormElement|null} */
        this.saveForm = document.getElementById("saveForm");

        // Validar elementos críticos
        this.validateCriticalElements();
    }

    /**
     * Valida que los elementos críticos del DOM existan.
     *
     * @method validateCriticalElements
     * @description Verifica video, overlay, canvas context y status element.
     *              Lanza error si alguno falta para evitar errores posteriores.
     * @throws {Error} Lista de elementos faltantes
     * @returns {void}
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
     *
     * @method initializeState
     * @description Establece valores iniciales para stream, flags de detección/captura,
     *              descriptor actual, ID de animación y contador de reintentos.
     * @returns {void}
     */
    initializeState() {
        /** @type {MediaStream|null} Stream de la cámara */
        this.stream = null;
        /** @type {boolean} Flag de detección activa */
        this.isDetecting = false;
        /** @type {boolean} Flag de captura en progreso */
        this.isCapturing = false;
        /** @type {boolean} Flag de modelos cargados */
        this.modelsLoaded = false;
        /** @type {number[]|null} Descriptor facial actual (128 valores) */
        this.currentDescriptor = null;
        /** @type {number|null} ID del timeout de animación de detección */
        this.detectionAnimationId = null;
        /** @type {number} Contador de reintentos */
        this.retryCount = 0;
    }

    /**
     * Configura todos los event listeners de la aplicación.
     *
     * @method setupEventListeners
     * @description Conecta eventos de botones, modal, formulario, ventana,
     *              teclado y video con sus handlers correspondientes.
     * @returns {void}
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
     *
     * @method setupFaceApiOptions
     * @description Crea instancia de TinyFaceDetectorOptions con los parámetros
     *              de CONFIG.DETECTION. Requiere que faceapi esté disponible.
     * @returns {void}
     */
    setupFaceApiOptions() {
        if (typeof faceapi !== "undefined") {
            /** @type {faceapi.TinyFaceDetectorOptions} */
            this.tinyOptions = new faceapi.TinyFaceDetectorOptions({
                inputSize: CONFIG.DETECTION.inputSize,
                scoreThreshold: CONFIG.DETECTION.scoreThreshold,
            });
        }
    }

    // =========================================================================
    // HANDLERS DE EVENTOS PRINCIPALES
    // =========================================================================

    /**
     * Maneja el evento de inicio/reinicio de cámara.
     *
     * @method handleStartCamera
     * @async
     * @description Carga modelos si no están cargados, inicia la cámara,
     *              y actualiza la UI según si es inicio nuevo o reinicio.
     * @returns {Promise<void>}
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
     * Maneja el evento de captura de descriptor facial.
     *
     * @method handleCaptureDescriptor
     * @async
     * @description Captura múltiples muestras del rostro, las promedia,
     *              guarda el resultado y detiene la cámara para ahorrar recursos.
     * @returns {Promise<void>}
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

            // Detener cámara después de captura exitosa
            await this.stopCamera();

            // Actualizar UI para estado post-captura
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

    /**
     * Maneja el evento de guardado del descriptor en el servidor.
     *
     * @method handleSaveDescriptor
     * @async
     * @param {Event} [event] - Evento del formulario (opcional)
     * @description Valida que exista descriptor, lo envía al servidor via AJAX,
     *              y muestra modal de éxito o error según resultado.
     * @returns {Promise<void>}
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

    /**
     * Maneja el proceso de recaptura de descriptor.
     *
     * @method handleRecapture
     * @async
     * @description Limpia el descriptor actual, reinicia la cámara si es necesario,
     *              y actualiza la UI para permitir nueva captura.
     * @returns {Promise<void>}
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

    // =========================================================================
    // MÉTODOS DE COMUNICACIÓN CON SERVIDOR
    // =========================================================================

    /**
     * Guarda el descriptor facial en el servidor mediante AJAX.
     *
     * @method saveDescriptorAjax
     * @async
     * @description Envía el descriptor como JSON al endpoint del formulario.
     *              Incluye token CSRF y headers necesarios.
     * @throws {Error} Si el servidor responde con error o la respuesta indica fallo
     * @returns {Promise<Object>} Respuesta del servidor con {success: boolean, message?: string}
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

    // =========================================================================
    // MÉTODOS DE CÁMARA Y MODELOS
    // =========================================================================
    // MÉTODOS DE PANTALLA DE CARGA
    // =========================================================================

    /**
     * Actualiza el progreso de la pantalla de carga
     * @method updateLoadingProgress
     * @param {number} percentage - Porcentaje de progreso (0-100)
     * @param {string} message - Mensaje a mostrar
     * @returns {void}
     */
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

    /**
     * Oculta la pantalla de carga
     * @method hideLoadingScreen
     * @returns {void}
     */
    hideLoadingScreen() {
        if (this.loadingOverlay) {
            this.loadingOverlay.classList.add("hidden");
        }
    }

    /**
     * Inicializa el sistema cargando los modelos
     * @method initializeSystem
     * @async
     * @returns {Promise<void>}
     */
    async initializeSystem() {
        try {
            // Paso 1: Verificar compatibilidad
            this.updateLoadingProgress(10, "Verificando compatibilidad del navegador...");
            await this.sleep(200);

            // Verificar que face-api esté disponible
            if (typeof faceapi === "undefined") {
                throw new Error("La biblioteca face-api.js no está disponible");
            }

            // Verificar soporte de getUserMedia
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                throw new Error("Tu navegador no soporta acceso a la cámara");
            }

            this.updateLoadingProgress(30, "Navegador compatible ✓");
            await this.sleep(200);

            // Paso 2: Cargar modelos
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

            // Paso 3: Sistema listo
            this.updateLoadingProgress(100, "Sistema listo ✓");
            await this.sleep(500);

            // Ocultar pantalla de carga
            console.log("Sistema inicializado correctamente");
            this.hideLoadingScreen();
            this.updateStatus("Sistema listo. Presiona 'Iniciar Cámara' para comenzar.");

        } catch (error) {
            console.error("Error en la inicialización:", error);

            // Mostrar error en la pantalla de carga
            if (this.loadingMessage) {
                this.loadingMessage.textContent = `Error: ${error.message}`;
                this.loadingMessage.style.color = "#ef4444";
            }

            // Después de 3 segundos, ocultar y mostrar error
            await this.sleep(3000);
            this.hideLoadingScreen();
            this.updateStatus(`Error: ${error.message}. Por favor, recarga la página.`);
        }
    }

    // =========================================================================
    // MÉTODOS DE CARGA DE MODELOS Y CÁMARA
    // =========================================================================

    /**
     * Carga los modelos de face-api.js necesarios para el reconocimiento.
     *
     * @method loadModels
     * @async
     * @description Carga en paralelo los tres modelos requeridos si no están ya cargados.
     *              Los modelos normalmente se cargan en initializeSystem(), esta función
     *              es un fallback por si se necesita llamar manualmente.
     * @throws {Error} Si faceapi no está disponible o los modelos no cargan
     * @returns {Promise<void>}
     */
    async loadModels() {
        // Si ya están cargados, no hacer nada
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

    /**
     * Inicia la cámara del dispositivo.
     *
     * @method startCamera
     * @async
     * @description Solicita acceso a la cámara con la configuración especificada,
     *              configura el video y canvas, e inicia el bucle de detección.
     * @throws {Error} Si el navegador no soporta getUserMedia o hay error de acceso
     * @returns {Promise<void>}
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
     * Configura el canvas de overlay para coincidir con el video.
     *
     * @method setupCanvas
     * @description Ajusta dimensiones del canvas al video y configura
     *              opciones de calidad de renderizado.
     * @returns {void}
     */
    setupCanvas() {
        this.overlay.width = this.video.videoWidth;
        this.overlay.height = this.video.videoHeight;

        // Configurar contexto para mejor calidad
        this.ctx.imageSmoothingEnabled = true;
        this.ctx.imageSmoothingQuality = "high";
    }

    /**
     * Detiene la cámara y libera recursos.
     *
     * @method stopCamera
     * @async
     * @description Detiene todas las pistas del stream, limpia el canvas,
     *              y actualiza el estado de la aplicación.
     * @returns {Promise<void>}
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

    // =========================================================================
    // MÉTODOS DE DETECCIÓN FACIAL
    // =========================================================================

    /**
     * Inicia el bucle de detección facial en tiempo real.
     *
     * @method startDetection
     * @description Activa el flag de detección e inicia el bucle.
     * @returns {void}
     */
    startDetection() {
        this.isDetecting = true;
        this.detectLoop();
    }

    /**
     * Bucle principal de detección facial en tiempo real.
     *
     * @method detectLoop
     * @async
     * @description Detecta rostros continuamente, dibuja las detecciones
     *              en el canvas, y actualiza el estado según si hay rostro o no.
     *              Se ejecuta cada CONFIG.PERFORMANCE.drawLoopInterval ms.
     * @returns {Promise<void>}
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
     * Limpia el canvas de overlay.
     *
     * @method clearCanvas
     * @description Borra todo el contenido del canvas.
     * @returns {void}
     */
    clearCanvas() {
        this.ctx.clearRect(0, 0, this.overlay.width, this.overlay.height);
    }

    /**
     * Dibuja la detección facial en el canvas.
     *
     * @method drawDetection
     * @param {Object} detection - Resultado de detección de face-api.js
     * @description Redimensiona la detección al tamaño del video y dibuja
     *              el recuadro de detección y los puntos de referencia faciales.
     * @returns {void}
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
     * Captura múltiples muestras del descriptor facial y las promedia.
     *
     * @method captureDescriptor
     * @async
     * @param {number} [samples=CONFIG.CAPTURE.samples] - Número de muestras a intentar
     * @param {number} [intervalMs=CONFIG.CAPTURE.intervalMs] - Intervalo entre capturas
     * @description Captura múltiples muestras del rostro, valida tamaño mínimo,
     *              y promedia los descriptores para un resultado más estable.
     *              Requiere mínimo CONFIG.CAPTURE.minSamples muestras válidas.
     * @throws {Error} Si no se capturan suficientes muestras válidas
     * @returns {Promise<number[]>} Array de 128 números representando el descriptor promediado
     */
    async captureDescriptor(
        samples = CONFIG.CAPTURE.samples,
        intervalMs = CONFIG.CAPTURE.intervalMs
    ) {
        const descriptors = [];
        let attempts = 0;
        const maxAttempts = samples * 3; // Permitir más intentos
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
                    // Validar tamaño mínimo del rostro detectado
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

        // Validar mínimo de muestras requeridas (flexible)
        if (descriptors.length < minRequired) {
            throw new Error(
                `Solo se capturaron ${descriptors.length} de ${samples} muestras (mínimo ${minRequired}). Acerque el rostro a la cámara.`
            );
        }

        // Informar si se capturaron menos del total pero suficientes
        if (descriptors.length < samples) {
            console.info(`Capturadas ${descriptors.length} de ${samples} muestras (mínimo ${minRequired} cumplido)`);
        }

        return this.averageDescriptors(descriptors);
    }

    /**
     * Calcula el promedio de múltiples descriptores faciales.
     *
     * @method averageDescriptors
     * @param {Float32Array[]} descriptors - Array de descriptores a promediar
     * @description Suma todos los valores de cada posición y divide por cantidad
     *              para obtener un descriptor promedio más estable.
     * @returns {number[]} Descriptor promediado como array de 128 números
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

    // =========================================================================
    // MÉTODOS DE UI - MODALES
    // =========================================================================

    /**
     * Muestra el modal de éxito después de guardar.
     *
     * @method showSuccessModal
     * @description Hace visible el modal con animación y enfoca el botón
     *              de cerrar para accesibilidad.
     * @returns {void}
     */
    showSuccessModal() {
        console.log("Intentando mostrar el modal de éxito...");
        if (this.modal) {
            console.log("Modal encontrado, configurando estilos...");
            this.modal.style.display = "flex";
            this.modal.style.opacity = "1";
            this.modal.style.visibility = "visible";

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
     * Oculta el modal y redirige a la lista de empleados.
     *
     * @method hideModal
     * @description Oculta el modal con transición y navega a /employees.
     * @returns {void}
     */
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

    /**
     * Handler para cerrar el modal.
     *
     * @method handleCloseModal
     * @description Oculta el modal, resetea el estado de captura y detiene la cámara.
     * @returns {void}
     */
    handleCloseModal() {
        this.hideModal();
        this.resetCaptureState();
        this.stopCamera();
    }

    /**
     * Handler para clic en el backdrop del modal.
     *
     * @method handleModalBackdropClick
     * @param {MouseEvent} event - Evento del clic
     * @description Cierra el modal si se hace clic fuera del contenido.
     * @returns {void}
     */
    handleModalBackdropClick(event) {
        if (event.target === this.modal) {
            this.hideModal();
        }
    }

    /**
     * Handler para submit del formulario.
     *
     * @method handleFormSubmit
     * @param {Event} event - Evento de submit
     * @description Permite que el formulario se procese normalmente.
     * @returns {boolean} true para permitir submit
     */
    handleFormSubmit(event) {
        return true;
    }

    /**
     * Handler para evento beforeunload de la ventana.
     *
     * @method handleBeforeUnload
     * @async
     * @description Detiene la cámara al cerrar/recargar la página.
     * @returns {Promise<void>}
     */
    async handleBeforeUnload() {
        await this.stopCamera();
    }

    /**
     * Handler para evento resize de la ventana.
     *
     * @method handleResize
     * @description Reconfigura el canvas si el video está activo.
     * @returns {void}
     */
    handleResize() {
        if (this.video && this.overlay && this.video.videoWidth > 0) {
            this.setupCanvas();
        }
    }

    /**
     * Handler para eventos de teclado.
     *
     * @method handleKeydown
     * @param {KeyboardEvent} event - Evento de teclado
     * @description Cierra el modal con la tecla Escape.
     * @returns {void}
     */
    handleKeydown(event) {
        if (event.key === "Escape" && this.modal?.style.display !== "none") {
            event.preventDefault();
            this.hideModal();
        }
    }

    /**
     * Handler para evento loadedmetadata del video.
     *
     * @method handleVideoLoaded
     * @description Reconfigura el canvas cuando el video carga sus metadatos.
     * @returns {void}
     */
    handleVideoLoaded() {
        this.setupCanvas();
    }

    /**
     * Handler para errores del video.
     *
     * @method handleVideoError
     * @param {Event} event - Evento de error
     * @description Maneja errores de reproducción de video.
     * @returns {void}
     */
    handleVideoError(event) {
        this.handleError(
            "Error de video",
            new Error("Error al reproducir video")
        );
    }

    // =========================================================================
    // MÉTODOS UTILITARIOS
    // =========================================================================

    /**
     * Actualiza el mensaje de estado en la UI.
     *
     * @method updateStatus
     * @param {string} message - Mensaje a mostrar
     * @description Actualiza el elemento de estado y configura aria-live
     *              para lectores de pantalla.
     * @returns {void}
     */
    updateStatus(message) {
        if (this.statusEl) {
            this.statusEl.textContent = message;
            this.statusEl.setAttribute("aria-live", "polite");
            console.log(`Status: ${message}`);
        }
    }

    /**
     * Actualiza el indicador de estado del descriptor.
     *
     * @method updateDescriptorState
     * @param {string} state - Estado a mostrar ("Capturado ✓", "No capturado", etc.)
     * @description Actualiza el elemento que muestra si hay descriptor capturado.
     * @returns {void}
     */
    updateDescriptorState(state) {
        if (this.descStateEl) {
            if (this.descStateEl.tagName === "SPAN") {
                this.descStateEl.textContent = state;
            } else {
                this.descStateEl.innerHTML = `Descriptor: <span id="descValue">${state}</span>`;
            }
        }
    }

    /**
     * Establece el estado de un botón (habilitado/deshabilitado y texto).
     *
     * @method setButtonState
     * @param {HTMLButtonElement|null} button - Botón a modificar
     * @param {boolean} enabled - Si debe estar habilitado
     * @param {string|null} [text=null] - Texto opcional para el botón
     * @description Modifica disabled, aria-disabled y opcionalmente el texto,
     *              preservando emojis si existen.
     * @returns {void}
     */
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

    /**
     * Resetea el estado de captura a valores iniciales.
     *
     * @method resetCaptureState
     * @description Limpia descriptor, resetea botones y actualiza mensajes
     *              para permitir nueva captura desde cero.
     * @returns {void}
     */
    resetCaptureState() {
        this.currentDescriptor = null;
        this.hiddenDescriptor.value = "";
        this.setButtonState(this.btnSave, false, "Guardar");
        this.setButtonState(this.btnStart, true, "Iniciar Cámara");
        this.setButtonState(this.btnCapture, false, "Capturar Descriptor");
        this.updateDescriptorState("No capturado");
        this.updateStatus("Presiona 'Iniciar Cámara' para comenzar");
    }

    /**
     * Maneja errores de la aplicación.
     *
     * @method handleError
     * @param {string} context - Contexto donde ocurrió el error
     * @param {Error} error - Objeto de error
     * @description Registra el error en consola, actualiza el estado,
     *              y muestra alerta si es un error crítico.
     * @returns {void}
     */
    handleError(context, error) {
        const message = `${context}: ${error.message}`;
        console.error(message, error);
        this.updateStatus(message);

        // Mostrar error en UI si es crítico
        if (context.includes("modelos") || context.includes("cámara")) {
            alert(message);
        }
    }

    /**
     * Pausa la ejecución por un tiempo determinado.
     *
     * @method sleep
     * @param {number} ms - Milisegundos a esperar
     * @returns {Promise<void>} Promesa que se resuelve después del tiempo
     */
    sleep(ms) {
        return new Promise((resolve) => setTimeout(resolve, ms));
    }
}

// =============================================================================
// INICIALIZACIÓN DE LA APLICACIÓN
// =============================================================================

/**
 * Inicializa la aplicación cuando el DOM está listo.
 * Verifica que face-api.js esté disponible y crea la instancia de FaceCaptureApp.
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

        // Inicializar sistema y cargar modelos
        window.faceCaptureApp.initializeSystem();
    } catch (error) {
        console.error("Error al inicializar la aplicación:", error);
        document.getElementById(
            "status"
        ).textContent = `Error de inicialización: ${error.message}`;
    }
});

// =============================================================================
// EXPORTS PARA TESTING
// =============================================================================

/**
 * Exporta la clase y configuración para testing si se usa en entorno Node.js
 */
if (typeof module !== "undefined" && module.exports) {
    module.exports = { FaceCaptureApp, CONFIG };
}
