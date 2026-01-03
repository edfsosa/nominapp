document.addEventListener("DOMContentLoaded", () => {
    const video = document.getElementById("video");
    const overlay = document.getElementById("overlay");
    const ctx = overlay.getContext("2d");

    const btnStart = document.getElementById("btnStart");
    const btnIdentify = document.getElementById("btnIdentify");
    const btnGeo = document.getElementById("btnGeo");
    const btnMark = document.getElementById("btnMark");

    const eventTypeEl = document.getElementById("eventType");

    const empCard = document.getElementById("empCard");
    const empName = document.getElementById("empName");
    const empDoc = document.getElementById("empDoc");
    const empInfo = document.getElementById("empInfo");

    const latEl = document.getElementById("lat");
    const lngEl = document.getElementById("lng");

    // CORRECCIÓN 1: Verificar que el token CSRF existe antes de acceder a su contenido
    const csrfToken = document.querySelector("meta[name=csrf-token]");
    const CSRF = csrfToken ? csrfToken.content : "";

    if (!CSRF) {
        console.warn(
            "Token CSRF no encontrado. Esto puede causar errores en las peticiones POST."
        );
    }

    const MODELS_URI = "/models";

    // Variables globales
    let stream = null;
    let modelsLoaded = false;
    let drawLoopActive = false;
    let state = {
        employee: null,
        allowed: [],
        location: null,
    };

    // CORRECCIÓN 2: Verificar que faceapi está disponible
    if (typeof faceapi === "undefined") {
        console.error(
            "face-api.js no está cargado. Asegúrate de incluir la librería antes de este script."
        );
        return;
    }

    const tinyOptions = new faceapi.TinyFaceDetectorOptions({
        inputSize: 320,
        scoreThreshold: 0.5,
    });
    const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

    // Función simplificada para logging (solo para debugging)
    const logStatus = (m) => {
        console.log("[Status]", m);
    };

    // CORRECCIÓN 3: Verificar elementos del DOM antes de usar
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

    // Verificar elementos al inicio
    if (!verifyDOMElements()) {
        return;
    }

    // Función asíncrona para cargar los modelos de face-api.js
    async function loadModels() {
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

    // Función asíncrona para iniciar la cámara
    async function startCamera() {
        try {
            // Verificar soporte de getUserMedia
            if (
                !navigator.mediaDevices ||
                !navigator.mediaDevices.getUserMedia
            ) {
                logStatus("Tu navegador no soporta acceso a la cámara.");
                return;
            }

            // Solicitar acceso a la cámara
            stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: "user",
                },
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
                // Iniciar el bucle de dibujo
                drawLoopActive = true;
                requestAnimationFrame(drawLoop);
            } else {
                const errorMsg = "Los modelos no están cargados. Por favor, recarga la página.";
                logStatus(errorMsg);
                showErrorModal("Modelos no cargados", errorMsg);
                return;
            }

            // Habilitar el botón
            btnIdentify.disabled = false;
            btnIdentify.removeAttribute('aria-disabled');
            logStatus("Cámara iniciada correctamente");
            showAlert("success", "Cámara iniciada. Presiona 'Identificar' cuando estés listo.", 4000);
        } catch (e) {
            // Manejo de errores
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

    let lastDetectionTime = 0; // Para limitar la frecuencia de detección

    // Función asíncrona para el bucle de dibujo
    async function drawLoop() {
        if (!drawLoopActive) return;

        try {
            const now = performance.now();
            const DETECTION_INTERVAL = 200; // Reducir la frecuencia a 200 ms

            // Limitar la frecuencia de detección (cada 200 ms)
            if (now - lastDetectionTime > DETECTION_INTERVAL) {
                lastDetectionTime = now;

                // Verificar que el video esté listo y los modelos cargados
                if (video.readyState >= 2 && modelsLoaded) {
                    // Detectar rostro y puntos clave
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
                        const resizedDetections = faceapi.resizeResults(
                            detection,
                            {
                                width: video.videoWidth,
                                height: video.videoHeight,
                            }
                        );

                        // Dibujar detecciones y puntos clave
                        faceapi.draw.drawDetections(overlay, resizedDetections);
                        faceapi.draw.drawFaceLandmarks(
                            overlay,
                            resizedDetections
                        );
                    }
                }
            }

            // Continuar el bucle solo si está activo
            if (drawLoopActive) {
                requestAnimationFrame(drawLoop);
            }
        } catch (error) {
            console.error("Error en drawLoop:", error);
            // Continuar el bucle a pesar del error
            if (drawLoopActive) {
                requestAnimationFrame(drawLoop);
            }
        }
    }

    // Función para capturar descriptores de rostro
    async function captureDescriptor(samples = 5, intervalMs = 160) {
        // Validar parámetros
        if (!Number.isInteger(samples) || samples <= 0) {
            throw new Error(
                "El número de muestras debe ser un entero positivo."
            );
        }
        if (intervalMs <= 0) {
            throw new Error("El intervalo debe ser un número positivo.");
        }

        // Verificar que los modelos estén cargados
        if (!modelsLoaded) {
            throw new Error(
                "Los modelos de reconocimiento facial no están cargados."
            );
        }

        logStatus(`Capturando ${samples} muestras de rostro...`);
        showAlert("info", `Capturando rostro... mantén la cara estable`, 0);

        const list = [];
        let attempts = 0;
        const maxAttempts = samples * 3;

        try {
            while (list.length < samples && attempts < maxAttempts) {
                // Mostrar progreso dinámico
                logStatus(`Captura ${list.length + 1} de ${samples}`);

                // Detectar rostro y descriptor
                const det = await faceapi
                    .detectSingleFace(video, tinyOptions)
                    .withFaceLandmarks()
                    .withFaceDescriptor();

                // Si se detecta un descriptor, agregarlo a la lista
                if (det?.descriptor) {
                    list.push(det.descriptor);
                } else {
                    console.warn("No se detectó un rostro en esta iteración.");
                }

                attempts++;

                // Esperar antes de la siguiente captura
                await sleep(intervalMs);
            }

            // Verificar si se capturaron suficientes muestras
            if (list.length < samples) {
                throw new Error(
                    `No se pudieron capturar suficientes muestras de rostro. Se obtuvieron ${list.length} de ${samples}.`
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

            logStatus("Captura completada con éxito.");
            return Array.from(avg);
        } catch (error) {
            console.error("Error en captureDescriptor:", error);
            logStatus("Error al capturar el descriptor: " + error.message);
            throw error;
        }
    }

    // Constante para los textos de los eventos
    const EVENT_TEXTS = {
        check_in: "Ingreso",
        break_start: "Inicio descanso",
        break_end: "Fin descanso",
        check_out: "Salida",
    };

    // Función para habilitar el paso 2 con los eventos permitidos
    function enableStep2(allowed) {
        try {
            // Validar que `allowed` sea un arreglo
            if (!Array.isArray(allowed)) {
                console.error('El parámetro "allowed" debe ser un arreglo.');
                return;
            }

            // Limpiar el contenido del elemento `eventTypeEl`
            eventTypeEl.innerHTML = "";

            // Si no hay eventos permitidos, mostrar mensaje y deshabilitar elementos
            if (allowed.length === 0) {
                addOption(
                    eventTypeEl,
                    "",
                    "No hay eventos permitidos para hoy"
                );
                disableElements([eventTypeEl, btnGeo, btnMark]);
                return;
            }

            // Agregar opción por defecto
            addOption(eventTypeEl, "", "— selecciona el tipo —");

            // Agregar las opciones permitidas
            allowed.forEach((event) => {
                const text = EVENT_TEXTS[event] || event;
                addOption(eventTypeEl, event, text);
            });

            // Habilitar los elementos necesarios
            enableElements([eventTypeEl, btnGeo]);
            // btnMark se habilitará cuando tengamos ubicación y evento seleccionado
        } catch (error) {
            console.error("Error en enableStep2:", error);
        }
    }

    // Función para agregar una opción a un elemento `<select>`
    function addOption(parent, value, text) {
        const opt = document.createElement("option");
        opt.value = value;
        opt.textContent = text;
        parent.appendChild(opt);
    }

    // Función para deshabilitar múltiples elementos
    function disableElements(elements) {
        elements.forEach((el) => {
            if (el) {
                el.disabled = true;
                el.setAttribute('aria-disabled', 'true');
            }
        });
    }

    // Función para habilitar múltiples elementos
    function enableElements(elements) {
        elements.forEach((el) => {
            if (el) {
                el.disabled = false;
                el.removeAttribute('aria-disabled');
            }
        });
    }

    // Función para verificar si se puede habilitar el botón de marcación
    function checkEnableMark() {
        try {
            // Validar que los elementos necesarios estén definidos
            if (!state || !eventTypeEl || !latEl || !lngEl || !btnMark) {
                console.error("Elementos necesarios no están definidos.");
                return;
            }

            // Verificar si el empleado está seleccionado
            const isEmployeeSelected = !!(state.employee && state.employee.id);

            // Verificar si el tipo de evento está seleccionado
            const isEventSelected = !!eventTypeEl.value;

            // Verificar si la ubicación (latitud y longitud) está completa
            const isLocationSet = !!(latEl.value && lngEl.value);

            // Habilitar o deshabilitar el botón según las condiciones
            btnMark.disabled = !(
                isEmployeeSelected &&
                isEventSelected &&
                isLocationSet
            );
        } catch (error) {
            console.error("Error en checkEnableMark:", error);
        }
    }

    // Función para actualizar la interfaz de usuario con los datos del empleado
    function updateEmployeeUI(employee, lastEvent) {
        try {
            // CORRECCIÓN 4: Usar clases CSS en lugar de style.display
            if (empCard) empCard.classList.remove("hidden");

            if (empName) {
                empName.textContent = `${employee.first_name || ""} ${
                    employee.last_name || ""
                }`.trim();
            }

            if (empDoc) {
                empDoc.textContent = employee.ci
                    ? `Doc: ${employee.ci}`
                    : "Sin documento";
            }

            if (empInfo) {
                empInfo.textContent = `Última marcación: ${lastEvent || "—"}`;
            }
        } catch (error) {
            console.error("Error al actualizar UI del empleado:", error);
        }
    }

    // Variable para controlar si ya se agregó el listener al modal
    let modalListenerAdded = false;
    let errorModalListenerAdded = false;
    let previousActiveElement = null;
    let currentAlertTimeout = null;

    // Función para mostrar el modal
    function showSuccessModal() {
        const modal = document.getElementById("successModal");
        const closeModal = document.getElementById("closeModal");

        if (!modal || !closeModal) {
            console.error("Elementos del modal no encontrados");
            return;
        }

        // Guardar el elemento que tiene el foco actualmente
        previousActiveElement = document.activeElement;

        // Mostrar el modal usando clases CSS con un pequeño delay para la animación
        modal.classList.remove("hidden");

        // Forzar reflow para que la animación funcione correctamente
        void modal.offsetWidth;

        // Agregar clase show para activar la animación
        requestAnimationFrame(() => {
            modal.classList.add("show");
        });

        // Enfocar el botón del modal para accesibilidad y anunciar a lectores de pantalla
        setTimeout(() => {
            closeModal.focus();
            // Anunciar el mensaje a lectores de pantalla
            modal.setAttribute('aria-hidden', 'false');
        }, 100);

        // Prevenir scroll del body cuando el modal está abierto
        document.body.classList.add("modal-open");

        // Agregar eventos para cerrar el modal solo si no se ha agregado antes
        if (!modalListenerAdded) {
            // Cerrar con el botón
            closeModal.addEventListener("click", closeModalHandler);

            // Cerrar al hacer clic en el backdrop (fuera del contenido)
            modal.addEventListener("click", (e) => {
                if (e.target === modal) {
                    closeModalHandler();
                }
            });

            // Cerrar con tecla ESC
            document.addEventListener("keydown", (e) => {
                if (e.key === "Escape" && modal.classList.contains("show")) {
                    closeModalHandler();
                }
            });

            modalListenerAdded = true;
        }
    }

    // Función para cerrar el modal de éxito
    function closeModalHandler() {
        const modal = document.getElementById("successModal");

        if (!modal) return;

        // Ocultar de lectores de pantalla
        modal.setAttribute('aria-hidden', 'true');

        // Remover clase show para activar animación de salida
        modal.classList.remove("show");

        // Esperar a que termine la animación antes de ocultar completamente
        setTimeout(() => {
            modal.classList.add("hidden");

            // Restaurar scroll del body
            document.body.classList.remove("modal-open");

            // Restaurar el foco al elemento anterior
            if (previousActiveElement && previousActiveElement.focus) {
                previousActiveElement.focus();
            }

            // Resetear el sistema
            resetSystem();
        }, 250); // Duración de la animación
    }

    // Función para mostrar el modal de error
    function showErrorModal(title, message) {
        const modal = document.getElementById("errorModal");
        const closeBtn = document.getElementById("closeErrorModal");
        const titleEl = document.getElementById("errorModalTitle");
        const descEl = document.getElementById("errorModalDesc");

        if (!modal || !closeBtn || !titleEl || !descEl) {
            console.error("Elementos del modal de error no encontrados");
            return;
        }

        // Guardar el elemento que tiene el foco actualmente
        previousActiveElement = document.activeElement;

        // Establecer el contenido del modal
        titleEl.textContent = title || "Error";
        descEl.textContent = message || "Ha ocurrido un error inesperado.";

        // Mostrar el modal usando clases CSS
        modal.classList.remove("hidden");

        // Forzar reflow para la animación
        void modal.offsetWidth;

        // Agregar clase show para activar la animación
        requestAnimationFrame(() => {
            modal.classList.add("show");
        });

        // Enfocar el botón del modal para accesibilidad y anunciar a lectores de pantalla
        setTimeout(() => {
            closeBtn.focus();
            // Anunciar el mensaje a lectores de pantalla
            modal.setAttribute('aria-hidden', 'false');
        }, 100);

        // Prevenir scroll del body
        document.body.classList.add("modal-open");

        // Agregar eventos para cerrar el modal solo si no se ha agregado antes
        if (!errorModalListenerAdded) {
            // Cerrar con el botón
            closeBtn.addEventListener("click", closeErrorModalHandler);

            // Cerrar al hacer clic en el backdrop
            modal.addEventListener("click", (e) => {
                if (e.target === modal) {
                    closeErrorModalHandler();
                }
            });

            // Cerrar con tecla ESC
            document.addEventListener("keydown", (e) => {
                if (e.key === "Escape" && modal.classList.contains("show")) {
                    closeErrorModalHandler();
                }
            });

            errorModalListenerAdded = true;
        }
    }

    // Función para cerrar el modal de error
    function closeErrorModalHandler() {
        const modal = document.getElementById("errorModal");

        if (!modal) return;

        // Ocultar de lectores de pantalla
        modal.setAttribute('aria-hidden', 'true');

        // Remover clase show para animación de salida
        modal.classList.remove("show");

        // Esperar a que termine la animación
        setTimeout(() => {
            modal.classList.add("hidden");

            // Restaurar scroll del body
            document.body.classList.remove("modal-open");

            // Restaurar el foco al elemento anterior
            if (previousActiveElement && previousActiveElement.focus) {
                previousActiveElement.focus();
            }
        }, 250);
    }

    // Función para mostrar alertas inline
    function showAlert(type, message, duration = 5000) {
        const container = document.getElementById("alertContainer");

        if (!container) {
            console.error("Contenedor de alertas no encontrado");
            return;
        }

        // Limpiar timeout anterior si existe
        if (currentAlertTimeout) {
            clearTimeout(currentAlertTimeout);
        }

        // Mapeo de iconos por tipo
        const icons = {
            error: "⚠",
            warning: "⚠",
            info: "ℹ",
            success: "✓",
        };

        // Crear el elemento de alerta
        const alertBox = document.createElement("div");
        alertBox.className = `alert-box alert-box-${type}`;
        alertBox.setAttribute("role", "alert");

        alertBox.innerHTML = `
            <div class="alert-box-icon">${icons[type] || "ℹ"}</div>
            <div class="alert-box-content">
                <div class="alert-box-message">${message}</div>
            </div>
        `;

        // Limpiar el contenedor y agregar la nueva alerta
        container.innerHTML = "";
        container.appendChild(alertBox);

        // Auto-ocultar después del tiempo especificado
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

    // Función para limpiar alertas
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

    // Función para resetear el sistema
    function resetSystem() {
        try {
            // Limpiar alertas
            clearAlerts();

            // Limpiar el estado global
            state.employee = null;
            state.allowed = [];
            state.location = null;

            // Detener el stream de video si existe
            if (stream) {
                stream.getTracks().forEach((track) => track.stop());
                stream = null;
            }

            // Detener el draw loop
            drawLoopActive = false;

            // Limpiar el video
            if (video) {
                video.srcObject = null;
            }

            // Limpiar el canvas
            if (ctx) {
                ctx.clearRect(0, 0, overlay.width, overlay.height);
            }

            // CORRECCIÓN 5: Verificar elementos antes de manipularlos
            if (empInfo) empInfo.textContent = "";
            if (empName) empName.textContent = "";
            if (empDoc) empDoc.textContent = "";
            if (latEl) latEl.value = "";
            if (lngEl) lngEl.value = "";
            if (eventTypeEl) {
                eventTypeEl.innerHTML =
                    '<option value="">— primero identificate —</option>';
            }
            if (empCard) empCard.classList.add("hidden");

            // Resetear botones - verificar que existan
            if (btnStart) {
                btnStart.disabled = false;
                btnStart.removeAttribute('aria-disabled');
            }
            if (btnIdentify) {
                btnIdentify.disabled = true;
                btnIdentify.setAttribute('aria-disabled', 'true');
            }
            if (btnGeo) {
                btnGeo.disabled = true;
                btnGeo.setAttribute('aria-disabled', 'true');
            }
            if (btnMark) {
                btnMark.disabled = true;
                btnMark.setAttribute('aria-disabled', 'true');
            }

            logStatus("Sistema reiniciado");
            showAlert("info", "Sistema reiniciado. Presiona 'Iniciar cámara' para comenzar.", 4000);
        } catch (error) {
            console.error("Error al resetear el sistema:", error);
        }
    }

    // Event Listeners

    // Evento para el botón de inicio de cámara
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

    // Evento para el botón de identificación
    if (btnIdentify) {
        btnIdentify.addEventListener("click", async () => {
            btnIdentify.disabled = true;

            try {
                logStatus("Iniciando captura de rostro...");
                const descriptor = await captureDescriptor(5, 160);

                logStatus("Enviando datos para identificación...");
                showAlert("info", "Identificando empleado...", 0);

                // CORRECCIÓN 6: Verificar que el token CSRF existe
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
                    body: JSON.stringify({
                        face_descriptor: descriptor,
                    }),
                });

                

                const json = await resp.json();

                // Verificar si la respuesta del servidor es exitosa
                if (!json.ok) {
                    throw new Error(json.message || "No identificado");
                }

                // Actualizar el estado global
                state.employee = json.employee;
                state.allowed = json.allowed_events || [];

                // Actualizar la interfaz de usuario
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

    // Evento para el botón de geolocalización
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

    // Evento para el botón de marcación
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

                // CORRECCIÓN 7: Verificar token CSRF
                if (!CSRF) {
                    throw new Error("Token CSRF no disponible");
                }

                // Preparar el payload
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

                // Enviar la solicitud
                const resp = await fetch("/marcar", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": CSRF,
                    },
                    body: JSON.stringify(payload),
                });

                // Procesar la respuesta
                const json = await resp.json();
                if (!resp.ok || !json.ok) {
                    throw new Error(
                        json.message || "No se pudo registrar la marcación."
                    );
                }

                logStatus(json.message || "Marcación registrada correctamente");

                // Limpiar alertas previas antes de mostrar el modal de éxito
                clearAlerts();

                // Mostrar el modal de confirmación
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

    // Evento para cambio en el tipo de evento
    if (eventTypeEl) {
        eventTypeEl.addEventListener("change", checkEnableMark);
    }

    // Limpiar recursos al salir de la página
    window.addEventListener("beforeunload", () => {
        if (stream) {
            stream.getTracks().forEach((t) => t.stop());
        }
        drawLoopActive = false;
    });
});
