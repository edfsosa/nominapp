<?php

namespace App\Http\Controllers;

use App\Models\FaceEnrollment;
use App\Rules\FaceDescriptor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FaceEnrollmentController extends Controller
{
    /**
     * Muestra la vista de auto-registro facial para el empleado.
     */
    public function show(string $token): View
    {
        // Buscar el registro de inscripción facial por token
        $enrollment = FaceEnrollment::where('token', $token)->firstOrFail();

        // Verificar si el registro ha expirado o no está pendiente de captura
        if ($enrollment->isExpired() && $enrollment->isPendingCapture()) {
            return view('enrollments.expired');
        }

        // Si el registro no está pendiente de captura, mostrar una vista indicando que ya se ha enviado una solicitud
        if (!$enrollment->isPendingCapture()) {
            return view('enrollments.already-submitted');
        }

        // Obtener el empleado asociado al registro de inscripción facial para mostrar su información en la vista
        $employee = $enrollment->employee;

        return view('enrollments.capture-face', compact('enrollment', 'employee'));
    }

    /**
     * Almacena el descriptor facial capturado por el empleado.
     */
    public function store(Request $request, string $token): JsonResponse
    {
        try {
            // Validar que el token exista y esté en estado pendiente de captura
            $enrollment = FaceEnrollment::where('token', $token)->first();

            // Verificar si el registro existe, no ha expirado y está pendiente de captura
            if (!$enrollment || $enrollment->isExpired() || !$enrollment->isPendingCapture()) {
                Log::warning('Intento de captura facial con token inválido o expirado', [
                    'token' => substr($token, 0, 8) . '...',
                    'exists' => (bool) $enrollment,
                    'expired' => $enrollment?->isExpired(),
                    'status' => $enrollment?->status,
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Enlace inválido o expirado.',
                ], 422);
            }

            // Validar el descriptor facial usando la regla personalizada
            $data = $request->validate([
                'face_descriptor' => ['required', new FaceDescriptor],
            ]);

            // El descriptor puede ser un array o una cadena JSON, manejar ambos casos
            $descriptor = is_string($data['face_descriptor'])
                ? json_decode($data['face_descriptor'], true)
                : $data['face_descriptor'];

            // Si el descriptor es una cadena JSON pero no se pudo decodificar, lanzar una excepción de validación
            if ($descriptor === null && is_string($data['face_descriptor'])) {
                throw ValidationException::withMessages([
                    'face_descriptor' => 'El descriptor facial contiene JSON inválido.',
                ]);
            }

            // Actualizar el registro de inscripción con el descriptor facial y cambiar el estado a "pendiente de aprobación"
            $enrollment->update([
                'face_descriptor' => $descriptor,
                'status' => 'pending_approval',
                'captured_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => substr($request->userAgent() ?? '', 0, 255),
            ]);

            // Registrar el evento de captura facial para auditoría
            Log::info('Face enrollment captured', [
                'enrollment_id' => $enrollment->id,
                'employee_id' => $enrollment->employee_id,
            ]);

            // Retornar una respuesta JSON indicando éxito
            return response()->json([
                'success' => true,
                'message' => 'Rostro capturado correctamente. El administrador revisará su solicitud.',
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            // Registrar el error para diagnóstico
            Log::error('Error in face enrollment capture', [
                'token' => $token,
                'error' => $e->getMessage(),
            ]);

            // Retornar una respuesta JSON indicando error
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el registro facial. Intente nuevamente.',
            ], 500);
        }
    }
}
