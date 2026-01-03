<?php

namespace App\Http\Controllers;

use App\Models\AttendanceDay;
use App\Models\AttendanceEvent;
use App\Models\Employee;
use App\Rules\FaceDescriptor;
use Carbon\Carbon;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use JsonException;
use Throwable;

class AttendanceFaceMarkController extends Controller
{
    /** Muestra la página de marcación facial */
    public function show(): ViewContract
    {
        return view('attendances.mark', ['title' => 'Marcación Facial']);
    }

    /** 1) Identifica al empleado por descriptor y devuelve data + eventos permitidos */
    public function identify(Request $request): JsonResponse
    {
        try {
            // CORRECCIÓN 1: Mover validación dentro del try-catch para capturar ValidationException
            $data = $request->validate([
                'face_descriptor' => ['required', new FaceDescriptor],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Datos de entrada inválidos.',
                'errors' => $e->errors(),
            ], 422);
        }

        // Normalizar descriptor (acepta string JSON o array)
        try {
            $raw = $data['face_descriptor'];
            $live = is_string($raw)
                ? json_decode($raw, true, 512, JSON_THROW_ON_ERROR)
                : (is_array($raw) ? $raw : null);

            if (!is_array($live) || count($live) !== 128) {
                // CORRECCIÓN 2: Verificar que todos los elementos sean numéricos
                return response()->json([
                    'ok' => false,
                    'message' => 'El descriptor facial no tiene el formato esperado (debe ser array de 128 números).',
                ], 422);
            }

            // CORRECCIÓN 3: Validar que todos los elementos del descriptor sean numéricos
            foreach ($live as $value) {
                if (!is_numeric($value)) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'El descriptor facial debe contener solo valores numéricos.',
                    ], 422);
                }
            }
        } catch (JsonException $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Descriptor facial inválido (JSON malformado).',
            ], 422);
        }

        try {
            // CORRECCIÓN 4: Validar que el threshold sea válido
            $threshold = (float) config('attendance.face_threshold', 0.6);
            if ($threshold <= 0 || $threshold > 2.0) {
                Log::warning('Invalid face threshold in config', ['threshold' => $threshold]);
                $threshold = 0.6; // valor por defecto seguro
            }

            [$employee, $distance] = $this->identifyEmployeeByDescriptor($live, $threshold);

            if (!$employee) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'No se pudo identificar el rostro. Intente nuevamente.',
                ], 422);
            }

            // CORRECCIÓN 5: Usar timezone de la aplicación
            $today = Carbon::now(config('app.timezone'))->toDateString();

            $day = AttendanceDay::where('employee_id', $employee->id)
                ->where('date', $today)
                ->first();

            $last = null;
            if ($day) {
                $last = AttendanceEvent::where('attendance_day_id', $day->id)
                    ->latest('recorded_at')
                    ->first();
            }

            $allowed = $this->allowedNextEvents($last?->event_type);

            return response()->json([
                'ok' => true,
                'employee' => [
                    'id' => $employee->id,
                    'first_name' => $employee->first_name ?? '',
                    'last_name' => $employee->last_name ?? '',
                    'ci' => $employee->ci ?? null,
                ],
                'distance' => round($distance, 4),
                'last_event' => $last?->event_type,
                'allowed_events' => $allowed,
            ]);
        } catch (Throwable $e) {
            Log::error('identify() error', [
                'msg' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => app()->hasDebugModeEnabled() ? $e->getTraceAsString() : null,
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Error interno al identificar empleado.',
            ], 500);
        }
    }

    /** 2) Registra la marcación (requiere ubicación obligatoria) */
    public function store(Request $request): JsonResponse
    {
        try {
            // CORRECCIÓN 6: Validación más robusta con mensajes personalizados
            $data = $request->validate([
                'employee_id'        => ['required', 'integer', 'exists:employees,id'],
                'event_type'         => ['required', 'string', 'in:check_in,break_start,break_end,check_out'],
                'location'           => ['required', 'array'],
                'location.lat'       => ['required', 'numeric', 'between:-90,90'],
                'location.lng'       => ['required', 'numeric', 'between:-180,180'],
            ], [
                'employee_id.required' => 'ID de empleado es requerido.',
                'employee_id.exists' => 'El empleado no existe.',
                'event_type.required' => 'Tipo de evento es requerido.',
                'event_type.in' => 'Tipo de evento inválido.',
                'location.required' => 'La ubicación es requerida.',
                'location.lat.between' => 'Latitud debe estar entre -90 y 90.',
                'location.lng.between' => 'Longitud debe estar entre -180 y 180.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Datos de entrada inválidos.',
                'errors' => $e->errors(),
            ], 422);
        }

        try {
            // CORRECCIÓN 7: Verificar que el empleado esté activo
            $employee = Employee::where('id', $data['employee_id'])
                ->where('status', 'active')
                ->firstOrFail();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Empleado no encontrado o inactivo.',
            ], 422);
        }

        // CORRECCIÓN 8: Usar timezone de la aplicación
        $today = Carbon::now(config('app.timezone'))->toDateString();

        try {
            $result = DB::transaction(function () use ($employee, $today, $data) {
                // Asegurar AttendanceDay del día (firstOrCreate es atómico)
                $day = AttendanceDay::firstOrCreate(
                    ['employee_id' => $employee->id, 'date' => $today],
                    ['status' => 'present']
                );

                // Si existe y el status no es 'present', actualizarlo
                if ($day->status !== 'present') {
                    $day->update(['status' => 'present']);
                }

                // Bloquear lectura del último evento para evitar condiciones de carrera
                $last = AttendanceEvent::where('attendance_day_id', $day->id)
                    ->lockForUpdate()
                    ->latest('recorded_at')
                    ->first();

                $allowed = $this->allowedNextEvents($last?->event_type);

                if (!in_array($data['event_type'], $allowed, true)) {
                    // CORRECCIÓN 9: Mensaje más descriptivo del error
                    $currentState = $last?->event_type ?? 'ninguno';
                    $allowedStr = empty($allowed) ? 'ninguno' : implode(', ', $allowed);

                    throw ValidationException::withMessages([
                        'event_type' => "Evento '{$data['event_type']}' no permitido. Estado actual: '{$currentState}'. Eventos permitidos: {$allowedStr}",
                    ]);
                }

                // CORRECCIÓN 10: Validar que la ubicación no sea 0,0 (ubicación inválida común)
                $lat = (float) $data['location']['lat'];
                $lng = (float) $data['location']['lng'];

                if ($lat === 0.0 && $lng === 0.0) {
                    throw ValidationException::withMessages([
                        'location' => 'La ubicación proporcionada no es válida.',
                    ]);
                }

                // CORRECCIÓN 11: Usar timezone de la aplicación para recorded_at
                $recordedAt = Carbon::now(config('app.timezone'));

                // Registrar el evento de asistencia
                $event = $day->events()->create([
                    'event_type'  => $data['event_type'],
                    'recorded_at' => $recordedAt,
                    'location'    => [
                        'lat' => $lat,
                        'lng' => $lng,
                    ],
                ]);

                // CORRECCIÓN 12: Verificar que el evento se creó correctamente
                if (!$event || !$event->id) {
                    throw new \Exception('No se pudo crear el evento de asistencia.');
                }

                return [
                    'employee' => $employee,
                    'event_type' => $data['event_type'],
                    'event_id' => $event->id,
                    'recorded_at' => $recordedAt->format('Y-m-d H:i:s'),
                ];
            });

            // CORRECCIÓN 13: Respuesta más informativa
            $eventTypes = [
                'check_in' => 'Ingreso',
                'break_start' => 'Inicio de descanso',
                'break_end' => 'Fin de descanso',
                'check_out' => 'Salida',
            ];

            $eventName = $eventTypes[$result['event_type']] ?? $result['event_type'];

            return response()->json([
                'ok' => true,
                'message' => "Marcación registrada correctamente: {$eventName} para {$result['employee']->first_name} {$result['employee']->last_name}",
                'data' => [
                    'event_id' => $result['event_id'],
                    'event_type' => $result['event_type'],
                    'recorded_at' => $result['recorded_at'],
                ],
            ]);
        } catch (ValidationException $ve) {
            // Errores de negocio/reglas (422)
            return response()->json([
                'ok' => false,
                'message' => collect($ve->errors())->flatten()->first() ?? 'Datos inválidos.',
                'errors' => $ve->errors(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('store() error', [
                'msg' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'employee_id' => $data['employee_id'] ?? null,
                'event_type' => $data['event_type'] ?? null,
                'trace' => app()->hasDebugModeEnabled() ? $e->getTraceAsString() : null,
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Error interno al registrar la marcación.',
            ], 500);
        }
    }

    /** Identifica empleado por distancia euclidiana */
    protected function identifyEmployeeByDescriptor(array $live, float $threshold = 0.6): array
    {
        try {
            // CORRECCIÓN 14: Agregar filtro de empleados activos y mejorar la consulta
            $candidates = Employee::query()
                ->whereNotNull('face_descriptor')
                ->where('status', 'active')
                ->select('id', 'first_name', 'last_name', 'ci', 'face_descriptor')
                ->get();

            if ($candidates->isEmpty()) {
                Log::warning('No hay empleados con descriptores faciales activos.');
                return [null, INF];
            }

            $best = null;
            $bestDist = INF;
            $processedCount = 0;

            foreach ($candidates as $emp) {
                // CORRECCIÓN 15: Mejor manejo del descriptor guardado
                $saved = $this->parseStoredDescriptor($emp->face_descriptor);

                if (!$saved) {
                    Log::warning("Descriptor facial inválido para empleado ID: {$emp->id}");
                    continue;
                }

                $dist = $this->euclideanDistance($live, $saved);
                if ($dist < $bestDist) {
                    $bestDist = $dist;
                    $best = $emp;
                }
                $processedCount++;
            }

            Log::info("Procesados {$processedCount} empleados para identificación facial", [
                'best_distance' => $bestDist,
                'threshold' => $threshold,
                'identified' => $best ? "ID: {$best->id}" : 'ninguno',
            ]);

            return ($best && $bestDist <= $threshold) ? [$best, $bestDist] : [null, $bestDist];
        } catch (Throwable $e) {
            Log::error('Error en identifyEmployeeByDescriptor', [
                'msg' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return [null, INF];
        }
    }

    // CORRECCIÓN 16: Nueva función para parsear descriptores guardados
    protected function parseStoredDescriptor($descriptor): ?array
    {
        if (is_array($descriptor)) {
            return (count($descriptor) === 128) ? $descriptor : null;
        }

        if (is_string($descriptor)) {
            try {
                $parsed = json_decode($descriptor, true, 512, JSON_THROW_ON_ERROR);
                return (is_array($parsed) && count($parsed) === 128) ? $parsed : null;
            } catch (JsonException $e) {
                return null;
            }
        }

        return null;
    }

    protected function euclideanDistance(array $a, array $b): float
    {
        // CORRECCIÓN 17: Verificar que ambos arrays tengan 128 elementos
        if (count($a) !== 128 || count($b) !== 128) {
            throw new \InvalidArgumentException('Los descriptores deben tener exactamente 128 elementos.');
        }

        $sum = 0.0;
        for ($i = 0; $i < 128; $i++) {
            $av = (float) ($a[$i] ?? 0.0);
            $bv = (float) ($b[$i] ?? 0.0);

            // CORRECCIÓN 18: Verificar valores numéricos válidos
            if (!is_finite($av) || !is_finite($bv)) {
                throw new \InvalidArgumentException('Los descriptores contienen valores no finitos.');
            }

            $d = $av - $bv;
            $sum += $d * $d;
        }

        $distance = sqrt($sum);

        // CORRECCIÓN 19: Verificar que el resultado sea finito
        if (!is_finite($distance)) {
            throw new \RuntimeException('La distancia euclidiana resultó en un valor no finito.');
        }

        return $distance;
    }

    /** Reglas de transición válidas */
    protected function allowedNextEvents(?string $last): array
    {
        // CORRECCIÓN 20: Validar tipos de eventos conocidos
        $validEventTypes = ['check_in', 'break_start', 'break_end', 'check_out'];

        if ($last !== null && !in_array($last, $validEventTypes, true)) {
            Log::warning("Tipo de evento desconocido encontrado: {$last}");
            return ['check_in']; // Fallback seguro
        }

        if ($last === null) {
            return ['check_in'];
        }

        return match ($last) {
            'check_in'    => ['break_start', 'check_out'],
            'break_start' => ['break_end'],
            'break_end'   => ['break_start', 'check_out'],
            'check_out'   => [], // ya terminó el día
            default       => ['check_in'], // fallback seguro
        };
    }
}
