<?php

namespace App\Http\Controllers;

use App\Models\AttendanceDay;
use App\Models\AttendanceEvent;
use App\Models\Branch;
use App\Models\Employee;
use App\Rules\FaceDescriptor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class AttendanceFaceMarkController extends Controller
{
    /** Muestra la página de marcación facial */
    public function show()
    {
        return view('attendance.mark');
    }

    /** 1) Identifica al empleado por descriptor y devuelve data + eventos permitidos */
    public function identify(Request $request)
    {
        try{
            $data = $request->validate([
                'face_descriptor' => ['required', new FaceDescriptor],
            ]);
    
            $live = is_string($data['face_descriptor'])
                ? json_decode($data['face_descriptor'], true)
                : $data['face_descriptor'];
    
            [$employee, $distance] = $this->identifyEmployeeByDescriptor($live, 0.60);
    
            if (! $employee) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'No se pudo identificar el rostro (sin coincidencias bajo el umbral).',
                ], 422);
            }
    
            $today = Carbon::now()->toDateString();
            $day   = AttendanceDay::where('employee_id', $employee->id)->where('date', $today)->first();
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
                'allowed_events' => $allowed,   // ej.: ['check_in'] o ['break_start','check_out']
            ]);
        }
        catch (Throwable $e) {
            Log::error('identify() error', ['msg' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'ok' => false,
                'message' => 'Error interno al identificar. Revisa storage/logs/laravel.log',
            ], 500);
        }
    }

    /** 2) Registra la marcación (requiere ubicación obligatoria) */
    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id'        => ['required', 'exists:employees,id'],
            'event_type'         => ['required', 'in:check_in,break_start,break_end,check_out'],
            'branch_id'          => ['nullable', 'integer'],
            'location.lat'       => ['required', 'numeric'],
            'location.lng'       => ['required', 'numeric'],
            'location.accuracy'  => ['nullable', 'numeric'],
        ]);

        $employee = Employee::findOrFail($data['employee_id']);
        $today    = Carbon::now()->toDateString();

        $day = AttendanceDay::firstOrCreate(
            ['employee_id' => $employee->id, 'date' => $today],
            ['status' => 'present']
        );

        $last    = $day->events()->latest('recorded_at')->first();
        $allowed = $this->allowedNextEvents($last?->event_type);

        if (! in_array($data['event_type'], $allowed, true)) {
            return response()->json([
                'ok' => false,
                'message' => "Evento '{$data['event_type']}' no permitido. Último: '" . ($last->event_type ?? 'ninguno') . "'",
            ], 422);
        }

        AttendanceEvent::create([
            'attendance_day_id' => $day->id,
            'event_type'        => $data['event_type'],
            'location'          => [
                'lat' => (float)$data['location']['lat'],
                'lng' => (float)$data['location']['lng'],
                'accuracy' => isset($data['location']['accuracy']) ? (float)$data['location']['accuracy'] : null,
                'branch_id' => $data['branch_id'] ?? null,
            ],
            'recorded_at'       => Carbon::now(),
        ]);

        return response()->json([
            'ok' => true,
            'message' => "Marcación registrada para {$employee->first_name } ({$data['event_type']}).",
        ]);
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
            $saved = is_array($emp->face_descriptor)
                ? $emp->face_descriptor
                : (is_string($emp->face_descriptor) ? json_decode($emp->face_descriptor, true) : null);

            if (!is_array($saved) || count($saved) !== 128) continue;

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
            $d = ($a[$i] ?? 0) - ($b[$i] ?? 0);
            $sum += $d * $d;
        }
        return sqrt($sum);
    }

    /** Reglas de transición válidas */
    protected function allowedNextEvents(?string $last): array
    {
        if ($last === null) return ['check_in'];
        return match ($last) {
            'check_in'    => ['break_start', 'check_out'],
            'break_start' => ['break_end'],
            'break_end'   => ['break_start', 'check_out'],
            'check_out'   => [], // ya terminó el día
            default       => ['check_in'],
        };
    }
}
