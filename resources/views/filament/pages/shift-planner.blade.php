<x-filament-panels::page>

    {{-- Contenedor de la aplicación Vue del planificador --}}
    <div
        id="shift-planner-app"
        data-init="{{ json_encode($initData) }}"
        style="min-height: 400px;"
    ></div>

    @vite(['resources/css/planner/planner.css', 'resources/js/planner/planner.js'])

</x-filament-panels::page>
