document.addEventListener("DOMContentLoaded", () => {
    const clockEl = document.getElementById("clock");
    if (!clockEl) return;

    function updateClock() {
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

    updateClock();
    setInterval(updateClock, 1000);
});
