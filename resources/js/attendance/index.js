import "./clock.js";
import { initializeApp } from "./init.js";
import { setupUI } from "./ui.js";

window.addEventListener("DOMContentLoaded", async () => {
    await initializeApp();
    setupUI();
});
