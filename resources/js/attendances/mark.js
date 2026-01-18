/**
 * =============================================================================
 * MARCACIÓN FACIAL - MODO MANUAL
 * =============================================================================
 *
 * @fileoverview Sistema de marcación de asistencia mediante reconocimiento facial.
 *               Este módulo permite a los empleados registrar su asistencia
 *               identificándose mediante su rostro y proporcionando su ubicación GPS.
 *
 * @description Flujo de uso:
 *              1. Usuario inicia la cámara
 *              2. Usuario presiona "Identificar" para capturar su rostro
 *              3. Sistema identifica al empleado y muestra eventos permitidos
 *              4. Usuario obtiene su ubicación GPS
 *              5. Usuario selecciona tipo de evento y registra la marcación
 *
 * @requires face-api.js - Biblioteca de reconocimiento facial
 * @requires Los modelos deben estar en /models (tinyFaceDetector, faceLandmark68, faceRecognition)
 *
 * @author Sistema RRHH
 * @version 2.0.0
 */

document.addEventListener("DOMContentLoaded", () => {
    // ==========================================================================
    // ELEMENTOS DEL DOM
    // ==========================================================================

    /** @type {HTMLElement} Overlay de pantalla de carga */
    const loadingOverlay = document.getElementById("loadingOverlay");

    /** @type {HTMLElement} Mensaje de la pantalla de carga */
    const loadingMessage = document.getElementById("loadingMessage");

    /** @type {HTMLElement} Barra de progreso */
    const loadingProgressBar = document.getElementById("loadingProgressBar");

    /** @type {HTMLElement} Texto de porcentaje de progreso */
    const loadingProgressText = document.getElementById("loadingProgressText");

    /** @type {HTMLVideoElement} Elemento de video para la cámara */
    const video = document.getElementById("video");

    /** @type {HTMLCanvasElement} Canvas para dibujar las detecciones faciales */
    const overlay = document.getElementById("overlay");

    /** @type {CanvasRenderingContext2D|null} Contexto 2D del canvas */
    const ctx = overlay?.getContext("2d");

    /** @type {HTMLButtonElement} Botón para iniciar la cámara */
    const btnStart = document.getElementById("btnStart");

    /** @type {HTMLButtonElement} Botón para identificar al empleado */
    const btnIdentify = document.getElementById("btnIdentify");

    /** @type {HTMLButtonElement} Botón para obtener geolocalización */
    const btnGeo = document.getElementById("btnGeo");

    /** @type {HTMLButtonElement} Botón para registrar la marcación */
    const btnMark = document.getElementById("btnMark");

    /** @type {HTMLSelectElement} Selector de tipo de evento */
    const eventTypeEl = document.getElementById("eventType");

    /** @type {HTMLElement} Tarjeta de información del empleado */
    const empCard = document.getElementById("empCard");

    /** @type {HTMLElement} Elemento para mostrar el nombre del empleado */
    const empName = document.getElementById("empName");

    /** @type {HTMLElement} Elemento para mostrar el documento del empleado */
    const empDoc = document.getElementById("empDoc");

    /** @type {HTMLElement} Elemento para mostrar información adicional */
    const empInfo = document.getElementById("empInfo");

    /** @type {HTMLInputElement} Campo oculto para latitud */
    const latEl = document.getElementById("lat");

    /** @type {HTMLInputElement} Campo oculto para longitud */
    const lngEl = document.getElementById("lng");

    // ==========================================================================
    // CONFIGURACIÓN Y CONSTANTES
    // ==========================================================================

    /**
     * Token CSRF para las peticiones POST
     * @type {string}
     */
    const csrfToken = document.querySelector("meta[name=csrf-token]");
    const CSRF = csrfToken ? csrfToken.content : "";

    if (!CSRF) {
        console.warn(
            "Token CSRF no encontrado. Esto puede causar errores en las peticiones POST."
        );
    }

    /**
     * URI donde se encuentran los modelos de face-api.js
     * @constant {string}
     */
    const MODELS_URI = "/models";

    /**
     * Tamaño mínimo de rostro en píxeles para aceptar una detección
     * @constant {number}
     */
    const MIN_FACE_SIZE = 100;

    /**
     * Textos descriptivos para cada tipo de evento de marcación
     * @constant {Object.<string, string>}
     */
    const EVENT_TEXTS = {
        check_in: "Ingreso",
        break_start: "Inicio descanso",
        break_end: "Fin descanso",
        check_out: "Salida",
    };

    // ==========================================================================
    // ESTADO GLOBAL
    // ==========================================================================

    /**
     * Stream de medios de la cámara
     * @type {MediaStream|null}
     */
    let stream = null;

    /**
     * Indica si los modelos de face-api.js están cargados
     * @type {boolean}
     */
    let modelsLoaded = false;

    /**
     * Indica si el bucle de dibujo está activo
     * @type {boolean}
     */
    let drawLoopActive = false;

    /**
     * Timestamp de la última detección para limitar frecuencia
     * @type {number}
     */
    let lastDetectionTime = 0;

    /**
     * Estado de la aplicación con datos del empleado y ubicación
     * @type {{employee: Object|null, allowed: string[], location: {lat: number, lng: number}|null}}
     */
    let state = {
        employee: null,
        allowed: [],
        location: null,
    };

    /**
     * Indica si ya se agregó el listener al modal de éxito
     * @type {boolean}
     */
    let modalListenerAdded = false;

    /**
     * Indica si ya se agregó el listener al modal de error
     * @type {boolean}
     */
    let errorModalListenerAdded = false;

    /**
     * Elemento que tenía el foco antes de abrir un modal
     * @type {HTMLElement|null}
     */
    let previousActiveElement = null;

    /**
     * Timeout actual de la alerta para poder cancelarlo
     * @type {number|null}
     */
    let currentAlertTimeout = null;

    // ==========================================================================
    // CONFIGURACIÓN FACE-API
    // ==========================================================================

    // Verificar que face-api.js está cargado
    if (typeof faceapi === "undefined") {
        console.error(
            "face-api.js no está cargado. Asegúrate de incluir la librería antes de este script."
        );
        return;
    }

    /**
     * Opciones de configuración para TinyFaceDetector
     * @description inputSize: 416 para mejor precisión (aumentado de 320)
     *              scoreThreshold: 0.6 para rechazar detecciones de baja calidad (aumentado de 0.5)
     * @type {faceapi.TinyFaceDetectorOptions}
     */
    const tinyOptions = new faceapi.TinyFaceDetectorOptions({
        inputSize: 416,      // Aumentado de 320 para mejor precisión
        scoreThreshold: 0.6, // Aumentado de 0.5 para rechazar detecciones de baja calidad
    });

    // ==========================================================================
    // FUNCIONES UTILITARIAS
    // ==========================================================================

    /**
     * Pausa la ejecución por un tiempo determinado
     * @param {number} ms - Milisegundos a esperar
     * @returns {Promise<void>} Promesa que se resuelve después del tiempo especificado
     */
    const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

    /**
     * Registra un mensaje en la consola con formato de estado
     * @param {string} message - Mensaje a registrar
     * @returns {void}
     */
    const logStatus = (message) => {
        console.log("[Status]", message);
    };

    // ==========================================================================
    // FUNCIONES DE VERIFICACIÓN
    // ==========================================================================

    /**
     * Verifica que todos los elementos del DOM necesarios existan
     * @description Comprueba cada elemento requerido y muestra error si falta alguno
     * @returns {boolean} true si todos los elementos existen, false en caso contrario
     */
    function verifyDOMElements() {
        const requiredElements = {
            video,
            overlay,
            btnStart,
            btnIdentify,
            btnGeo,
            btnMark,
            eventTypeEl,
            empCard,
            empName,
            empDoc,
            empInfo,
            latEl,
            lngEl,
        };

        const missingElements = [];
        for (const [name, element] of Object.entries(requiredElements)) {
            if (!element) {
                missingElements.push(name);
            }
        }

        if (missingElements.length > 0) {
            console.error("Elementos del DOM faltantes:", missingElements);
            const errorMsg = "Error: Faltan elementos requeridos en la página. Por favor, recarga.";
            logStatus(errorMsg);
            showErrorModal("Error de inicialización", errorMsg);
            return false;
        }
        return true;
    }

    // ==========================================================================
    // FUNCIONES DE PANTALLA DE CARGA
    // ==========================================================================

    /**
     * Actualiza el progreso de la pantalla de carga
     * @param {number} percentage - Porcentaje de progreso (0-100)
     * @param {string} message - Mensaje a mostrar
     * @returns {void}
     */
    function updateLoadingProgress(percentage, message) {
        if (loadingProgressBar) {
            loadingProgressBar.style.width = `${percentage}%`;
        }
        if (loadingProgressText) {
            loadingProgressText.textContent = `${percentage}%`;
        }
        if (loadingMessage && message) {
            loadingMessage.textContent = message;
        }
    }

    /**
     * Oculta la pantalla de carga
     * @returns {void}
     */
    function hideLoadingScreen() {
        if (loadingOverlay) {
            loadingOverlay.classList.add("hidden");
        }
    }

    /**
     * Inicializa el sistema cargando los modelos
     * @async
     * @returns {Promise<void>}
     */
    async function initializeSystem() {
        try {
            // Paso 1: Verificar compatibilidad
            updateLoadingProgress(10, "Verificando compatibilidad del navegador...");
            await sleep(200);

            // Verificar que face-api esté disponible
            if (typeof faceapi === "undefined") {
                throw new Error("La biblioteca face-api.js no está disponible");
            }

            // Verificar soporte de getUserMedia
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                throw new Error("Tu navegador no soporta acceso a la cámara");
            }

            updateLoadingProgress(30, "Navegador compatible ✓");
            await sleep(200);

            // Paso 2: Cargar modelos
            updateLoadingProgress(40, "Cargando modelos de reconocimiento facial...");

            if (!modelsLoaded) {
                await Promise.all([
                    faceapi.nets.tinyFaceDetector.loadFromUri(MODELS_URI),
                    faceapi.nets.faceLandmark68Net.loadFromUri(MODELS_URI),
                    faceapi.nets.faceRecognitionNet.loadFromUri(MODELS_URI),
                ]);
                modelsLoaded = true;
            }

            updateLoadingProgress(80, "Modelos cargados correctamente ✓");
            await sleep(300);

            // Paso 3: Sistema listo
            updateLoadingProgress(100, "Sistema listo ✓");
            await sleep(500);

            // Ocultar pantalla de carga
            console.log("Sistema inicializado correctamente");
            hideLoadingScreen();
            showAlert("success", "Sistema listo. Presiona 'Iniciar cámara' para comenzar.", 4000);

        } catch (error) {
            console.error("Error en la inicialización:", error);

            // Mostrar error en la pantalla de carga
            if (loadingMessage) {
                loadingMessage.textContent = `Error: ${error.message}`;
                loadingMessage.style.color = "#ef4444";
            }

            // Después de 3 segundos, ocultar y mostrar error
            await sleep(3000);
            hideLoadingScreen();
            showErrorModal(
                "Error al inicializar",
                `${error.message}. Por favor, recarga la página.`
            );
        }
    }

    // Verificar elementos al inicio
    if (!verifyDOMElements()) {
        return;
    }

    // Iniciar el sistema con pantalla de carga
    initializeSystem();

    // ==========================================================================
    // FUNCIONES DE CÁMARA Y MODELOS
    // ==========================================================================

    /**
     * Carga los modelos de reconocimiento facial de face-api.js
     * @async
     * @description Carga en paralelo los tres modelos necesarios si no están ya cargados.
     *              Los modelos normalmente se cargan en initializeSystem(), esta función
     *              es un fallback por si se necesita llamar manualmente.
     * @throws {Error} Si no se pueden cargar los modelos
     * @returns {Promise<void>}
     */
    async function loadModels() {
        // Si ya están cargados, no hacer nada
        if (modelsLoaded) {
            logStatus("Modelos ya están cargados");
            return;
        }

        try {
            logStatus("Cargando modelos de reconocimiento facial...");
            showAlert("info", "Cargando modelos de reconocimiento facial...", 0);

            await Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri(MODELS_URI),
                faceapi.nets.faceLandmark68Net.loadFromUri(MODELS_URI),
                faceapi.nets.faceRecognitionNet.loadFromUri(MODELS_URI),
            ]);

            modelsLoaded = true;
            logStatus("Modelos cargados correctamente");
            showAlert("success", "Modelos cargados. Presiona 'Iniciar cámara' para comenzar.", 4000);
        } catch (error) {
            modelsLoaded = false;
            const errorMsg = "Error al cargar los modelos de reconocimiento facial. Por favor, recarga la página.";
            logStatus(errorMsg);
            showErrorModal(
                "Error al cargar modelos",
                errorMsg + " Si el problema persiste, verifica tu conexión a internet."
            );
            console.error("Error al cargar los modelos:", error);
            throw error;
        }
    }

    /**
     * Inicia la cámara del dispositivo y configura el video
     * @async
     * @description Solicita acceso a la cámara frontal, configura el elemento video
     *              y ajusta el canvas al tamaño del video
     * @throws {Error} Si no se puede acceder a la cámara (permiso denegado, no encontrada, en uso)
     * @returns {Promise<void>}
     */
    async function startCamera() {
        try {
            // Verificar soporte de getUserMedia
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                logStatus("Tu navegador no soporta acceso a la cámara.");
                return;
            }

            // Solicitar acceso a la cámara
            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: "user" },
                audio: false,
            });

            // Configurar el video
            video.srcObject = stream;
            await new Promise((res) => (video.onloadedmetadata = res));

            // Ajustar el tamaño del lienzo
            overlay.width = video.videoWidth;
            overlay.height = video.videoHeight;

            // Verificar que los modelos estén cargados antes de iniciar el draw loop
            if (modelsLoaded) {
                drawLoopActive = true;
                requestAnimationFrame(drawLoop);
            } else {
                const errorMsg = "Los modelos no están cargados. Por favor, recarga la página.";
                logStatus(errorMsg);
                showErrorModal("Modelos no cargados", errorMsg);
                return;
            }

            // Habilitar el botón de identificación
            btnIdentify.disabled = false;
            btnIdentify.removeAttribute("aria-disabled");
            logStatus("Cámara iniciada correctamente");
            showAlert("success", "Cámara iniciada. Presiona 'Identificar' cuando estés listo.", 4000);
        } catch (e) {
            let errorTitle = "Error de cámara";
            let errorMsg = "";

            if (e.name === "NotAllowedError") {
                errorTitle = "Permiso denegado";
                errorMsg = "Por favor, habilita el acceso a la cámara en tu navegador y recarga la página.";
            } else if (e.name === "NotFoundError") {
                errorTitle = "Cámara no encontrada";
                errorMsg = "No se detectó ninguna cámara en tu dispositivo. Por favor, conecta una cámara y recarga la página.";
            } else if (e.name === "NotReadableError") {
                errorTitle = "Cámara en uso";
                errorMsg = "La cámara está siendo utilizada por otra aplicación. Cierra otras aplicaciones que puedan estar usando la cámara e intenta nuevamente.";
            } else {
                errorMsg = "No se pudo iniciar la cámara: " + e.message;
            }

            logStatus(errorMsg);
            showErrorModal(errorTitle, errorMsg);
            console.error("Error al iniciar la cámara:", e);
        }
    }

    // ==========================================================================
    // FUNCIONES DE DETECCIÓN FACIAL
    // ==========================================================================

    /**
     * Bucle de dibujo que detecta y dibuja rostros en tiempo real
     * @async
     * @description Se ejecuta continuamente mientras drawLoopActive sea true.
     *              Limita la frecuencia de detección a 200ms para optimizar rendimiento.
     *              Dibuja el recuadro de detección y los puntos de referencia faciales.
     * @returns {Promise<void>}
     */
    async function drawLoop() {
        if (!drawLoopActive) return;

        try {
            const now = performance.now();
            const DETECTION_INTERVAL = 200;

            // Limitar la frecuencia de detección
            if (now - lastDetectionTime > DETECTION_INTERVAL) {
                lastDetectionTime = now;

                // Verificar que el video esté listo y los modelos cargados
                if (video.readyState >= 2 && modelsLoaded) {
                    const detection = await faceapi
                        .detectSingleFace(video, tinyOptions)
                        .withFaceLandmarks();

                    // Limpiar el lienzo
                    ctx.clearRect(0, 0, overlay.width, overlay.height);

                    if (detection) {
                        // Ajustar dimensiones solo si es necesario
                        if (
                            overlay.width !== video.videoWidth ||
                            overlay.height !== video.videoHeight
                        ) {
                            faceapi.matchDimensions(overlay, video);
                        }

                        // Redimensionar resultados
                        const resizedDetections = faceapi.resizeResults(detection, {
                            width: video.videoWidth,
                            height: video.videoHeight,
                        });

                        // Dibujar detecciones y puntos clave
                        faceapi.draw.drawDetections(overlay, resizedDetections);
                        faceapi.draw.drawFaceLandmarks(overlay, resizedDetections);
                    }
                }
            }

            // Continuar el bucle solo si está activo
            if (drawLoopActive) {
                requestAnimationFrame(drawLoop);
            }
        } catch (error) {
            console.error("Error en drawLoop:", error);
            if (drawLoopActive) {
                requestAnimationFrame(drawLoop);
            }
        }
    }

    /**
     * Captura múltiples muestras del descriptor facial y las promedia
     * @async
     * @description Captura varias muestras del rostro detectado, valida que el rostro
     *              tenga un tamaño mínimo aceptable, y promedia los descriptores
     *              para obtener un resultado más estable y preciso.
     * @param {number} [samples=5] - Número de muestras a capturar
     * @param {number} [intervalMs=150] - Intervalo en milisegundos entre capturas
     * @throws {Error} Si los parámetros son inválidos
     * @throws {Error} Si los modelos no están cargados
     * @throws {Error} Si no se capturan suficientes muestras válidas
     * @returns {Promise<number[]>} Array de 128 números representando el descriptor facial promediado
     */
    async function captureDescriptor(samples = 5, intervalMs = 150) {
        // Validar parámetros
        if (!Number.isInteger(samples) || samples <= 0) {
            throw new Error("El número de muestras debe ser un entero positivo.");
        }
        if (intervalMs <= 0) {
            throw new Error("El intervalo debe ser un número positivo.");
        }

        // Verificar que los modelos estén cargados
        if (!modelsLoaded) {
            throw new Error("Los modelos de reconocimiento facial no están cargados.");
        }

        logStatus(`Capturando ${samples} muestras de rostro...`);
        showAlert("info", "Capturando rostro... mantén la cara estable", 0);

        const list = [];
        let attempts = 0;
        const maxAttempts = samples * 3;

        try {
            while (list.length < samples && attempts < maxAttempts) {
                logStatus(`Captura ${list.length + 1} de ${samples}`);

                const det = await faceapi
                    .detectSingleFace(video, tinyOptions)
                    .withFaceLandmarks()
                    .withFaceDescriptor();

                if (det?.descriptor) {
                    // Validar tamaño del rostro detectado
                    const box = det.detection.box;
                    if (box.width >= MIN_FACE_SIZE && box.height >= MIN_FACE_SIZE) {
                        list.push(det.descriptor);
                    } else {
                        console.warn(
                            `Rostro muy pequeño: ${Math.round(box.width)}x${Math.round(box.height)}px (mínimo ${MIN_FACE_SIZE}px)`
                        );
                        showAlert("warning", "Acerca el rostro a la cámara", 2000);
                    }
                } else {
                    console.warn("No se detectó un rostro en esta iteración.");
                }

                attempts++;
                await sleep(intervalMs);
            }

            // Verificar si se capturaron suficientes muestras (mínimo 3)
            const minRequired = Math.min(3, samples);
            if (list.length < minRequired) {
                throw new Error(
                    `Solo se capturaron ${list.length} muestras válidas (mínimo ${minRequired}). Acerque el rostro a la cámara.`
                );
            }

            // Calcular el promedio de los descriptores
            const avg = new Float32Array(128).fill(0);
            list.forEach((descriptor) => {
                descriptor.forEach((value, i) => {
                    avg[i] += value;
                });
            });
            for (let i = 0; i < 128; i++) {
                avg[i] /= list.length;
            }

            logStatus(`Captura completada con éxito (${list.length} muestras).`);
            return Array.from(avg);
        } catch (error) {
            console.error("Error en captureDescriptor:", error);
            logStatus("Error al capturar el descriptor: " + error.message);
            throw error;
        }
    }

    // ==========================================================================
    // FUNCIONES DE INTERFAZ DE USUARIO
    // ==========================================================================

    /**
     * Habilita el paso 2 del flujo con los eventos de marcación permitidos
     * @description Limpia el selector de eventos y lo llena con los eventos permitidos
     *              según el estado actual del empleado (check_in, break_start, etc.)
     * @param {string[]} allowed - Array de tipos de eventos permitidos
     * @returns {void}
     */
    function enableStep2(allowed) {
        try {
            if (!Array.isArray(allowed)) {
                console.error('El parámetro "allowed" debe ser un arreglo.');
                return;
            }

            eventTypeEl.innerHTML = "";

            if (allowed.length === 0) {
                addOption(eventTypeEl, "", "No hay eventos permitidos para hoy");
                disableElements([eventTypeEl, btnGeo, btnMark]);
                return;
            }

            addOption(eventTypeEl, "", "— selecciona el tipo —");

            allowed.forEach((event) => {
                const text = EVENT_TEXTS[event] || event;
                addOption(eventTypeEl, event, text);
            });

            enableElements([eventTypeEl, btnGeo]);
        } catch (error) {
            console.error("Error en enableStep2:", error);
        }
    }

    /**
     * Agrega una opción a un elemento select
     * @param {HTMLSelectElement} parent - Elemento select padre
     * @param {string} value - Valor de la opción
     * @param {string} text - Texto visible de la opción
     * @returns {void}
     */
    function addOption(parent, value, text) {
        const opt = document.createElement("option");
        opt.value = value;
        opt.textContent = text;
        parent.appendChild(opt);
    }

    /**
     * Deshabilita múltiples elementos del DOM
     * @param {HTMLElement[]} elements - Array de elementos a deshabilitar
     * @returns {void}
     */
    function disableElements(elements) {
        elements.forEach((el) => {
            if (el) {
                el.disabled = true;
                el.setAttribute("aria-disabled", "true");
            }
        });
    }

    /**
     * Habilita múltiples elementos del DOM
     * @param {HTMLElement[]} elements - Array de elementos a habilitar
     * @returns {void}
     */
    function enableElements(elements) {
        elements.forEach((el) => {
            if (el) {
                el.disabled = false;
                el.removeAttribute("aria-disabled");
            }
        });
    }

    /**
     * Verifica si se cumplen las condiciones para habilitar el botón de marcación
     * @description El botón se habilita solo si:
     *              - Hay un empleado identificado
     *              - Se ha seleccionado un tipo de evento
     *              - Se ha obtenido la ubicación GPS
     * @returns {void}
     */
    function checkEnableMark() {
        try {
            if (!state || !eventTypeEl || !latEl || !lngEl || !btnMark) {
                console.error("Elementos necesarios no están definidos.");
                return;
            }

            const isEmployeeSelected = !!(state.employee && state.employee.id);
            const isEventSelected = !!eventTypeEl.value;
            const isLocationSet = !!(latEl.value && lngEl.value);

            btnMark.disabled = !(isEmployeeSelected && isEventSelected && isLocationSet);
        } catch (error) {
            console.error("Error en checkEnableMark:", error);
        }
    }

    /**
     * Traduce los tipos de eventos de inglés a español
     * @param {string|null} eventType - Tipo de evento en inglés
     * @returns {string} Tipo de evento en español
     */
    function translateEventType(eventType) {
        const translations = {
            check_in: "Entrada",
            break_start: "Inicio de descanso",
            break_end: "Fin de descanso",
            check_out: "Salida",
        };

        return translations[eventType] || eventType || "—";
    }

    /**
     * Actualiza la interfaz de usuario con los datos del empleado identificado
     * @param {Object} employee - Datos del empleado
     * @param {string} [employee.first_name] - Nombre del empleado
     * @param {string} [employee.last_name] - Apellido del empleado
     * @param {string} [employee.ci] - Documento de identidad
     * @param {string|null} lastEvent - Último evento registrado del empleado
     * @returns {void}
     */
    function updateEmployeeUI(employee, lastEvent) {
        try {
            if (empCard) empCard.classList.remove("hidden");

            if (empName) {
                empName.textContent = `${employee.first_name || ""} ${employee.last_name || ""}`.trim();
            }

            if (empDoc) {
                empDoc.textContent = employee.ci ? `Doc: ${employee.ci}` : "Sin documento";
            }

            if (empInfo) {
                const translatedEvent = translateEventType(lastEvent);
                empInfo.textContent = `Última marcación: ${translatedEvent}`;
            }
        } catch (error) {
            console.error("Error al actualizar UI del empleado:", error);
        }
    }

    // ==========================================================================
    // FUNCIONES DE MODALES
    // ==========================================================================

    /**
     * Muestra el modal de éxito después de una marcación exitosa
     * @description Muestra un modal con animación, maneja accesibilidad (foco, aria),
     *              y configura eventos para cerrarlo (botón, backdrop, tecla ESC)
     * @returns {void}
     */
    function showSuccessModal() {
        const modal = document.getElementById("successModal");
        const closeModal = document.getElementById("closeModal");

        if (!modal || !closeModal) {
            console.error("Elementos del modal no encontrados");
            return;
        }

        previousActiveElement = document.activeElement;

        modal.classList.remove("hidden");
        void modal.offsetWidth; // Forzar reflow para animación

        requestAnimationFrame(() => {
            modal.classList.add("show");
        });

        setTimeout(() => {
            closeModal.focus();
            modal.setAttribute("aria-hidden", "false");
        }, 100);

        document.body.classList.add("modal-open");

        if (!modalListenerAdded) {
            closeModal.addEventListener("click", closeModalHandler);

            modal.addEventListener("click", (e) => {
                if (e.target === modal) {
                    closeModalHandler();
                }
            });

            document.addEventListener("keydown", (e) => {
                if (e.key === "Escape" && modal.classList.contains("show")) {
                    closeModalHandler();
                }
            });

            modalListenerAdded = true;
        }
    }

    /**
     * Cierra el modal de éxito con animación
     * @description Oculta el modal, restaura el foco al elemento anterior,
     *              y resetea el sistema para una nueva marcación
     * @returns {void}
     */
    function closeModalHandler() {
        const modal = document.getElementById("successModal");

        if (!modal) return;

        modal.setAttribute("aria-hidden", "true");
        modal.classList.remove("show");

        setTimeout(() => {
            modal.classList.add("hidden");
            document.body.classList.remove("modal-open");

            if (previousActiveElement && previousActiveElement.focus) {
                previousActiveElement.focus();
            }

            resetSystem();
        }, 250);
    }

    /**
     * Muestra el modal de error con un mensaje personalizado
     * @param {string} title - Título del error
     * @param {string} message - Descripción detallada del error
     * @returns {void}
     */
    function showErrorModal(title, message) {
        const modal = document.getElementById("errorModal");
        const closeBtn = document.getElementById("closeErrorModal");
        const titleEl = document.getElementById("errorModalTitle");
        const descEl = document.getElementById("errorModalDesc");

        if (!modal || !closeBtn || !titleEl || !descEl) {
            console.error("Elementos del modal de error no encontrados");
            return;
        }

        previousActiveElement = document.activeElement;

        titleEl.textContent = title || "Error";
        descEl.textContent = message || "Ha ocurrido un error inesperado.";

        modal.classList.remove("hidden");
        void modal.offsetWidth;

        requestAnimationFrame(() => {
            modal.classList.add("show");
        });

        setTimeout(() => {
            closeBtn.focus();
            modal.setAttribute("aria-hidden", "false");
        }, 100);

        document.body.classList.add("modal-open");

        if (!errorModalListenerAdded) {
            closeBtn.addEventListener("click", closeErrorModalHandler);

            modal.addEventListener("click", (e) => {
                if (e.target === modal) {
                    closeErrorModalHandler();
                }
            });

            document.addEventListener("keydown", (e) => {
                if (e.key === "Escape" && modal.classList.contains("show")) {
                    closeErrorModalHandler();
                }
            });

            errorModalListenerAdded = true;
        }
    }

    /**
     * Cierra el modal de error con animación
     * @returns {void}
     */
    function closeErrorModalHandler() {
        const modal = document.getElementById("errorModal");

        if (!modal) return;

        modal.setAttribute("aria-hidden", "true");
        modal.classList.remove("show");

        setTimeout(() => {
            modal.classList.add("hidden");
            document.body.classList.remove("modal-open");

            if (previousActiveElement && previousActiveElement.focus) {
                previousActiveElement.focus();
            }
        }, 250);
    }

    // ==========================================================================
    // FUNCIONES DE ALERTAS
    // ==========================================================================

    /**
     * Muestra una alerta inline en el contenedor de alertas
     * @param {('error'|'warning'|'info'|'success')} type - Tipo de alerta que determina el estilo
     * @param {string} message - Mensaje a mostrar
     * @param {number} [duration=5000] - Duración en ms antes de ocultar (0 = permanente)
     * @returns {void}
     */
    function showAlert(type, message, duration = 5000) {
        const container = document.getElementById("alertContainer");

        if (!container) {
            console.error("Contenedor de alertas no encontrado");
            return;
        }

        if (currentAlertTimeout) {
            clearTimeout(currentAlertTimeout);
        }

        const icons = {
            error: "⚠",
            warning: "⚠",
            info: "ℹ",
            success: "✓",
        };

        const alertBox = document.createElement("div");
        alertBox.className = `alert-box alert-box-${type}`;
        alertBox.setAttribute("role", "alert");

        alertBox.innerHTML = `
            <div class="alert-box-icon">${icons[type] || "ℹ"}</div>
            <div class="alert-box-content">
                <div class="alert-box-message">${message}</div>
            </div>
        `;

        container.innerHTML = "";
        container.appendChild(alertBox);

        if (duration > 0) {
            currentAlertTimeout = setTimeout(() => {
                alertBox.style.opacity = "0";
                alertBox.style.transform = "translateY(-10px)";
                setTimeout(() => {
                    if (alertBox.parentNode === container) {
                        container.removeChild(alertBox);
                    }
                }, 300);
            }, duration);
        }
    }

    /**
     * Limpia todas las alertas del contenedor
     * @returns {void}
     */
    function clearAlerts() {
        const container = document.getElementById("alertContainer");
        if (container) {
            container.innerHTML = "";
        }
        if (currentAlertTimeout) {
            clearTimeout(currentAlertTimeout);
            currentAlertTimeout = null;
        }
    }

    // ==========================================================================
    // FUNCIONES DE RESET
    // ==========================================================================

    /**
     * Resetea el sistema a su estado inicial
     * @description Limpia el estado, detiene la cámara, limpia el canvas,
     *              resetea los campos del formulario y los botones
     * @returns {void}
     */
    function resetSystem() {
        try {
            clearAlerts();

            // Limpiar estado global
            state.employee = null;
            state.allowed = [];
            state.location = null;

            // Detener stream de video
            if (stream) {
                stream.getTracks().forEach((track) => track.stop());
                stream = null;
            }

            drawLoopActive = false;

            if (video) {
                video.srcObject = null;
            }

            if (ctx) {
                ctx.clearRect(0, 0, overlay.width, overlay.height);
            }

            // Limpiar campos
            if (empInfo) empInfo.textContent = "";
            if (empName) empName.textContent = "";
            if (empDoc) empDoc.textContent = "";
            if (latEl) latEl.value = "";
            if (lngEl) lngEl.value = "";
            if (eventTypeEl) {
                eventTypeEl.innerHTML = '<option value="">— primero identificate —</option>';
            }
            if (empCard) empCard.classList.add("hidden");

            // Resetear botones
            if (btnStart) {
                btnStart.disabled = false;
                btnStart.removeAttribute("aria-disabled");
            }
            if (btnIdentify) {
                btnIdentify.disabled = true;
                btnIdentify.setAttribute("aria-disabled", "true");
            }
            if (btnGeo) {
                btnGeo.disabled = true;
                btnGeo.setAttribute("aria-disabled", "true");
            }
            if (btnMark) {
                btnMark.disabled = true;
                btnMark.setAttribute("aria-disabled", "true");
            }

            logStatus("Sistema reiniciado");
            showAlert("info", "Sistema reiniciado. Presiona 'Iniciar cámara' para comenzar.", 4000);
        } catch (error) {
            console.error("Error al resetear el sistema:", error);
        }
    }

    // ==========================================================================
    // EVENT LISTENERS
    // ==========================================================================

    // Evento: Iniciar cámara
    if (btnStart) {
        btnStart.addEventListener("click", async () => {
            try {
                btnStart.disabled = true;
                await loadModels();
                await startCamera();
            } catch (error) {
                console.error("Error al iniciar:", error);
                logStatus("Error al inicializar el sistema");
                btnStart.disabled = false;
            }
        });
    }

    // Evento: Identificar empleado
    if (btnIdentify) {
        btnIdentify.addEventListener("click", async () => {
            btnIdentify.disabled = true;

            try {
                logStatus("Iniciando captura de rostro...");
                const descriptor = await captureDescriptor(5, 150);

                logStatus("Enviando datos para identificación...");
                showAlert("info", "Identificando empleado...", 0);

                if (!CSRF) {
                    throw new Error("Token CSRF no disponible");
                }

                const resp = await fetch("/marcar/identificar", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        Accept: "application/json",
                        "X-CSRF-TOKEN": CSRF,
                    },
                    body: JSON.stringify({ face_descriptor: descriptor }),
                });

                const json = await resp.json();

                if (!json.ok) {
                    throw new Error(json.message || "No identificado");
                }

                state.employee = json.employee;
                state.allowed = json.allowed_events || [];

                updateEmployeeUI(json.employee, json.last_event);
                enableStep2(state.allowed);
                checkEnableMark();

                logStatus("Empleado identificado ✓");
                showAlert("success", "Empleado identificado correctamente", 3000);
            } catch (e) {
                console.error("Error en la identificación:", e);
                let errorMsg = e.message || "No se pudo identificar al empleado";
                logStatus("Error: " + errorMsg);
                showAlert("error", errorMsg, 7000);
            } finally {
                btnIdentify.disabled = false;
            }
        });
    }

    // Evento: Obtener geolocalización
    if (btnGeo) {
        btnGeo.addEventListener("click", () => {
            if (!navigator.geolocation) {
                const errorMsg = "Tu navegador no soporta geolocalización. Por favor, usa un navegador moderno.";
                logStatus(errorMsg);
                showErrorModal("Geolocalización no soportada", errorMsg);
                return;
            }

            btnGeo.disabled = true;
            logStatus("Solicitando ubicación GPS...");
            showAlert("info", "Obteniendo ubicación...", 0);

            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    if (latEl) latEl.value = pos.coords.latitude.toFixed(6);
                    if (lngEl) lngEl.value = pos.coords.longitude.toFixed(6);
                    state.location = {
                        lat: pos.coords.latitude,
                        lng: pos.coords.longitude,
                    };
                    checkEnableMark();
                    logStatus("Ubicación obtenida correctamente");
                    showAlert("success", "Ubicación obtenida correctamente", 3000);
                    btnGeo.disabled = false;
                },
                (err) => {
                    console.error("Error de geolocalización:", err);
                    let errorMsg = "";

                    switch (err.code) {
                        case 1:
                            errorMsg = "Permiso denegado. Por favor, habilita la ubicación en tu navegador.";
                            break;
                        case 2:
                            errorMsg = "No se pudo obtener la ubicación. Verifica que el GPS esté activado.";
                            break;
                        case 3:
                            errorMsg = "Tiempo de espera agotado. Por favor, intenta de nuevo.";
                            break;
                        default:
                            errorMsg = "Error desconocido al obtener ubicación. Por favor, intenta de nuevo.";
                            break;
                    }

                    logStatus(errorMsg);
                    showAlert("error", errorMsg, 6000);
                    btnGeo.disabled = false;
                },
                {
                    timeout: 10000,
                    maximumAge: 60000,
                    enableHighAccuracy: true,
                }
            );
        });
    }

    // Evento: Registrar marcación
    if (btnMark) {
        btnMark.addEventListener("click", async () => {
            btnMark.disabled = true;

            try {
                // Validar datos antes de enviar
                if (!state.employee || !state.employee.id) {
                    throw new Error("Empleado no identificado.");
                }
                if (!eventTypeEl || !eventTypeEl.value) {
                    throw new Error("Tipo de evento no seleccionado.");
                }
                if (!latEl || !lngEl || !latEl.value || !lngEl.value) {
                    throw new Error("Ubicación no válida.");
                }
                if (!CSRF) {
                    throw new Error("Token CSRF no disponible");
                }

                const payload = {
                    employee_id: state.employee.id,
                    event_type: eventTypeEl.value,
                    location: {
                        lat: parseFloat(latEl.value),
                        lng: parseFloat(lngEl.value),
                    },
                };

                logStatus("Enviando marcación al servidor...");
                showAlert("info", "Registrando marcación...", 0);

                const resp = await fetch("/marcar", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": CSRF,
                    },
                    body: JSON.stringify(payload),
                });

                const json = await resp.json();
                if (!resp.ok || !json.ok) {
                    throw new Error(json.message || "No se pudo registrar la marcación.");
                }

                logStatus(json.message || "Marcación registrada correctamente");
                clearAlerts();
                showSuccessModal();
            } catch (e) {
                console.error("Error en la marcación:", e);
                const errorMsg = e.message || "No se pudo registrar la marcación. Por favor, intenta de nuevo.";
                logStatus("Error: " + errorMsg);
                showErrorModal("Error al registrar marcación", errorMsg);
            } finally {
                btnMark.disabled = false;
            }
        });
    }

    // Evento: Cambio en selector de tipo de evento
    if (eventTypeEl) {
        eventTypeEl.addEventListener("change", checkEnableMark);
    }

    // Evento: Limpiar recursos al salir de la página
    window.addEventListener("beforeunload", () => {
        if (stream) {
            stream.getTracks().forEach((t) => t.stop());
        }
        drawLoopActive = false;
    });

    // ==========================================================================
    // INICIALIZACIÓN
    // ==========================================================================

    logStatus("Sistema de marcación facial inicializado");
});
