<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Capturar rostro</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        /* === mismos estilos base que mark.blade.php === */
        body {
            font-family: system-ui, Arial;
            background: #0b1220;
            color: #e5e7eb;
            margin: 0;
            padding: 24px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 360px;
            gap: 16px;
        }

        .card {
            background: #0f172a;
            border: 1px solid #1f2937;
            border-radius: 12px;
            padding: 16px;
        }

        .row {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 12px;
        }

        label {
            font-size: 14px;
            color: #cbd5e1;
        }

        input[type=text] {
            padding: 8px 10px;
            border-radius: 10px;
            border: 1px solid #1f2937;
            background: #0b1220;
            color: #e5e7eb;
        }

        button {
            border: 0;
            border-radius: 10px;
            padding: 10px 14px;
            color: #fff;
            cursor: pointer;
        }

        .btn-gray {
            background: #374151;
        }

        .btn-blue {
            background: #2563eb;
        }

        .btn-green {
            background: #059669;
        }

        .btn-red {
            background: #b91c1c;
        }

        .btn[disabled] {
            opacity: .6;
            cursor: not-allowed;
        }

        .video-wrap {
            position: relative;
            aspect-ratio: 16/9;
            background: black;
            border-radius: 12px;
            overflow: hidden;
        }

        video,
        canvas {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .status {
            font-size: 14px;
            color: #9ca3af;
        }

        .emp {
            display: flex;
            gap: 12px;
            align-items: center;
            margin-top: 8px;
        }

        .emp img {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid #1f2937;
        }

        .alert {
            background: #064e3b;
            color: #d1fae5;
            padding: 10px 14px;
            border-radius: 10px;
            margin-bottom: 10px;
        }

        .error {
            background: #7f1d1d;
            color: #fee2e2;
            padding: 10px 14px;
            border-radius: 10px;
            margin-bottom: 10px;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Capturar rostro</h1>

        <div class="grid">
            <!-- Columna izquierda: cámara -->
            <div class="card">
                <h3>Paso 1 · Captura</h3>
                <div class="video-wrap">
                    <video id="video" autoplay playsinline muted></video>
                    <canvas id="overlay"></canvas>
                </div>
                <div class="row">
                    <button id="btnStart" class="btn btn-gray">Iniciar cámara</button>
                    <button id="btnCapture" class="btn btn-blue" disabled>Capturar descriptor</button>
                    <span id="status" class="status"></span>
                </div>
            </div>

            <!-- Columna derecha: datos del empleado + guardar -->
            <div class="card">
                <h3>Paso 2 · Confirmación</h3>

                <div class="emp">
                    <img src="{{ $employee->avatar_url ?? ($employee->photo_url ?? ($employee->profile_photo_url ?? 'https://placehold.co/112x112?text=EMP')) }}"
                        alt="avatar">
                    <div>
                        <div style="font-weight:600">
                            {{ $employee->name ?? trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')) ?:
                                'Empleado #' . $employee->id }}
                        </div>
                        @php
                            $doc =
                                $employee->document_number ??
                                ($employee->document ?? ($employee->dni ?? ($employee->ci ?? null)));
                        @endphp
                        @if ($doc)
                            <div class="status">Doc: {{ $doc }}</div>
                        @endif
                        <div class="status" id="descState">Descriptor: —</div>
                    </div>
                </div>

                <form id="saveForm" method="POST" action="{{ route('face.capture.store', $employee) }}"
                    class="row">
                    @csrf
                    <input type="hidden" name="face_descriptor" id="faceDescriptor">
                    <button type="submit" id="btnSave" class="btn btn-green" disabled>Guardar descriptor</button>
                    <button type="button" id="btnCancel" class="btn btn-red" onclick="window.close()">Cancelar</button>
                </form>

                <p class="status" style="margin-top:6px">
                    * Se promedian varias muestras para mayor estabilidad antes de guardar.
                </p>
            </div>
        </div>
    </div>

    <script defer src="https://unpkg.com/face-api.js@0.22.2/dist/face-api.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const video = document.getElementById('video');
            const overlay = document.getElementById('overlay');
            const ctx = overlay.getContext('2d');

            const btnStart = document.getElementById('btnStart');
            const btnCapture = document.getElementById('btnCapture');
            const btnSave = document.getElementById('btnSave');
            const statusEl = document.getElementById('status');
            const descState = document.getElementById('descState');
            const hiddenDescriptor = document.getElementById('faceDescriptor');

            const MODELS_URI = '/models'; // asegurate de tener los pesos en public/models
            let stream = null;

            const tinyOptions = new faceapi.TinyFaceDetectorOptions({
                inputSize: 320,
                scoreThreshold: 0.5
            });
            const sleep = (ms) => new Promise(r => setTimeout(r, ms));
            const logStatus = (m) => statusEl.textContent = m;

            async function loadModels() {
                logStatus('Cargando modelos...');
                await Promise.all([
                    faceapi.nets.tinyFaceDetector.loadFromUri(MODELS_URI),
                    faceapi.nets.faceLandmark68Net.loadFromUri(MODELS_URI),
                    faceapi.nets.faceRecognitionNet.loadFromUri(MODELS_URI),
                ]);
                logStatus('Modelos cargados');
            }

            async function startCamera() {
                try {
                    stream = await navigator.mediaDevices.getUserMedia({
                        video: {
                            facingMode: 'user'
                        },
                        audio: false
                    });
                    video.srcObject = stream;
                    await new Promise(res => video.onloadedmetadata = res);
                    overlay.width = video.videoWidth;
                    overlay.height = video.videoHeight;
                    requestAnimationFrame(drawLoop);
                    btnCapture.disabled = false;
                } catch (e) {
                    logStatus('No se pudo iniciar la cámara: ' + e.message);
                }
            }

            async function drawLoop() {
                const det = await faceapi.detectSingleFace(video, tinyOptions).withFaceLandmarks();
                ctx.clearRect(0, 0, overlay.width, overlay.height);
                if (det) {
                    const dims = faceapi.matchDimensions(overlay, {
                        width: video.videoWidth,
                        height: video.videoHeight
                    }, true);
                    const resized = faceapi.resizeResults(det, dims);
                    faceapi.draw.drawDetections(overlay, resized);
                    faceapi.draw.drawFaceLandmarks(overlay, resized);
                }
                requestAnimationFrame(drawLoop);
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
                    await sleep(intervalMs);
                }
                const avg = new Float32Array(128).fill(0);
                for (const d of list)
                    for (let i = 0; i < 128; i++) avg[i] += d[i];
                for (let i = 0; i < 128; i++) avg[i] /= list.length;
                return Array.from(avg);
            }

            btnStart.addEventListener('click', async () => {
                btnStart.disabled = true;
                await loadModels();
                await startCamera();
            });

            btnCapture.addEventListener('click', async () => {
                btnCapture.disabled = true;
                try {
                    const descriptor = await captureDescriptor(5, 160);
                    hiddenDescriptor.value = JSON.stringify(descriptor);
                    btnSave.disabled = false;
                    descState.textContent = 'Descriptor: listo ✔';
                    logStatus('Descriptor listo. Podés Guardar.');
                } catch (e) {
                    logStatus('Error al capturar: ' + e.message);
                    btnCapture.disabled = false;
                }
            });

            window.addEventListener('beforeunload', () => {
                if (stream) stream.getTracks().forEach(t => t.stop());
            });
        });
    </script>
</body>

</html>
