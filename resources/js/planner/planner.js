/**
 * Planificador Visual de Turnos Rotativos
 * Entry point de Vue 3 para la página /admin/planificador.
 */

import { createApp } from 'vue';
import ShiftPlanner from './ShiftPlanner.vue';

const container = document.getElementById('shift-planner-app');

if (container) {
    const initData = JSON.parse(container.dataset.init);

    createApp(ShiftPlanner, { initData })
        .mount(container);
}
