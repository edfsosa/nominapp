// resources/js/scripts.js

let employees = [];
let faceMatcher;
let currentLocation = "";
let recognitionEnabled = false;
let recognitionStarted = false;

function updateClock() {
    const clockEl = document.getElementById("clock");
    if (!clockEl) return;
    const now = new Date();
    clockEl.textContent =
        now.toLocaleDateString("es-ES", {
            weekday: "long",
            year: "numeric",
            month: "long",
            day: "numeric",
        }) +
        " | " +
        now.toLocaleTimeString("es-ES", {
            hour: "2-digit",
            minute: "2-digit",
            second: "2-digit",
        });
}

document.addEventListener("DOMContentLoaded", () => {
    const branchSelect = document.getElementById("branch");
    const typeSelect = document.getElementById("type");
    const messageBox = document.getElementById("messageBox");
    const video = document.getElementById("video");
    const overlay = document.getElementById("overlay");

    branchSelect.disabled = true;
    typeSelect.disabled = true;
    updateClock();
    setInterval(updateClock, 1000);

    function showMessage(text, variant, withRetry = false) {
        messageBox.innerHTML = text;
        messageBox.className = `alert alert-${variant}`;
        if (withRetry) {
            const btn = document.createElement("button");
            btn.textContent = "Reintentar";
            btn.className = "btn btn-sm btn-primary ml-2";
            btn.onclick = initializeApp;
            messageBox.appendChild(btn);
        }
    }

    function handleInitError(err) {
        console.error(err);
        const msg = err.message.toLowerCase();
        if (msg.includes("permiso denegado") || msg.includes("ubicación")) {
            showMessage(
                "Necesitamos tu ubicación para continuar.",
                "warning",
                true
            );
        } else if (
            err.name === "NotAllowedError" ||
            msg.includes("permission denied")
        ) {
            showMessage(
                "Necesitamos acceso a la cámara para continuar.",
                "warning",
                true
            );
        } else {
            showMessage(`❌ ${err.message}`, "danger");
        }
    }

    async function initializeApp() {
        showMessage("Obteniendo ubicación…", "info");
        try {
            await requireLocation();
            showMessage("Ubicación obtenida ✅", "success");
        } catch (err) {
            return handleInitError(err);
        }
        showMessage("Cargando sucursales…", "info");
        try {
            await loadBranches();
            branchSelect.disabled = false;
            showMessage("Seleccione una sucursal", "success");
        } catch (err) {
            return handleInitError(err);
        }
    }

    initializeApp();

    branchSelect.addEventListener("change", async () => {
        const branchId = branchSelect.value;
        if (!branchId) return;

        showMessage("Cargando empleados…", "info");
        try {
            await loadEmployees(branchId);
            typeSelect.disabled = false;
            showMessage("Seleccione tipo de evento", "success");
        } catch (err) {
            console.error(err);
            showMessage(`❌ ${err.message}`, "danger");
        }
    });

    typeSelect.addEventListener("change", async () => {
        const eventType = typeSelect.value;
        if (!eventType) return;

        showMessage("Configurando cámara…", "info");
        try {
            await setupCamera(video, overlay);
        } catch (err) {
            return handleInitError(err);
        }

        try {
            if (!recognitionStarted) {
                showMessage("Cargando modelos…", "info");
                await loadModels();
                showMessage("Cargando descriptores…", "info");
                await loadLabeledDescriptors();
                startLiveRecognition({
                    video,
                    overlay,
                    typeSelect,
                    messageBox,
                });
                recognitionStarted = true;
            }
            recognitionEnabled = true;
            showMessage("Listo para reconocimiento ✅", "success");
        } catch (err) {
            console.error(err);
            showMessage(`❌ ${err.message}`, "danger");
        }
    });
});

async function loadBranches() {
    const branchSelect = document.getElementById("branch");
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

async function requireLocation() {
    if (!navigator.geolocation)
        throw new Error("Geolocalización no soportada.");
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

async function loadEmployees(branchId) {
    const res = await fetch(
        `/api/employees?branch_id=${encodeURIComponent(branchId)}`
    );
    if (!res.ok) throw new Error(res.statusText);
    const list = await res.json();
    if (!Array.isArray(list)) throw new Error("Respuesta inválida");
    employees = list;
}

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

async function loadModels() {
    const URL = "/models";
    await Promise.all([
        faceapi.nets.tinyFaceDetector.loadFromUri(URL),
        faceapi.nets.faceLandmark68Net.loadFromUri(URL),
        faceapi.nets.faceRecognitionNet.loadFromUri(URL),
    ]);
}

async function loadLabeledDescriptors() {
    const desc = [];
    for (const emp of employees) {
        if (!emp.photo) continue;
        const imageUrl = `/storage/${emp.photo}`;
        const img = await faceapi.fetchImage(imageUrl);
        const detection = await faceapi
            .detectSingleFace(
                img,
                new faceapi.TinyFaceDetectorOptions({
                    inputSize: 512,
                    scoreThreshold: 0.4,
                })
            )
            .withFaceLandmarks()
            .withFaceDescriptor();
        if (detection) {
            desc.push(
                new faceapi.LabeledFaceDescriptors(emp.id.toString(), [
                    detection.descriptor,
                ])
            );
        }
    }
    if (!desc.length)
        throw new Error(
            "No se encontraron rostros válidos en las fotos de los empleados."
        );
    faceMatcher = new faceapi.FaceMatcher(desc, 0.5);
}

function startLiveRecognition({ video, overlay, typeSelect, messageBox }) {
    recognitionEnabled = true;
    const ctx = overlay.getContext("2d");
    setInterval(async () => {
        if (!recognitionEnabled || !faceMatcher) return;
        const eventType = typeSelect.value;
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
        recognitionEnabled = false;
        const emp = employees.find((e) => `${e.id}` === match.label);
        if (!emp) {
            messageBox.textContent = "❌ Empleado no registrado";
            messageBox.className = "alert alert-danger";
            return;
        }
        messageBox.textContent = `✅ ${emp.first_name} ${emp.last_name}`;
        messageBox.className = "alert alert-success";
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
                    event_type: typeSelect,
                    employee_id: emp.id,
                    location: currentLocation,
                }),
            });
            const json = await res.json();
            if (!res.ok || !json.success)
                throw new Error(json.message || "Error en servidor");
            document.getElementById("successSound").play();
            messageBox.textContent = "✅ Marcación registrada";
        } catch (err) {
            console.error(err);
            document.getElementById("errorSound").play();
            messageBox.textContent = `❌ ${err.message}`;
            messageBox.className = "alert alert-danger";
        }
        setTimeout(() => {
            recognitionEnabled = true;
            messageBox.textContent = "Listo para reconocimiento";
            messageBox.className = "alert alert-info";
        }, 5000);
    }, 1500);
}
