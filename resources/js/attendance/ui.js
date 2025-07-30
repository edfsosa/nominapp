let employees = [];
let faceMatcher;
let recognitionEnabled = false;
let recognitionStarted = false;

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
    const descriptors = [];
    const options = new faceapi.TinyFaceDetectorOptions({
        inputSize: 512,
        scoreThreshold: 0.4,
    });

    for (const emp of employees) {
        if (!emp.photo) continue;
        const imageUrl = `/storage/${emp.photo}`;
        try {
            const img = await faceapi.fetchImage(imageUrl);
            const det = await faceapi
                .detectSingleFace(img, options)
                .withFaceLandmarks()
                .withFaceDescriptor();

            if (det) {
                descriptors.push(
                    new faceapi.LabeledFaceDescriptors(emp.id.toString(), [
                        det.descriptor,
                    ])
                );
            }
        } catch (e) {
            console.error(`Error procesando foto de empleado ${emp.id}:`, e);
        }
    }

    if (!descriptors.length) {
        throw new Error(
            "No se encontraron rostros válidos en las fotos de los empleados."
        );
    }

    faceMatcher = new faceapi.FaceMatcher(descriptors, 0.5);
}

function startLiveRecognition({ video, overlay, typeSelect, messageBox }) {
    recognitionEnabled = true;
    const ctx = overlay.getContext("2d");
    const interval = setInterval(async () => {
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
        recognitionEnabled = false;
        const emp = employees.find((e) => e.id.toString() === match.label);
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
                    Accept: "application/json",
                    "X-CSRF-TOKEN": csrf,
                },
                body: JSON.stringify({
                    event_type: typeSelect.value,
                    employee_id: emp.id,
                    location: window.currentLocation
                        .split(",")
                        .map((coord) => parseFloat(coord)), // [lat, lng]
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
    return () => clearInterval(interval);
}

export function setupUI() {
    const branchSelect = document.getElementById("branch");
    const typeSelect = document.getElementById("type");
    const messageBox = document.getElementById("messageBox");
    const video = document.getElementById("video");
    const overlay = document.getElementById("overlay");

    branchSelect.addEventListener("change", async () => {
        messageBox.textContent = "Cargando empleados…";
        messageBox.className = "alert alert-info";
        try {
            await loadEmployees(branchSelect.value);
            typeSelect.disabled = false;
            messageBox.textContent = "Seleccione tipo de evento";
            messageBox.className = "alert alert-success";
        } catch (err) {
            console.error(err);
            messageBox.textContent = `❌ ${err.message}`;
            messageBox.className = "alert alert-danger";
        }
    });

    typeSelect.addEventListener("change", async () => {
        messageBox.textContent = "Configurando cámara…";
        messageBox.className = "alert alert-info";
        try {
            await setupCamera(video, overlay);
            messageBox.textContent = "Cámara configurada ✅";
            messageBox.className = "alert alert-success";
            if (!recognitionStarted) {
                messageBox.textContent = "Cargando modelos…";
                messageBox.className = "alert alert-info";
                await loadModels();
                messageBox.textContent = "Cargando descriptores…";
                messageBox.className = "alert alert-info";
                await loadLabeledDescriptors();
                setupUI.cleanup = startLiveRecognition({
                    video,
                    overlay,
                    typeSelect,
                    messageBox,
                });
                recognitionStarted = true;
            }
            recognitionEnabled = true;
            messageBox.textContent = "Listo para reconocimiento ✅";
            messageBox.className = "alert alert-success";
        } catch (err) {
            console.error(err);
            messageBox.textContent = `❌ ${err.message}`;
            messageBox.className = "alert alert-danger";
        }
    });
}
