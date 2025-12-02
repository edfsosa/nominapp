<div class="space-y-6">
    {{-- Header con badge del tipo de evento --}}
    <div class="flex items-center justify-between pb-4 border-b border-gray-200 dark:border-gray-700">
        <div class="flex items-center gap-3">
            @php
                $eventConfig = match ($record->event_type) {
                    'check_in' => ['icon' => '→', 'color' => 'green', 'label' => 'Entrada jornada'],
                    'break_start' => ['icon' => '⏸', 'color' => 'yellow', 'label' => 'Inicio descanso'],
                    'break_end' => ['icon' => '▶', 'color' => 'blue', 'label' => 'Fin descanso'],
                    'check_out' => ['icon' => '←', 'color' => 'red', 'label' => 'Salida jornada'],
                    default => ['icon' => '?', 'color' => 'gray', 'label' => 'Desconocido'],
                };
            @endphp

            <div
                class="flex items-center justify-center w-12 h-12 rounded-full bg-{{ $eventConfig['color'] }}-100 dark:bg-{{ $eventConfig['color'] }}-900/20">
                <span class="text-2xl">{{ $eventConfig['icon'] }}</span>
            </div>

            <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ $eventConfig['label'] }}
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $record->recorded_at->format('l, d \d\e F \d\e Y') }}
                </p>
            </div>
        </div>

        <div class="text-right">
            <p class="text-3xl font-bold text-gray-900 dark:text-white">
                {{ $record->recorded_at->format('H:i') }}
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ $record->recorded_at->format('s') }} segundos
            </p>
        </div>
    </div>

    {{-- Información principal en cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        {{-- Card: Fecha y Hora completa --}}
        <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
            <div class="flex items-start gap-3">
                <div
                    class="flex-shrink-0 w-10 h-10 flex items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/20">
                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                        Hora de marcación
                    </p>
                    <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                        {{ $record->recorded_at->format('H:i:s') }}
                    </p>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        {{ $record->recorded_at->format('d/m/Y') }}
                    </p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ $record->recorded_at->diffForHumans() }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Card: Ubicación --}}
        @php
            $location = is_string($record->location) ? json_decode($record->location, true) : $record->location;
            $lat = $location['lat'] ?? null;
            $lng = $location['lng'] ?? null;
            $hasLocation = $lat && $lng;
        @endphp

        <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
            <div class="flex items-start gap-3">
                <div
                    class="flex-shrink-0 w-10 h-10 flex items-center justify-center rounded-lg bg-green-100 dark:bg-green-900/20">
                    <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                        Geolocalización
                    </p>
                    @if ($hasLocation)
                        <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                            📍 {{ number_format($lat, 6) }}, {{ number_format($lng, 6) }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Latitud: {{ number_format($lat, 6) }}<br>
                            Longitud: {{ number_format($lng, 6) }}
                        </p>
                    @else
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 italic">
                            Sin datos de ubicación
                        </p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Card: Registrado en sistema --}}
        <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
            <div class="flex items-start gap-3">
                <div
                    class="flex-shrink-0 w-10 h-10 flex items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-900/20">
                    <svg class="w-5 h-5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                        Registrado en sistema
                    </p>
                    <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                        {{ $record->created_at->format('d/m/Y H:i:s') }}
                    </p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ $record->created_at->diffForHumans() }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Card: Diferencia de tiempo --}}
        @php
            $timeDiff = $record->recorded_at->diffInSeconds($record->created_at);
        @endphp
        <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
            <div class="flex items-start gap-3">
                <div
                    class="flex-shrink-0 w-10 h-10 flex items-center justify-center rounded-lg bg-orange-100 dark:bg-orange-900/20">
                    <svg class="w-5 h-5 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                        Sincronización
                    </p>
                    <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                        @if ($timeDiff < 5)
                            ⚡ Instantáneo
                        @elseif($timeDiff < 60)
                            ✓ {{ $timeDiff }} segundos
                        @else
                            ⚠️ {{ round($timeDiff / 60, 1) }} minutos
                        @endif
                    </p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Diferencia entre marcación y registro
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Botón de acción para el mapa --}}
    @if ($hasLocation)
        <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
            <a href="https://www.google.com/maps?q={{ $lat }},{{ $lng }}" target="_blank"
                class="inline-flex items-center justify-center w-full px-4 py-3 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white text-sm font-medium rounded-lg shadow-sm transition-all duration-200 hover:shadow-md">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                Abrir ubicación en Google Maps
                <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                </svg>
            </a>
        </div>

        {{-- Mapa embebido (opcional) --}}
        <div class="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
            <iframe width="100%" height="300" frameborder="0" style="border:0"
                referrerpolicy="no-referrer-when-downgrade"
                src="https://www.google.com/maps/embed/v1/place?key=AIzaSyDPk5A7zaM10UXR-Au5SAsgUEm9adFlPtU&q={{ $lat }},{{ $lng }}&zoom=16"
                allowfullscreen>
            </iframe>
            <p class="px-4 py-2 text-xs text-center text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-800">
                Ubicación aproximada de la marcación
            </p>
        </div>
    @else
        <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
            <div
                class="flex items-center justify-center p-6 rounded-lg bg-gray-50 dark:bg-gray-800 border-2 border-dashed border-gray-300 dark:border-gray-600">
                <div class="text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                    </svg>
                    <p class="mt-2 text-sm font-medium text-gray-900 dark:text-white">
                        Sin datos de ubicación
                    </p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Esta marcación no tiene información de geolocalización
                    </p>
                </div>
            </div>
        </div>
    @endif
</div>
