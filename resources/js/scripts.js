// scripts.js

// Variables globales
let employees = [];
let faceMatcher;
let currentLocation = "";
let recognitionEnabled = false;
let recognitionStarted = false;

// DOMContentLoaded: arranque principal
document.addEventListener("DOMContentLoaded", async () => {
    const branchSelect = document.getElementById("branch");
    const typeSelect = document.getElementById("type");
    const messageBox = document.getElementById("messageBox");
    const video = document.getElementById("video");
    const overlay = document.getElementById("overlay");

    // Deshabilitar selects hasta inicializar
    branchSelect.disabled = true;
    typeSelect.disabled = true;

    // Helper mensajes
    const showMessage = (text, variant) => {
        messageBox.textContent = text;
        messageBox.className = `alert alert-${variant}`;
    };

    try {
        // 1) Ubicación
        showMessage("Obteniendo ubicación…", "info");
        await requireLocation();

        // 2) Cámara
        showMessage("Configurando cámara…", "info");
        await setupCamera(video, overlay);

        // 3) Cargar sucursales
        showMessage("Cargando sucursales…", "info");
        await loadBranches();
        branchSelect.disabled = false;
        showMessage("Seleccione una sucursal", "success");
    } catch (err) {
        console.error(err);
        showMessage(`❌ ${err.message}`, "danger");
        return;
    }

    // Al cambiar de sucursal
    branchSelect.addEventListener("change", async () => {
        const branchId = branchSelect.value;
        if (!branchId) return;

        showMessage("Cargando empleados…", "info");
        try {
            recognitionEnabled = false;
            await loadEmployees(branchId);

            if (!recognitionStarted) {
                showMessage("Cargando modelos…", "info");
                await loadModels();
            }

            showMessage("Cargando descriptores…", "info");
            await loadLabeledDescriptors();

            if (!recognitionStarted) {
                startLiveRecognition({
                    video,
                    overlay,
                    typeSelect,
                    messageBox,
                });
                recognitionStarted = true;
            }

            recognitionEnabled = true;
            typeSelect.disabled = false;
            showMessage("Empleados de sucursal cargados ✅", "success");
        } catch (err) {
            console.error(err);
            showMessage(`❌ ${err.message}`, "danger");
        }
    });
});

// 0) Cargar sucursales
async function loadBranches() {
    const branchSelect = document.getElementById("branch");
    branchSelect.innerHTML = "<option>Cargando…</option>";
    const res = await fetch("/api/branches");
    if (!res.ok) throw new Error(res.statusText);
    const list = await res.json();
    branchSelect.innerHTML = "<option disabled selected>Seleccione...</option>";
    list.forEach((b) => {
        const o = document.createElement("option");
        o.value = b.id;
        o.textContent = b.name;
        branchSelect.appendChild(o);
    });
}

// 1) Ubicación
async function requireLocation() {
    if (!navigator.geolocation) {
        throw new Error("Geolocalización no soportada.");
    }
    const opts = {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 60000,
    };
    return new Promise((resolve, reject) => {
        navigator.geolocation.getCurrentPosition(
            ({ coords: { latitude, longitude } }) => {
                currentLocation = `${latitude},${longitude}`;
                resolve(currentLocation);
            },
            (err) => {
                const msgs = {
                    1: "Permiso denegado",
                    2: "Posición no disponible",
                    3: "Tiempo agotado",
                };
                reject(new Error(msgs[err.code] || "Error de ubicación"));
            },
            opts
        );
    });
}

// 2) Cargar empleados
async function loadEmployees(branchId) {
    const res = await fetch(
        `/api/employees?branch_id=${encodeURIComponent(branchId)}`
    );
    if (!res.ok) throw new Error(res.statusText);
    const list = await res.json();
    if (!Array.isArray(list)) throw new Error("Respuesta inválida");
    employees = list;
}

// 3) Configurar cámara
async function setupCamera(video, overlay) {
    const resize = () => {
        overlay.width = video.videoWidth;
        overlay.height = video.videoHeight;
    };
    video.addEventListener("loadedmetadata", resize);
    window.addEventListener("resize", resize);

    const stream = await navigator.mediaDevices.getUserMedia({ video: true });
    video.srcObject = stream;
    await new Promise((r) => (video.onloadedmetadata = r));
    video.play();
}

// 4) Cargar modelos
async function loadModels() {
    const URL = "/models";
    await Promise.all([
        faceapi.nets.tinyFaceDetector.loadFromUri(URL),
        faceapi.nets.faceLandmark68Net.loadFromUri(URL),
        faceapi.nets.faceRecognitionNet.loadFromUri(URL),
    ]);
}

// 5) Cargar descriptores
async function loadLabeledDescriptors() {
    const desc = [];
    for (const emp of employees) {
        if (!emp.photo) continue;
        const img = await faceapi.fetchImage(`/storage/${emp.photo}`);
        const det = await faceapi
            .detectSingleFace(img, new faceapi.TinyFaceDetectorOptions())
            .withFaceLandmarks()
            .withFaceDescriptor();
        if (det) {
            desc.push(
                new faceapi.LabeledFaceDescriptors(`${emp.id}`, [
                    det.descriptor,
                ])
            );
        }
    }
    if (!desc.length) throw new Error("No hay rostros válidos");
    faceMatcher = new faceapi.FaceMatcher(desc, 0.5);
}

// 6) Reconocimiento en vivo
function startLiveRecognition({ video, overlay, typeSelect, messageBox }) {
    recognitionEnabled = true;
    const ctx = overlay.getContext("2d");

    setInterval(async () => {
        // ** NUEVA VALIDACIÓN: exigir selección de tipo antes de reconocer **
        const eventType = typeSelect.value;
        if (!eventType) {
            messageBox.textContent = "Seleccione el tipo de marcación primero";
            messageBox.className = "alert alert-warning";
            return;
        }

        if (!recognitionEnabled || !faceMatcher) return;
        const result = await faceapi
            .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
            .withFaceLandmarks()
            .withFaceDescriptor();

        ctx.clearRect(0, 0, overlay.width, overlay.height);

        if (!result) {
            messageBox.textContent = "Buscando rostro…";
            messageBox.className = "alert alert-warning";
            return;
        }

        const size = { width: video.width, height: video.height };
        faceapi.matchDimensions(overlay, size);
        const resized = faceapi.resizeResults(result, size);
        faceapi.draw.drawDetections(overlay, resized);

        const match = faceMatcher.findBestMatch(result.descriptor);
        if (match.label === "unknown") {
            messageBox.textContent = "❌ Rostro no reconocido";
            messageBox.className = "alert alert-danger";
            return;
        }

        // Éxito
        recognitionEnabled = false;
        const emp = employees.find((e) => `${e.id}` === match.label);
        if (!emp) {
            messageBox.textContent = "❌ Empleado no registrado";
            messageBox.className = "alert alert-danger";
            return;
        }
        messageBox.textContent = `✅ ${emp.first_name} ${emp.last_name}`;
        messageBox.className = "alert alert-success";

        // Enviar marcación
        try {
            const csrf = document.querySelector(
                'meta[name="csrf-token"]'
            ).content;
            const res = await fetch("/marcar", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": csrf,
                },
                body: JSON.stringify({
                    event_type: typeSelect.value,
                    employee_id: match.label,
                    location: {
                        lat: Number(currentLocation.split(",")[0]),
                        lng: Number(currentLocation.split(",")[1]),
                    },
                }),
            });
            const json = await res.json();
            if (!res.ok || !json.success) {
                throw new Error(json.message || "Error en servidor");
            }
            // Sonido de éxito
            document.getElementById("successSound").play();
            messageBox.textContent = "✅ Marcación registrada";
        } catch (err) {
            console.error(err);
            document.getElementById("errorSound").play();
            messageBox.textContent = `❌ ${err.message}`;
            messageBox.className = "alert alert-danger";
        }

        // Reiniciar en 5s
        setTimeout(() => {
            recognitionEnabled = true;
            messageBox.textContent = "Listo para reconocimiento";
            messageBox.className = "alert alert-info";
        }, 5000);
    }, 1500);
}
