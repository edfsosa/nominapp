<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Marcación facial</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
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

        select,
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
    </style>
</head>

<body>
    <div class="container">
        <h1>Marcación facial</h1>

        <div class="grid">
            <div class="card">
                <h3>Paso 1 · Identificación</h3>
                <div class="video-wrap">
                    <video id="video" autoplay playsinline muted></video>
                    <canvas id="overlay"></canvas>
                </div>
                <div class="row">
                    <button id="btnStart" class="btn btn-gray">Iniciar cámara</button>
                    <button id="btnIdentify" class="btn btn-blue" disabled>Identificar</button>
                    <span id="status" class="status"></span>
                </div>

                <div id="empCard" class="emp" style="display:none">
                    <img id="empAvatar" alt="avatar" src="">
                    <div>
                        <div id="empName" style="font-weight:600"></div>
                        <div id="empDoc" class="status"></div>
                        <div id="empInfo" class="status"></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <h3>Paso 2 · Datos de marcación</h3>

                <div class="row">
                    <div>
                        <label>Tipo de marcación</label><br>
                        <select id="eventType" disabled>
                            <option value="">— primero identificate —</option>
                        </select>
                    </div>


                </div>

                <div class="row">
                    <button id="btnGeo" class="btn btn-blue" disabled>Obtener ubicación</button>
                    <button id="btnMark" class="btn btn-green" disabled>Confirmar marcación</button>
                </div>

                <div class="row">
                    <div><label>Lat.</label><br><input id="lat" type="text" readonly></div>
                    <div><label>Lng.</label><br><input id="lng" type="text" readonly></div>
                    <div><label>Precisión (m)</label><br><input id="acc" type="text" readonly></div>
                </div>
                <p class="status" style="margin-top:8px">La ubicación es **obligatoria** para confirmar.</p>
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
            const btnIdentify = document.getElementById('btnIdentify');
            const btnGeo = document.getElementById('btnGeo');
            const btnMark = document.getElementById('btnMark');

            const statusEl = document.getElementById('status');
            const eventTypeEl = document.getElementById('eventType');
            const branchEl = document.getElementById('branchId');

            const empCard = document.getElementById('empCard');
            const empAvatar = document.getElementById('empAvatar');
            const empName = document.getElementById('empName');
            const empDoc = document.getElementById('empDoc');
            const empInfo = document.getElementById('empInfo');

            const latEl = document.getElementById('lat');
            const lngEl = document.getElementById('lng');
            const accEl = document.getElementById('acc');

            const CSRF = document.querySelector('meta[name=csrf-token]').content;
            const MODELS_URI = '/models';
            let stream = null;
            let state = {
                employee: null,
                allowed: [],
                location: null
            };

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
                    btnIdentify.disabled = false;
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

            function enableStep2(allowed) {
                // Popular event types permitidos
                eventTypeEl.innerHTML = '';
                if (allowed.length === 0) {
                    const opt = document.createElement('option');
                    opt.value = '';
                    opt.textContent = 'No hay eventos permitidos para hoy';
                    eventTypeEl.appendChild(opt);
                    eventTypeEl.disabled = true;
                    btnGeo.disabled = true;
                    btnMark.disabled = true;
                    return;
                }
                for (const ev of allowed) {
                    const opt = document.createElement('option');
                    opt.value = ev;
                    opt.textContent = ({
                        check_in: 'Ingreso',
                        break_start: 'Inicio descanso',
                        break_end: 'Fin descanso',
                        check_out: 'Salida'
                    })[ev] || ev;
                    eventTypeEl.appendChild(opt);
                }
                eventTypeEl.disabled = false;
                if (branchEl) branchEl.disabled = false;
                btnGeo.disabled = false;
                // btnMark se habilitará cuando tengamos ubicación
            }

            function checkEnableMark() {
                const okEmp = !!state.employee;
                const okEvent = !!eventTypeEl.value;
                const okLoc = !!(latEl.value && lngEl.value);
                btnMark.disabled = !(okEmp && okEvent && okLoc);
            }

            btnStart.addEventListener('click', async () => {
                btnStart.disabled = true;
                await loadModels();
                await startCamera();
            });

            btnIdentify.addEventListener('click', async () => {
                btnIdentify.disabled = true;
                try {
                    const descriptor = await captureDescriptor(5, 160);
                    const resp = await fetch('{{ route('mark.identify') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': CSRF,
                        },
                        body: JSON.stringify({
                            face_descriptor: descriptor
                        }),
                    });
                    const json = await resp.json();
                    if (!resp.ok || !json.ok) throw new Error(json.message || 'No identificado');

                    state.employee = json.employee;
                    state.allowed = json.allowed_events || [];

                    // UI empleado
                    empCard.style.display = 'flex';
                    empName.textContent = json.employee.first_name + ' ' + json.employee.last_name;
                    empDoc.textContent = json.employee.ci ? ('Doc: ' + json.employee
                        .ci) : '';
                    empInfo.textContent =
                        `Distancia: ${Number(json.distance).toFixed(4)} · Último: ${json.last_event ?? '—'}`;
                    empAvatar.src = json.employee.avatar_url || 'https://placehold.co/112x112?text=EMP';

                    enableStep2(state.allowed);
                    checkEnableMark();
                    logStatus('Empleado identificado ✔');
                } catch (e) {
                    logStatus('Error: ' + e.message);
                    btnIdentify.disabled = false;
                }
            });

            btnGeo.addEventListener('click', () => {
                if (!navigator.geolocation) {
                    alert('Geolocalización no soportada');
                    return;
                }
                navigator.geolocation.getCurrentPosition(
                    (pos) => {
                        latEl.value = pos.coords.latitude.toFixed(6);
                        lngEl.value = pos.coords.longitude.toFixed(6);
                        accEl.value = pos.coords.accuracy?.toFixed(1) || '';
                        checkEnableMark();
                    },
                    (err) => alert('No se pudo obtener la ubicación: ' + err.message), {
                        enableHighAccuracy: true,
                        timeout: 8000,
                        maximumAge: 0
                    }
                );
            });

            btnMark.addEventListener('click', async () => {
                btnMark.disabled = true;
                try {
                    const payload = {
                        employee_id: state.employee.id,
                        event_type: eventTypeEl.value,
                        branch_id: branchEl ? (branchEl.value || null) : null,
                        location: {
                            lat: parseFloat(latEl.value),
                            lng: parseFloat(lngEl.value),
                            accuracy: accEl.value ? parseFloat(accEl.value) : null,
                        },
                    };

                    const resp = await fetch('{{ route('mark.store') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': CSRF
                        },
                        body: JSON.stringify(payload),
                    });
                    const json = await resp.json();
                    if (!resp.ok || !json.ok) throw new Error(json.message ||
                        'No se pudo registrar la marcación');

                    logStatus(json.message);
                    // Reset sólo lo necesario
                    btnMark.disabled = false;
                    // Si querés “cerrar sesión” de la persona identificada:
                    // location.reload();
                } catch (e) {
                    logStatus('Error: ' + e.message);
                    btnMark.disabled = false;
                }
            });

            eventTypeEl.addEventListener('change', checkEnableMark);
            window.addEventListener('beforeunload', () => {
                if (stream) stream.getTracks().forEach(t => t.stop());
            });
        });
    </script>
</body>

</html>
