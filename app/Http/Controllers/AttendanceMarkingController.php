<?php

namespace App\Http\Controllers;

use App\Models\AttendanceDay;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AttendanceMarkingController extends Controller
{
    public function showForm()
    {
        // Obtener todas las sucursales
        $branches = Branch::all();
        return view('attendance.mark', compact('branches'));
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'event_type'  => ['required', 'in:check_in,break_start,break_end,check_out'],
                'employee_id' => ['required', 'exists:employees,id'],
                'location'    => ['nullable', 'array'],
            ]);

            $today = now()->toDateString();

            // 1) Obtener o crear el registro de asistencia del día
            $attendanceDay = AttendanceDay::firstOrCreate(
                ['employee_id' => $data['employee_id'], 'date' => $today],
                ['status'      => 'present']
            );

            // 2) Eventos existentes ordenados
            $events = $attendanceDay->events()->orderBy('recorded_at')->get();

            // 3) Validaciones de flujo

            // 3a) Primera marcación debe ser check_in
            if ($events->isEmpty() && $data['event_type'] !== 'check_in') {
                return response()->json([
                    'success' => false,
                    'message' => 'La primera marcación del día debe ser entrada (check_in).',
                ], 400);
            }

            // 3b) Solo un check_in por día
            if (
                $data['event_type'] === 'check_in'
                && $events->contains(fn($e) => $e->event_type === 'check_in')
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe una entrada de jornada para este día.',
                ], 400);
            }

            // 3c) Salida de jornada: debe ser última y única
            if ($data['event_type'] === 'check_out') {
                if (! $events->contains(fn($e) => $e->event_type === 'check_in')) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Debes registrar primero la entrada de jornada.',
                    ], 400);
                }
                if ($events->contains(fn($e) => $e->event_type === 'check_out')) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Ya registraste la salida de jornada hoy.',
                    ], 400);
                }
                // Verificar descansos abiertos antes de salir
                foreach (['break_start' => 'break_end'] as $start => $end) {
                    $lastStart = $events->last(fn($e) => $e->event_type === $start);
                    $lastEnd   = $events->last(fn($e) => $e->event_type === $end);
                    if ($lastStart && (! $lastEnd || $lastEnd->recorded_at < $lastStart->recorded_at)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Debes cerrar tu descanso antes de salir de jornada.',
                        ], 400);
                    }
                }
            }

            // 3d) No cerrar descanso sin abrirlo
            if ($data['event_type'] === 'break_end') {
                $lastBreakStart = $events->last(fn($e) => $e->event_type === 'break_start');
                if (! $lastBreakStart || now()->lt($lastBreakStart->recorded_at)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No puedes terminar un descanso sin haberlo iniciado primero.',
                    ], 400);
                }
            }

            // 4) Crear el nuevo evento
            $attendanceEvent = $attendanceDay->events()->create([
                'event_type'  => $data['event_type'],
                'recorded_at' => now(),
                'location'    => $data['location'] ?? null,
            ]);

            // 5) Devolver día y eventos actualizados
            return response()->json([
                'success' => true,
                'event'   => $attendanceEvent,
                'day'     => $attendanceDay,
                'events'  => $attendanceDay->events()->orderBy('recorded_at')->get(),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al registrar marcación: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error interno. Inténtalo de nuevo más tarde.',
            ], 500);
        }
    }
}
