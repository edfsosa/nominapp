document.addEventListener("DOMContentLoaded", () => {
    const video = document.getElementById("video");
    const overlay = document.getElementById("overlay");
    const ctx = overlay.getContext("2d");

    const btnStart = document.getElementById("btnStart");
    const btnIdentify = document.getElementById("btnIdentify");
    const btnGeo = document.getElementById("btnGeo");
    const btnMark = document.getElementById("btnMark");

    const statusEl = document.getElementById("status");
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
    const logStatus = (m) => {
        if (statusEl) {
            statusEl.textContent = m;
        } else {
            console.log("Status:", m);
        }
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
            statusEl,
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
            logStatus("Error: Faltan elementos requeridos en la página");
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
            logStatus("Cargando modelos...");
            await Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri(MODELS_URI),
                faceapi.nets.faceLandmark68Net.loadFromUri(MODELS_URI),
                faceapi.nets.faceRecognitionNet.loadFromUri(MODELS_URI),
            ]);
            modelsLoaded = true;
            logStatus("Modelos cargados");
        } catch (error) {
            modelsLoaded = false;
            logStatus(
                "Error al cargar los modelos. Por favor, recarga la página."
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
                logStatus("Los modelos no están cargados. Recarga la página.");
                return;
            }

            // Habilitar el botón
            btnIdentify.disabled = false;
            logStatus(
                "Cámara iniciada. Presiona 'Identificar' cuando estés listo."
            );
        } catch (e) {
            // Manejo de errores
            if (e.name === "NotAllowedError") {
                logStatus("Permiso denegado, por favor habilita la cámara y recarga la página.");
            } else if (e.name === "NotFoundError") {
                logStatus("No se encontró una cámara en el dispositivo. Por favor, conecta una cámara y recarga la página.");
            } else {
                logStatus("No se pudo iniciar la cámara: " + e.message);
            }
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

        logStatus(`Capturando (${samples})… mantené la cara estable`);
        const list = [];
        let attempts = 0;
        const maxAttempts = samples * 3;

        try {
            while (list.length < samples && attempts < maxAttempts) {
                // Mostrar progreso dinámico
                logStatus(
                    `Capturando rostro (${list.length + 1} de ${samples})…`
                );

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
            if (el) el.disabled = true;
        });
    }

    // Función para habilitar múltiples elementos
    function enableElements(elements) {
        elements.forEach((el) => {
            if (el) el.disabled = false;
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
            // CORRECCIÓN 4: Verificar que los elementos existen antes de usarlos
            if (empCard) empCard.style.display = "flex";

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

    // Función para mostrar el modal
    function showSuccessModal() {
        const modal = document.getElementById("successModal");
        const closeModal = document.getElementById("closeModal");

        if (!modal || !closeModal) {
            console.error("Elementos del modal no encontrados");
            return;
        }

        // Mostrar el modal
        modal.style.display = "flex";

        // Agregar evento para cerrar el modal solo si no se ha agregado antes
        if (!modalListenerAdded) {
            closeModal.addEventListener("click", () => {
                modal.style.display = "none";
                resetSystem();
            });
            modalListenerAdded = true;
        }
    }

    // Función para resetear el sistema
    function resetSystem() {
        try {
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
            if (empCard) empCard.style.display = "none";

            // Resetear botones - verificar que existan
            if (btnStart) btnStart.disabled = false;
            if (btnIdentify) btnIdentify.disabled = true;
            if (btnGeo) btnGeo.disabled = true;
            if (btnMark) btnMark.disabled = true;

            logStatus(
                "Sistema reiniciado. Presiona 'Iniciar cámara' para comenzar."
            );
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
                logStatus("Capturando rostro...");
                const descriptor = await captureDescriptor(5, 160);

                logStatus("Identificando empleado...");

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
            } catch (e) {
                console.error("Error en la identificación:", e);
                logStatus("Error: " + e.message);
            } finally {
                btnIdentify.disabled = false;
            }
        });
    }

    // Evento para el botón de geolocalización
    if (btnGeo) {
        btnGeo.addEventListener("click", () => {
            if (!navigator.geolocation) {
                logStatus("Geolocalización no soportada en este navegador");
                return;
            }

            btnGeo.disabled = true;
            logStatus("Obteniendo ubicación...");

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
                    btnGeo.disabled = false;
                },
                (err) => {
                    console.error("Error de geolocalización:", err);
                    switch (err.code) {
                        case 1:
                            logStatus(
                                "Permiso denegado, por favor habilita la ubicación y recarga la página."
                            );
                            break;
                        case 2:
                            logStatus("No se pudo obtener la ubicación. Por favor, intenta de nuevo.");
                            break;
                        case 3:
                            logStatus("Tiempo de espera agotado. Por favor, intenta de nuevo.");
                            break;
                    
                        default: 
                            logStatus("Error desconocido al obtener ubicación. Por favor, intenta de nuevo.");
                            break;
                    }
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

                logStatus("Enviando marcación...");

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

                // Mostrar el modal de confirmación
                showSuccessModal();
            } catch (e) {
                console.error("Error en la marcación:", e);
                logStatus("Error: " + e.message);
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
