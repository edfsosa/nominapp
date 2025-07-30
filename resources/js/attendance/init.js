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
                window.currentLocation = `${latitude},${longitude}`;
                resolve(window.currentLocation);
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

function showMessage(text, variant, withRetry = false) {
    const box = document.getElementById("messageBox");
    box.innerHTML = text;
    box.className = `alert alert-${variant}`;
    if (withRetry) {
        const btn = document.createElement("button");
        btn.textContent = "Reintentar";
        btn.className = "btn btn-sm btn-primary ml-2";
        btn.onclick = initializeApp;
        box.appendChild(btn);
    }
}

function handleInitError(err) {
    const msg = err.message.toLowerCase();
    if (msg.includes("permiso denegado") || msg.includes("ubicación")) {
        showMessage(
            "Necesitamos tu ubicación para continuar.",
            "warning",
            true
        );
    } else {
        showMessage(`❌ ${err.message}`, "danger");
    }
}

export async function initializeApp() {
    const branchSelect = document.getElementById("branch");
    const typeSelect = document.getElementById("type");
    branchSelect.disabled = true;
    typeSelect.disabled = true;

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
