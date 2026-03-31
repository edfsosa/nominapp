{{-- Modal de diagnóstico para intentos fallidos de marcación --}}
@php
    $type = $record->failure_type;
    $meta = $record->metadata ?? [];

    $diagnoses = [
        'face_no_match' => [
            'icon'    => 'heroicon-o-face-frown',
            'color'   => 'danger',
            'cause'   => 'Ningún empleado enrolado superó el umbral de similitud facial.',
            'details' => isset($meta['best_distance'])
                ? "Mejor distancia obtenida: **{$meta['best_distance']}** (umbral máximo permitido: 0.45)."
                : null,
            'steps'   => [
                'Verificar que el empleado tenga un enrolamiento facial activo en el panel.',
                'Si el enrolamiento existe, revisar que la calidad sea **Alta (≥ 0.85)**. Si es Media o Baja, re-enrolar.',
                'Al re-enrolar: buena iluminación frontal, sin lentes, sin gorra, fondo neutro.',
                'Si el problema persiste en varios empleados, revisar la cámara y la iluminación del lugar.',
            ],
        ],

        'face_ambiguous' => [
            'icon'    => 'heroicon-o-question-mark-circle',
            'color'   => 'warning',
            'cause'   => 'El sistema encontró dos o más empleados con descriptores faciales muy similares — no pudo decidir con certeza.',
            'details' => isset($meta['best_distance'], $meta['second_best'])
                ? "Distancia al primero: **{$meta['best_distance']}** / al segundo: **{$meta['second_best']}** (diferencia insuficiente)."
                : null,
            'steps'   => [
                'Re-enrolar a los empleados involucrados asegurando calidad **Alta (≥ 0.85)** en cada uno.',
                'Al re-enrolar: capturar desde distintos ángulos leves (±15°) para mejorar la unicidad del descriptor.',
                'Si los empleados son físicamente muy similares (ej. hermanos), aumentar el número de muestras en el enrolamiento.',
            ],
        ],

        'face_no_candidates' => [
            'icon'    => 'heroicon-o-users',
            'color'   => 'danger',
            'cause'   => 'No hay empleados activos con descriptor facial registrado en el sistema.',
            'details' => null,
            'steps'   => [
                'Ir a **Empleados → [Empleado] → Enrolamiento Facial** y completar el proceso de captura.',
                'Asegurarse de que el empleado tenga estado **Activo** y el enrolamiento esté **Aprobado**.',
                'Si la sucursal tiene muchos empleados sin enrolar, usar la exportación masiva de links de enrolamiento.',
            ],
        ],

        'employee_not_found' => [
            'icon'    => 'heroicon-o-user-minus',
            'color'   => 'danger',
            'cause'   => 'El ID de empleado enviado no existe o el empleado está inactivo.',
            'details' => isset($meta['employee_id'])
                ? "ID intentado: **{$meta['employee_id']}**."
                : null,
            'steps'   => [
                'Verificar que el empleado exista en el sistema y tenga estado **Activo**.',
                'Si fue dado de baja recientemente, el enrolamiento puede haber quedado cacheado. Limpiar caché: `php artisan cache:clear`.',
            ],
        ],

        'employee_no_branch' => [
            'icon'    => 'heroicon-o-building-storefront',
            'color'   => 'warning',
            'cause'   => 'El empleado no tiene una sucursal asignada, por lo que no se puede determinar la ubicación desde terminal.',
            'details' => null,
            'steps'   => [
                'Ir a **Empleados → [Empleado] → Editar** y asignar una sucursal.',
                'Si el empleado es remoto y marca desde móvil, este error no debería ocurrir — revisar que el frontend envíe `source: mobile` con coordenadas GPS.',
            ],
        ],

        'branch_no_coordinates' => [
            'icon'    => 'heroicon-o-map-pin',
            'color'   => 'warning',
            'cause'   => 'La sucursal del empleado no tiene coordenadas GPS configuradas.',
            'details' => isset($meta['branch_name'])
                ? "Sucursal afectada: **{$meta['branch_name']}**."
                : null,
            'steps'   => [
                'Ir a **Sucursales → [Sucursal] → Editar** y configurar las coordenadas en el mapa.',
                'Arrastrar el marcador hasta la ubicación exacta del local o usar la búsqueda por dirección.',
                'Guardar y volver a intentar la marcación.',
            ],
        ],

        'invalid_event_sequence' => [
            'icon'    => 'heroicon-o-arrow-path',
            'color'   => 'warning',
            'cause'   => 'El empleado intentó registrar un evento que no corresponde al estado actual de su jornada.',
            'details' => collect([
                isset($meta['last_event'])      ? "Último evento registrado: **{$meta['last_event']}**." : null,
                isset($meta['attempted_event']) ? "Evento intentado: **{$meta['attempted_event']}**."    : null,
                isset($meta['allowed_events'])  ? "Eventos permitidos en ese momento: **" . implode(', ', (array) $meta['allowed_events']) . "**." : null,
            ])->filter()->implode(' '),
            'steps'   => [
                'Si el empleado olvidó marcar un evento previo (ej. entrada), crearlo manualmente en **Asistencias → [Día] → Eventos**.',
                'Sequence válida: Entrada → Inicio descanso → Fin descanso → Salida.',
                'Si el problema se repite frecuentemente, capacitar al empleado sobre el orden correcto de marcación.',
            ],
        ],

        'invalid_location' => [
            'icon'    => 'heroicon-o-map-pin',
            'color'   => 'danger',
            'cause'   => 'La ubicación GPS recibida es (0, 0), lo que indica que el dispositivo no pudo obtener coordenadas válidas.',
            'details' => null,
            'steps'   => [
                'El empleado debe activar el GPS del dispositivo y otorgar permisos de ubicación al navegador.',
                'Intentar en una zona con mejor señal GPS o cobertura de red (el GPS asistido usa datos móviles).',
                'Si ocurre en modo terminal, revisar que la sucursal tenga coordenadas configuradas correctamente.',
            ],
        ],

        'internal_error' => [
            'icon'    => 'heroicon-o-bug-ant',
            'color'   => 'danger',
            'cause'   => 'Error interno del servidor durante el procesamiento de la marcación.',
            'details' => null,
            'steps'   => [
                'Revisar `storage/logs/laravel.log` en el timestamp indicado para ver el stack trace completo.',
                'Si el error es recurrente, reportar al equipo de desarrollo con la fecha/hora y el ID de este registro.',
            ],
        ],
    ];

    $info = $diagnoses[$type] ?? [
        'icon'    => 'heroicon-o-exclamation-triangle',
        'color'   => 'gray',
        'cause'   => 'Tipo de fallo no reconocido.',
        'details' => null,
        'steps'   => ['Revisar el mensaje de error y los metadatos en el detalle del registro.'],
    ];
@endphp

<div class="space-y-4 py-2">

    {{-- Causa --}}
    <div class="rounded-lg border border-{{ $info['color'] }}-200 bg-{{ $info['color'] }}-50 p-4 dark:border-{{ $info['color'] }}-800 dark:bg-{{ $info['color'] }}-950">
        <p class="text-sm font-medium text-{{ $info['color'] }}-800 dark:text-{{ $info['color'] }}-200">
            ¿Qué ocurrió?
        </p>
        <p class="mt-1 text-sm text-{{ $info['color'] }}-700 dark:text-{{ $info['color'] }}-300">
            {{ $info['cause'] }}
        </p>
        @if($info['details'])
            <p class="mt-2 text-sm text-{{ $info['color'] }}-700 dark:text-{{ $info['color'] }}-300">
                {!! str($info['details'])->markdown()->toHtmlString() !!}
            </p>
        @endif
    </div>

    {{-- Pasos sugeridos --}}
    <div>
        <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Pasos sugeridos:</p>
        <ol class="space-y-2">
            @foreach($info['steps'] as $i => $step)
                <li class="flex gap-3 text-sm text-gray-600 dark:text-gray-400">
                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-primary-100 text-xs font-bold text-primary-700 dark:bg-primary-900 dark:text-primary-300">
                        {{ $i + 1 }}
                    </span>
                    <span>{!! str($step)->markdown()->toHtmlString() !!}</span>
                </li>
            @endforeach
        </ol>
    </div>

    {{-- Datos del registro --}}
    <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-900">
        <p class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Datos del intento</p>
        <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs text-gray-600 dark:text-gray-300">
            <span class="font-medium">Fecha/hora:</span>
            <span>{{ $record->occurred_at->format('d/m/Y H:i:s') }}</span>

            <span class="font-medium">Modo:</span>
            <span>{{ \App\Models\AttendanceMarkFailure::getModeLabel($record->mode) }}</span>

            @if($record->employee)
                <span class="font-medium">Empleado:</span>
                <span>{{ $record->employee->first_name }} {{ $record->employee->last_name }}</span>
            @endif

            @if($record->branch)
                <span class="font-medium">Sucursal:</span>
                <span>{{ $record->branch->name }}</span>
            @endif

            @if($record->ip_address)
                <span class="font-medium">IP:</span>
                <span>{{ $record->ip_address }}</span>
            @endif
        </div>
    </div>

</div>
