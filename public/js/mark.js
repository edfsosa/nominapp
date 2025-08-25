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

    const CSRF = document.querySelector("meta[name=csrf-token]").content;
    const MODELS_URI = "/models";
    let stream = null;
    let state = {
        employee: null,
        allowed: [],
        location: null,
    };

    const tinyOptions = new faceapi.TinyFaceDetectorOptions({
        inputSize: 320,
        scoreThreshold: 0.5,
    });
    const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
    const logStatus = (m) => (statusEl.textContent = m);

    // funcion asincrona para cargar los modelos de face-api.js
    async function loadModels() {
        try {
            logStatus("Cargando modelos...");
            await Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri(MODELS_URI),
                faceapi.nets.faceLandmark68Net.loadFromUri(MODELS_URI),
                faceapi.nets.faceRecognitionNet.loadFromUri(MODELS_URI),
            ]);
            logStatus("Modelos cargados");
        } catch (error) {
            logStatus(
                "Error al cargar los modelos. Por favor, recarga la página."
            );
            console.error("Error al cargar los modelos:", error);
        }
    }

    // funcion asincrona para iniciar la cámara
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
            const stream = await navigator.mediaDevices.getUserMedia({
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

            // Iniciar el bucle de dibujo
            requestAnimationFrame(drawLoop);

            // Habilitar el botón
            btnIdentify.disabled = false;
        } catch (e) {
            // Manejo de errores
            if (e.name === "NotAllowedError") {
                logStatus("Permiso denegado para acceder a la cámara.");
            } else if (e.name === "NotFoundError") {
                logStatus("No se encontró una cámara en el dispositivo.");
            } else {
                logStatus("No se pudo iniciar la cámara: " + e.message);
            }
        }
    }

    let lastDetectionTime = 0; // Para limitar la frecuencia de detección

    // funcion asincrona para el bucle de dibujo
    async function drawLoop() {
        try {
            const now = performance.now();

            const DETECTION_INTERVAL = 200; // Reducir la frecuencia a 200 ms
            // Limitar la frecuencia de detección (cada 100 ms)
            if (now - lastDetectionTime > DETECTION_INTERVAL) {
                lastDetectionTime = now;

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
                    const resizedDetections = faceapi.resizeResults(detection, {
                        width: video.videoWidth,
                        height: video.videoHeight,
                    });

                    // Dibujar detecciones y puntos clave
                    faceapi.draw.drawDetections(overlay, resizedDetections);
                    faceapi.draw.drawFaceLandmarks(overlay, resizedDetections);
                }
            }

            // Continuar el bucle
            requestAnimationFrame(drawLoop);
        } catch (error) {
            console.error("Error en drawLoop:", error);
        }
    }

    // función para capturar descriptores de rostro
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

        logStatus(`Capturando (${samples})… mantené la cara estable`);
        const list = [];
        let attempts = 0;

        try {
            while (list.length < samples) {
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

                // Evitar bucles infinitos en caso de problemas
                if (++attempts > samples * 3) {
                    throw new Error(
                        "No se pudieron capturar suficientes muestras de rostro."
                    );
                }

                // Esperar antes de la siguiente captura
                await sleep(intervalMs);
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
            throw error; // Relanzar el error para que el llamador lo maneje
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

            // Crear un fragmento para optimizar la inserción de opciones
            const fragment = document.createDocumentFragment();

            // Agregar las opciones permitidas
            allowed.forEach((event) => {
                const text = EVENT_TEXTS[event] || event; // Usar texto mapeado o el valor original
                addOption(fragment, event, text);
            });

            // Agregar las opciones al elemento `eventTypeEl`
            eventTypeEl.appendChild(fragment);

            // Habilitar los elementos necesarios
            enableElements([eventTypeEl, btnGeo]);
            // btnMark se habilitará cuando tengamos ubicación
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
        elements.forEach((el) => (el.disabled = true));
    }

    // Función para habilitar múltiples elementos
    function enableElements(elements) {
        elements.forEach((el) => (el.disabled = false));
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
            const isEmployeeSelected = !!state.employee;

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

    // Evento para el botón de inicio de cámara
    btnStart.addEventListener("click", async () => {
        btnStart.disabled = true;
        await loadModels();
        await startCamera();
    });

    // Evento para el botón de identificación
    btnIdentify.addEventListener("click", async () => {
        btnIdentify.disabled = true; // Deshabilitar el botón para evitar múltiples clics

        try {
            logStatus("Capturando rostro..."); // Informar al usuario
            const descriptor = await captureDescriptor(5, 160);

            logStatus("Identificando empleado..."); // Informar al usuario
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

            // Verificar si la respuesta es válida
            if (!resp.ok) {
                throw new Error(
                    `Error de red: ${resp.status} ${resp.statusText}`
                );
            }

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

            logStatus("Empleado identificado ✔"); // Confirmar éxito
        } catch (e) {
            console.error("Error en la identificación:", e);
            logStatus("Error: " + e.message);
        } finally {
            btnIdentify.disabled = false; // Rehabilitar el botón en cualquier caso
        }
    });

    // Función para actualizar la interfaz de usuario con los datos del empleado
    function updateEmployeeUI(employee, lastEvent) {
        empCard.style.display = "flex";
        empName.textContent = `${employee.first_name} ${employee.last_name}`;
        empDoc.textContent = employee.ci ? `Doc: ${employee.ci}` : "";
        empInfo.textContent = `Última marcación: ${lastEvent ?? "—"}`;
    }

    btnGeo.addEventListener("click", () => {
        if (!navigator.geolocation) {
            alert("Geolocalización no soportada");
            return;
        }
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                latEl.value = pos.coords.latitude.toFixed(6);
                lngEl.value = pos.coords.longitude.toFixed(6);
                checkEnableMark();
            },
            (err) => alert("No se pudo obtener la ubicación: " + err.message),
            {
                timeout: 8000,
                maximumAge: 0,
            }
        );
    });

    // Función para mostrar el modal
    function showSuccessModal() {
        const modal = document.getElementById("successModal");
        const closeModal = document.getElementById("closeModal");

        // Mostrar el modal
        modal.style.display = "flex";

        // Agregar evento para cerrar el modal
        closeModal.addEventListener("click", () => {
            modal.style.display = "none"; // Ocultar el modal
            resetSystem(); // Limpiar el sistema después de cerrar el modal
        });
    }

    function resetSystem() {
        // Limpiar el estado global
        state.employee = null;
        state.allowed = [];
        state.location = null;

        // Limpiar la interfaz
        empInfo.textContent = ""; // Limpiar información del empleado
        latEl.value = ""; // Limpiar latitud
        lngEl.value = ""; // Limpiar longitud
        eventTypeEl.innerHTML = ""; // Limpiar el menú de eventos
        empCard.style.display = "none"; // Ocultar la tarjeta del empleado
        btnMark.disabled = true; // Deshabilitar el botón de marcación
        logStatus("Sistema listo para el siguiente empleado."); // Mensaje de estado
    }

    // Modificación en el bloque de registro de marcación
    btnMark.addEventListener("click", async () => {
        btnMark.disabled = true; // Deshabilitar el botón para evitar múltiples clics

        try {
            // Validar datos antes de enviar
            if (!state.employee || !state.employee.id) {
                throw new Error("Empleado no identificado.");
            }
            if (!eventTypeEl.value) {
                throw new Error("Tipo de evento no seleccionado.");
            }
            if (!latEl.value || !lngEl.value) {
                throw new Error("Ubicación no válida.");
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

            logStatus("Enviando marcación..."); // Informar al usuario

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

            logStatus(json.message); // Confirmar éxito

            // Mostrar el modal de confirmación
            showSuccessModal();
        } catch (e) {
            // Manejo de errores
            console.error("Error en la marcación:", e);
            logStatus("Error: " + e.message);
        } finally {
            btnMark.disabled = false; // Rehabilitar el botón en cualquier caso
        }
    });

    eventTypeEl.addEventListener("change", checkEnableMark);
    window.addEventListener("beforeunload", () => {
        if (stream) stream.getTracks().forEach((t) => t.stop());
    });
});
