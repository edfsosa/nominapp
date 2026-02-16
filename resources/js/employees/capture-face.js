/**
 * =============================================================================
 * CAPTURA FACIAL PARA EMPLEADOS - ENROLAMIENTO (ADMIN)
 * =============================================================================
 *
 * @fileoverview Punto de entrada para la captura facial iniciada por admin.
 *               Usa la clase base FaceCaptureApp del módulo compartido.
 *
 * @requires ../shared/FaceCaptureApp.js
 * @requires face-api.js (cargado via CDN en la vista)
 */

import { FaceCaptureApp } from '../shared/FaceCaptureApp.js';

// =============================================================================
// INICIALIZACIÓN DE LA APLICACIÓN
// =============================================================================

document.addEventListener("DOMContentLoaded", () => {
    try {
        if (typeof faceapi === "undefined") {
            console.error("face-api.js no está cargado");
            document.getElementById("status").textContent =
                "Error: No se pudo cargar la biblioteca de reconocimiento facial";
            return;
        }

        window.faceCaptureApp = new FaceCaptureApp();
        console.log("Aplicación de captura facial inicializada");

        window.faceCaptureApp.initializeSystem();
    } catch (error) {
        console.error("Error al inicializar la aplicación:", error);
        document.getElementById(
            "status"
        ).textContent = `Error de inicialización: ${error.message}`;
    }
});
