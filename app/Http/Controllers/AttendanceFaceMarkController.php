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
        // Validación (dejar que Laravel maneje errores 422 de validación)
        $data = $request->validate([
            'face_descriptor' => ['required', new FaceDescriptor],
        ]);

        // Normalizar descriptor (acepta string JSON o array)
        try {
            $raw = $data['face_descriptor'];
            $live = is_string($raw)
                ? json_decode($raw, true, 512, JSON_THROW_ON_ERROR)
                : (is_array($raw) ? $raw : null);

            if (!is_array($live) || count($live) !== 128) {
                // Si por alguna razón la regla aceptó pero el contenido no es válido
                return response()->json([
                    'ok' => false,
                    'message' => 'El descriptor facial no tiene el formato esperado.',
                ], 422);
            }
        } catch (JsonException $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Descriptor facial inválido (JSON).',
            ], 422);
        }

        try {
            $threshold = (float) config('attendance.face_threshold', 0.6);
            [$employee, $distance] = $this->identifyEmployeeByDescriptor($live, $threshold);

            if (!$employee) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'No se pudo identificar el rostro.',
                ], 422);
            }

            $today = Carbon::now()->toDateString();

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
                    'first_name' => $employee->first_name,
                    'last_name' => $employee->last_name,
                    'ci' => $employee->ci ?? null,
                ],
                'distance' => $distance,
                'last_event' => $last?->event_type,
                'allowed_events' => $allowed ?? [],
            ]);
        } catch (Throwable $e) {
            Log::error('identify() error', [
                'msg' => $e->getMessage(),
                // Evita loggear datos sensibles; el trace puede ser útil en desarrollo
                'trace' => app()->hasDebugModeEnabled() ? $e->getTraceAsString() : null,
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Error interno al identificar. Revisa storage/logs/laravel.log',
            ], 500);
        }
    }

    /** 2) Registra la marcación (requiere ubicación obligatoria) */
    public function store(Request $request): JsonResponse
    {
        // Validación de datos
        $data = $request->validate([
            'employee_id'        => ['required', 'exists:employees,id'],
            'event_type'         => ['required', 'in:check_in,break_start,break_end,check_out'],
            'location.lat'       => ['required', 'numeric', 'between:-90,90'],
            'location.lng'       => ['required', 'numeric', 'between:-180,180'],
        ]);

        $employee = Employee::findOrFail($data['employee_id']);
        $today    = Carbon::now()->toDateString();

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
                    // Dejar que el controlador superior genere la respuesta acorde
                    throw ValidationException::withMessages([
                        'event_type' => "Evento no permitido. Último: '" . ($last->event_type ?? 'ninguno') . "'",
                    ]);
                }

                // Registrar el evento de asistencia
                $day->events()->create([
                    'event_type'  => $data['event_type'],
                    'recorded_at' => Carbon::now(),
                    'location'    => [
                        'lat' => (float) $data['location']['lat'],
                        'lng' => (float) $data['location']['lng'],
                    ],
                ]);

                return [
                    'employee' => $employee,
                    'event_type' => $data['event_type'],
                ];
            });

            return response()->json([
                'ok' => true,
                'message' => "Marcación registrada para {$result['employee']->first_name} ({$result['event_type']}).",
            ]);
        } catch (ValidationException $ve) {
            // Errores de negocio/reglas (422)
            return response()->json([
                'ok' => false,
                'message' => collect($ve->errors())->flatten()->first() ?? 'Datos inválidos.',
            ], 422);
        } catch (Throwable $e) {
            Log::error('store() error', [
                'msg' => $e->getMessage(),
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
        $candidates = Employee::query()
            ->whereNotNull('face_descriptor')
            ->select('id', 'first_name', 'last_name', 'ci', 'face_descriptor')
            ->get();

        $best = null;
        $bestDist = INF;

        foreach ($candidates as $emp) {
            // Si el modelo Employee tiene $casts['face_descriptor' => 'array'], esto ya será array
            $saved = is_array($emp->face_descriptor)
                ? $emp->face_descriptor
                : (is_string($emp->face_descriptor) ? json_decode($emp->face_descriptor, true) : null);

            if (!is_array($saved) || count($saved) !== 128) {
                continue;
            }

            $dist = $this->euclideanDistance($live, $saved);
            if ($dist < $bestDist) {
                $bestDist = $dist;
                $best = $emp;
            }
        }

        return ($best && $bestDist <= $threshold) ? [$best, $bestDist] : [null, $bestDist];
    }

    protected function euclideanDistance(array $a, array $b): float
    {
        $sum = 0.0;
        for ($i = 0; $i < 128; $i++) {
            $av = $a[$i] ?? 0.0;
            $bv = $b[$i] ?? 0.0;
            $d = $av - $bv;
            $sum += $d * $d;
        }
        return sqrt($sum);
    }

    /** Reglas de transición válidas */
    protected function allowedNextEvents(?string $last): array
    {
        if ($last === null) {
            return ['check_in'];
        }

        return match ($last) {
            'check_in'    => ['break_start', 'check_out'],
            'break_start' => ['break_end'],
            'break_end'   => ['break_start', 'check_out'],
            'check_out'   => [], // ya terminó el día
            default       => ['check_in'],
        };
    }
}
