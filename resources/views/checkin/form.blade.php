<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Registro de Marcación</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Fuente bonita -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f4f4f4;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 2rem;
        }

        h1 {
            margin-bottom: 1rem;
            color: #333;
        }

        form {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }

        label {
            display: block;
            margin-top: 1rem;
            font-weight: 600;
        }

        input,
        select {
            width: 100%;
            padding: 0.5rem;
            margin-top: 0.25rem;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        video,
        canvas {
            margin-top: 1rem;
            width: 100%;
            border-radius: 10px;
        }

        .btn {
            margin-top: 1.5rem;
            background-color: #4CAF50;
            color: white;
            padding: 0.75rem;
            border: none;
            border-radius: 5px;
            width: 100%;
            font-weight: bold;
            font-size: 1rem;
            cursor: pointer;
        }

        .btn:hover {
            background-color: #45a049;
        }

        .alert {
            padding: 0.75rem;
            margin-bottom: 1rem;
            border-radius: 5px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>

<body>
    <h1>Registro de Marcación</h1>

    @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
    <div class="alert alert-error">
        <ul style="margin: 0; padding-left: 1.2rem;">
            @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form id="checkin-form" method="POST" action="/marcar" enctype="multipart/form-data">
        @csrf

        <label for="ci">CI:</label>
        <input type="number" id="ci" name="ci" min="1000" max="99999999" required>

        <label for="type">Tipo:</label>
        <select id="type" name="type" required>
            <option value="entrada">Entrada</option>
            <option value="salida">Salida</option>
        </select>

        <video id="video" autoplay playsinline></video>
        <canvas id="canvas" width="300" height="300" style="display:none;"></canvas>
        <img id="preview" src="#" alt="Previsualización" style="display:none; width: 100%; border-radius: 10px; margin-top: 1rem;" />
        <audio id="snapSound" src="{{ asset('sounds/snap.mp3') }}"></audio>


        <input type="file" id="photo" name="photo" style="display:none;" required>
        <input type="hidden" id="latitude" name="latitude">
        <input type="hidden" id="longitude" name="longitude">

        <button type="button" class="btn" onclick="prepararFotoYEnviar()">📸 Marcar</button>
    </form>

    <script>
        const form = document.getElementById('checkin-form');
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        const fileInput = document.getElementById('photo');

        function cargarCamara() {
            navigator.mediaDevices.getUserMedia({
                    video: true
                })
                .then(stream => {
                    video.srcObject = stream;
                })
                .catch(err => {
                    alert("No se pudo acceder a la cámara.");
                    console.error(err);
                });
        }

        async function capturarUbicacion() {
            try {
                const position = await new Promise((resolve, reject) => {
                    navigator.geolocation.getCurrentPosition(resolve, reject);
                });

                document.getElementById('latitude').value = position.coords.latitude;
                document.getElementById('longitude').value = position.coords.longitude;
                console.log("Ubicación capturada:", position.coords.latitude, position.coords.longitude);
            } catch (err) {
                alert("No se pudo obtener la ubicación.");
                console.error(err);
            }
        }

        async function prepararFotoYEnviar() {
            try {
                const context = canvas.getContext('2d');
                context.drawImage(video, 0, 0, canvas.width, canvas.height);

                const snapSound = document.getElementById('snapSound');
                snapSound.play();

                canvas.toBlob(blob => {
                    if (!blob) {
                        alert("Error al capturar la foto.");
                        return;
                    }

                    // Mostrar previsualización
                    const preview = document.getElementById('preview');
                    preview.src = URL.createObjectURL(blob);
                    preview.style.display = 'block';

                    const file = new File([blob], "selfie.jpg", {
                        type: "image/jpeg"
                    });
                    const dt = new DataTransfer();
                    dt.items.add(file);
                    fileInput.files = dt.files;

                    // Dar tiempo para ver la previsualización (opcional)
                    setTimeout(() => {
                        form.submit();
                    }, 1000); // Espera 1 segundo antes de enviar
                }, 'image/jpeg');
            } catch (err) {
                alert("Error al preparar la foto.");
                console.error(err);
            }
        }


        window.onload = () => {
            cargarCamara();
            capturarUbicacion();
        };
    </script>
</body>

</html>