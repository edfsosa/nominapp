<div class="space-y-3">
    @php
        $eventConfig = match ($record->event_type) {
            'check_in'    => ['color' => 'success', 'label' => 'Entrada jornada'],
            'break_start' => ['color' => 'warning', 'label' => 'Inicio descanso'],
            'break_end'   => ['color' => 'info',    'label' => 'Fin descanso'],
            'check_out'   => ['color' => 'danger',  'label' => 'Salida jornada'],
            default       => ['color' => 'gray',    'label' => 'Desconocido'],
        };

        $badgeClass = match ($eventConfig['color']) {
            'success' => 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 ring-1 ring-green-200 dark:ring-green-800',
            'danger'  => 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 ring-1 ring-red-200 dark:ring-red-800',
            'warning' => 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-300 ring-1 ring-yellow-200 dark:ring-yellow-800',
            'info'    => 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 ring-1 ring-blue-200 dark:ring-blue-800',
            default   => 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 ring-1 ring-gray-200 dark:ring-gray-700',
        };

        $location    = is_string($record->location) ? json_decode($record->location, true) : $record->location;
        $lat         = $location['lat'] ?? null;
        $lng         = $location['lng'] ?? null;
        $hasLocation = $lat && $lng;
        $mapsUrl     = $hasLocation ? "https://www.google.com/maps?q={$lat},{$lng}" : null;
    @endphp

    {{-- Header: tipo + hora --}}
    <div class="flex items-start justify-between pb-3 border-b border-gray-200 dark:border-gray-700">
        <span class="inline-flex items-center px-2.5 py-1 rounded-md {{ $badgeClass }} text-xs font-semibold tracking-wide uppercase">
            {{ $eventConfig['label'] }}
        </span>
        <div class="text-right leading-none">
            <p class="text-4xl font-bold tabular-nums text-gray-900 dark:text-white">
                {{ $record->recorded_at->format('H:i') }}
            </p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                {{ $record->recorded_at->translatedFormat('l d/m/Y') }}
            </p>
            <p class="text-xs text-gray-400 dark:text-gray-500">
                {{ $record->recorded_at->diffForHumans() }}
            </p>
        </div>
    </div>

    {{-- Empleado y sucursal --}}
    <div class="rounded-lg bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700 divide-y divide-gray-200 dark:divide-gray-700">
        @if ($record->employee_name)
            <div class="flex items-center justify-between px-4 py-2.5">
                <span class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wide">Empleado</span>
                <div class="text-right">
                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $record->employee_name }}</span>
                    @if ($record->employee_ci)
                        <span class="ml-2 text-xs text-gray-400 dark:text-gray-500">CI {{ $record->employee_ci }}</span>
                    @endif
                </div>
            </div>
        @endif

        @if ($record->branch_name)
            <div class="flex items-center justify-between px-4 py-2.5">
                <span class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wide">Sucursal</span>
                <span class="text-sm text-gray-700 dark:text-gray-300">{{ $record->branch_name }}</span>
            </div>
        @endif
    </div>

    {{-- Ubicación / Mapa --}}
    @if ($hasLocation)
        <div class="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
            <iframe
                width="100%"
                height="220"
                frameborder="0"
                style="border:0; display:block;"
                referrerpolicy="no-referrer-when-downgrade"
                src="https://www.google.com/maps/embed/v1/place?key={{ config('services.google.maps_key') }}&q={{ $lat }},{{ $lng }}&zoom=16"
                allowfullscreen>
            </iframe>
        </div>
        <a href="{{ $mapsUrl }}" target="_blank"
            class="inline-flex items-center gap-1.5 text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline">
            Ver en Google Maps
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
            </svg>
        </a>
    @else
        <div class="flex items-center gap-2 px-4 py-3 rounded-lg bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700">
            <svg class="w-4 h-4 text-gray-300 dark:text-gray-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
            </svg>
            <span class="text-xs text-gray-400 dark:text-gray-500 italic">Sin datos de ubicación</span>
        </div>
    @endif
</div>
