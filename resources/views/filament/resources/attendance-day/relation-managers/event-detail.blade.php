<div class="space-y-4">
    @php
        $eventConfig = match ($record->event_type) {
            'check_in' => ['icon' => '→', 'color' => 'success', 'label' => 'Entrada jornada'],
            'break_start' => ['icon' => '⏸', 'color' => 'warning', 'label' => 'Inicio descanso'],
            'break_end' => ['icon' => '▶', 'color' => 'info', 'label' => 'Fin descanso'],
            'check_out' => ['icon' => '←', 'color' => 'danger', 'label' => 'Salida jornada'],
            default => ['icon' => '?', 'color' => 'gray', 'label' => 'Desconocido'],
        };

        $badgeClass = match ($eventConfig['color']) {
            'success' => 'bg-green-100 dark:bg-green-900/20 text-green-700 dark:text-green-300',
            'danger' => 'bg-red-100 dark:bg-red-900/20 text-red-700 dark:text-red-300',
            'warning' => 'bg-yellow-100 dark:bg-yellow-900/20 text-yellow-700 dark:text-yellow-300',
            'info' => 'bg-blue-100 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300',
            default => 'bg-gray-100 dark:bg-gray-900/20 text-gray-700 dark:text-gray-300',
        };

        $location = is_string($record->location) ? json_decode($record->location, true) : $record->location;
        $lat = $location['lat'] ?? null;
        $lng = $location['lng'] ?? null;
        $hasLocation = $lat && $lng;
    @endphp

    {{-- Header minimalista --}}
    <div class="flex items-center justify-between pb-3 border-b border-gray-200 dark:border-gray-700">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-lg {{ $badgeClass }} text-sm font-medium">
                <span>{{ $eventConfig['icon'] }}</span>
                <span>{{ $eventConfig['label'] }}</span>
            </div>
        </div>
        <div class="text-right">
            <p class="text-3xl font-bold text-gray-900 dark:text-white">
                {{ $record->recorded_at->format('H:i') }}
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ $record->recorded_at->format('d/m/Y') }}
            </p>
        </div>
    </div>

    {{-- Card único con información principal --}}
    <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 space-y-3">
        {{-- Marcación realizada --}}
        <div class="flex items-start gap-3">
            <div class="flex-shrink-0 w-8 h-8 flex items-center justify-center">
                <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                    Marcación realizada
                </p>
                <p class="text-sm font-medium text-gray-900 dark:text-white">
                    {{ $record->recorded_at->format('d/m/Y H:i') }}
                    <span
                        class="text-xs text-gray-500 dark:text-gray-400">({{ $record->recorded_at->diffForHumans() }})</span>
                </p>
            </div>
        </div>

        {{-- Ubicación --}}
        <div class="flex items-start gap-3 pt-3 border-t border-gray-200 dark:border-gray-700">
            <div class="flex-shrink-0 w-8 h-8 flex items-center justify-center">
                <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                    Ubicación
                </p>
                @if ($hasLocation)
                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                        {{ number_format($lat, 6) }}, {{ number_format($lng, 6) }}
                    </p>
                    <a href="https://www.google.com/maps?q={{ $lat }},{{ $lng }}" target="_blank"
                        class="inline-flex items-center gap-1 mt-1 text-xs text-blue-600 dark:text-blue-400 hover:underline">
                        Ver en Google Maps
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                        </svg>
                    </a>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400 italic">
                        Sin datos de ubicación
                    </p>
                @endif
            </div>
        </div>
    </div>

    {{-- Mapa embebido compacto --}}
    @if ($hasLocation)
        <div class="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
            <iframe width="100%" height="200" frameborder="0" style="border:0"
                referrerpolicy="no-referrer-when-downgrade"
                src="https://www.google.com/maps/embed/v1/place?key=AIzaSyDPk5A7zaM10UXR-Au5SAsgUEm9adFlPtU&q={{ $lat }},{{ $lng }}&zoom=16"
                allowfullscreen>
            </iframe>
        </div>
    @endif
</div>
