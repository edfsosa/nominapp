<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Rules\FaceDescriptor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class EmployeeFaceController extends Controller
{
    /**
     * Muestra la vista de captura de rostro para un empleado.
     *
     * @param Employee $employee
     * @return \Illuminate\View\View
     */
    public function show(Employee $employee)
    {
        return view('employees.capture-face', compact('employee'));
    }

    /**
     * Almacena el descriptor facial del empleado.
     *
     * @param Request $request
     * @param Employee $employee
     * @return \Illuminate\Http\JsonResponse
     * @throws ValidationException
     */
    public function store(Request $request, Employee $employee)
    {
        try {
            // Validar el descriptor facial
            $data = $request->validate([
                'face_descriptor' => ['required', new FaceDescriptor],
            ]);

            // Acepta string JSON o array
            $descriptor = is_string($data['face_descriptor'])
                ? json_decode($data['face_descriptor'], true)
                : $data['face_descriptor'];

            // Validar que json_decode no falló
            if ($descriptor === null && is_string($data['face_descriptor'])) {
                throw ValidationException::withMessages([
                    'face_descriptor' => 'El descriptor facial contiene JSON inválido.',
                ]);
            }

            // Validar que es un array válido
            if (!is_array($descriptor)) {
                throw ValidationException::withMessages([
                    'face_descriptor' => 'El descriptor facial debe ser un array válido.',
                ]);
            }

            // Actualizar el empleado
            $updated = $employee->update(['face_descriptor' => $descriptor]);

            if (!$updated) {
                Log::error('Failed to update face descriptor', [
                    'employee_id' => $employee->id,
                    'descriptor_length' => count($descriptor),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Error al guardar el descriptor facial. Por favor, intente nuevamente.',
                ], 500);
            }

            // Log de éxito
            Log::info('Face descriptor saved successfully', [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Descriptor facial guardado correctamente',
            ]);

        } catch (ValidationException $e) {
            // Re-lanzar ValidationException para que Laravel lo maneje
            throw $e;

        } catch (\Exception $e) {
            // Log del error
            Log::error('Error saving face descriptor', [
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el descriptor facial. Por favor, intente nuevamente.',
            ], 500);
        }
    }
}
