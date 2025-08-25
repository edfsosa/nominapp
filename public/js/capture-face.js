document.addEventListener("DOMContentLoaded", () => {
    const video = document.getElementById("video");
    const overlay = document.getElementById("overlay");
    const ctx = overlay.getContext("2d");

    const btnStart = document.getElementById("btnStart");
    const btnCapture = document.getElementById("btnCapture");
    const btnSave = document.getElementById("btnSave");
    const statusEl = document.getElementById("status");
    const descState = document.getElementById("descState");
    const hiddenDescriptor = document.getElementById("faceDescriptor");

    const MODELS_URI = "/models";
    let stream = null;
    let isDetecting = false;

    const tinyOptions = new faceapi.TinyFaceDetectorOptions({
        inputSize: 320,
        scoreThreshold: 0.5,
    });

    const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
    const logStatus = (m) => (statusEl.textContent = m);

    async function loadModels() {
        logStatus("Cargando modelos...");
        try {
            await Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri(MODELS_URI),
                faceapi.nets.faceLandmark68Net.loadFromUri(MODELS_URI),
                faceapi.nets.faceRecognitionNet.loadFromUri(MODELS_URI),
            ]);
            logStatus("Modelos cargados");
        } catch (e) {
            logStatus("Error al cargar modelos: " + e.message);
        }
    }

    async function startCamera() {
        try {
            if (
                !navigator.mediaDevices ||
                !navigator.mediaDevices.getUserMedia
            ) {
                throw new Error("Tu navegador no soporta acceso a la cámara.");
            }

            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: "user" },
                audio: false,
            });
            video.srcObject = stream;
            await new Promise((res) => (video.onloadedmetadata = res));
            overlay.width = video.videoWidth;
            overlay.height = video.videoHeight;
            isDetecting = true;
            drawLoop();
            btnCapture.disabled = false;
        } catch (e) {
            logStatus("No se pudo iniciar la cámara: " + e.message);
        }
    }

    async function stopCamera() {
        if (stream) {
            stream.getTracks().forEach((t) => t.stop());
            stream = null;
        }
        isDetecting = false;
        ctx.clearRect(0, 0, overlay.width, overlay.height);
        logStatus("Cámara detenida.");
    }

    async function drawLoop() {
        if (!isDetecting) return;

        const det = await faceapi
            .detectSingleFace(video, tinyOptions)
            .withFaceLandmarks();
        ctx.clearRect(0, 0, overlay.width, overlay.height);
        if (det) {
            const dims = faceapi.matchDimensions(
                overlay,
                { width: video.videoWidth, height: video.videoHeight },
                true
            );
            const resized = faceapi.resizeResults(det, dims);
            faceapi.draw.drawDetections(overlay, resized);
            faceapi.draw.drawFaceLandmarks(overlay, resized);
        } else {
            logStatus("No se detectó ningún rostro.");
        }
        setTimeout(() => requestAnimationFrame(drawLoop), 100); // Limitar FPS
    }

    async function captureDescriptor(samples = 5, intervalMs = 160) {
        logStatus(`Capturando (${samples})… mantené la cara estable`);
        const list = [];
        while (list.length < samples) {
            const det = await faceapi
                .detectSingleFace(video, tinyOptions)
                .withFaceLandmarks()
                .withFaceDescriptor();
            if (det?.descriptor) list.push(det.descriptor);
            else logStatus("No se detectó un rostro. Intentando nuevamente...");
            await sleep(intervalMs);
        }
        const avg = new Float32Array(128).fill(0);
        for (const d of list) for (let i = 0; i < 128; i++) avg[i] += d[i];
        for (let i = 0; i < 128; i++) avg[i] /= list.length;
        return Array.from(avg);
    }

    btnStart.addEventListener("click", async () => {
        btnStart.disabled = true;
        await loadModels();
        await startCamera();
    });

    btnCapture.addEventListener("click", async () => {
        btnCapture.disabled = true;
        try {
            const descriptor = await captureDescriptor(5, 160);
            hiddenDescriptor.value = JSON.stringify(descriptor);
            btnSave.disabled = false;
            descState.textContent = "Descriptor: listo ✔";
            logStatus("Descriptor listo. Podés Guardar.");
        } catch (e) {
            logStatus("Error al capturar: " + e.message);
        } finally {
            btnCapture.disabled = false;
        }
    });

    btnSave.addEventListener("click", () => {
        logStatus("Guardando descriptor...");
        // Aquí puedes agregar lógica para guardar el descriptor

        // Mostrar el modal de confirmación
        const modal = document.getElementById("confirmationModal");
        const closeModal = document.getElementById("closeModal");

        modal.style.display = "flex"; // Mostrar el modal

        // Agregar evento para cerrar el modal
        closeModal.addEventListener("click", () => {
            modal.style.display = "none"; // Ocultar el modal
            // Bloquear los botones de guardar y capturar
            btnSave.disabled = true;
            btnCapture.disabled = true;
        });
    });

    window.addEventListener("beforeunload", stopCamera);
});
