@php
    $location    = $getRecord()->location;
    $lat         = $location['lat'] ?? null;
    $lng         = $location['lng'] ?? null;
    $hasLocation = $lat && $lng;
@endphp

@if ($hasLocation)
    {{-- Mapa embebido --}}
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

    {{-- Link a Google Maps --}}
    <a href="https://www.google.com/maps?q={{ $lat }},{{ $lng }}" target="_blank"
        class="inline-flex items-center gap-1.5 mt-2 text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline">
        Ver en Google Maps
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
        </svg>
    </a>
@else
    {{-- Sin ubicación --}}
    <span class="text-xs text-gray-400 dark:text-gray-500 italic">Sin datos de ubicación</span>
@endif
