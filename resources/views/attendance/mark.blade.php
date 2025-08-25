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

        /* Fondo del modal */
        .modal {
            position: fixed;
            inset: 0;
            /* top:0; right:0; bottom:0; left:0 */
            background: rgba(0, 0, 0, 0.6);
            /* oscurece el fondo */
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        /* Caja del modal */
        .modal-content {
            background: #1e293b;
            /* gris azulado oscuro */
            color: #f1f5f9;
            /* texto claro */
            padding: 24px;
            border-radius: 12px;
            max-width: 400px;
            width: 90%;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
            animation: fadeIn 0.3s ease-out;
        }

        /* Título */
        .modal-content h2 {
            margin-top: 0;
            margin-bottom: 12px;
            font-size: 20px;
            color: #22c55e;
            /* verde éxito */
        }

        /* Texto */
        .modal-content p {
            margin-bottom: 20px;
            font-size: 16px;
            color: #e2e8f0;
        }

        /* Botón */
        #closeModal {
            background: #22c55e;
            color: #fff;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            font-size: 15px;
            cursor: pointer;
            transition: background 0.2s ease-in-out;
        }

        #closeModal:hover {
            background: #16a34a;
            /* verde más oscuro */
        }

        /* Animación de entrada */
        @keyframes fadeIn {
            from {
                transform: scale(0.9);
                opacity: 0;
            }

            to {
                transform: scale(1);
                opacity: 1;
            }
        }
    </style>
    <script defer src="{{ asset('js/mark.js') }}"></script>
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
                    <button id="btnStart" class="btn btn-gray" aria-label="Iniciar cámara">Iniciar cámara</button>
                    <button id="btnIdentify" class="btn btn-blue" aria-label="Identificar empleado"
                        disabled>Identificar</button>
                    <span id="status" class="status"></span>
                </div>

                <div id="empCard" class="emp" style="display:none">
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
                    <button id="btnGeo" class="btn btn-blue" aria-label="Obtener ubicación" disabled>Obtener
                        ubicación</button>
                    <button id="btnMark" class="btn btn-green" aria-label="Confirmar marcación" disabled>Confirmar
                        marcación</button>
                </div>

                <div class="row">
                    <div><label>Lat.</label><br><input id="lat" type="text" readonly></div>
                    <div><label>Lng.</label><br><input id="lng" type="text" readonly></div>
                </div>
                <p class="status" style="margin-top:8px">La ubicación es **obligatoria** para confirmar.</p>
            </div>
        </div>
    </div>

    <div id="successModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h2>¡Marcación registrada!</h2>
            <p>Su marcación se ha registrado correctamente.</p>
            <button id="closeModal">Aceptar</button>
        </div>
    </div>

    <script defer src="https://unpkg.com/face-api.js@0.22.2/dist/face-api.min.js"></script>
</body>

</html>
