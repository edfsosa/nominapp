import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/attendances/mark.js',
                'resources/css/attendances/styles.css',
                'resources/js/attendances/terminal.js',
                'resources/css/attendances/terminal.css',
                'resources/js/employees/capture-face.js',
                'resources/css/employees/capture-face.css',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
