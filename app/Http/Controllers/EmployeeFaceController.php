<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\FaceEnrollment;
use App\Rules\FaceDescriptor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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
            // Validar el descriptor facial y metadatos de captura
            $data = $request->validate([
                'face_descriptor' => ['required', new FaceDescriptor],
                'face_snapshot'   => ['nullable', 'string'],
                'samples_count'   => ['nullable', 'integer', 'min:1', 'max:255'],
                'face_score'      => ['nullable', 'numeric', 'min:0', 'max:1'],
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

            // Actualizar el empleado e invalidar caché de descriptores
            $updated = $employee->update(['face_descriptor' => $descriptor]);
            Cache::forget('employees_face_descriptors');

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

            // Registrar en face_enrollments como captura de admin (auto-aprobada)
            $snapshotPath = null;
            if (!empty($data['face_snapshot'])) {
                $snapshotPath = $this->saveSnapshotFromBase64($data['face_snapshot'], $employee->id);
            }

            $now = now();
            $adminId = Auth::id();

            $enrollment = FaceEnrollment::create([
                'employee_id'     => $employee->id,
                'token'           => null,
                'face_descriptor' => $descriptor,
                'snapshot_path'   => $snapshotPath,
                'samples_count'   => $data['samples_count'] ?? null,
                'face_score'      => $data['face_score'] ?? null,
                'source'          => 'admin',
                'status'          => 'approved',
                'expires_at'      => null,
                'captured_at'     => $now,
                'reviewed_at'     => $now,
                'generated_by_id' => $adminId,
                'reviewed_by_id'  => $adminId,
                'ip_address'      => $request->ip(),
                'user_agent'      => substr($request->userAgent() ?? '', 0, 255),
            ]);

            // Expirar otros enrollments pendientes del mismo empleado
            FaceEnrollment::where('employee_id', $employee->id)
                ->where('id', '!=', $enrollment->id)
                ->whereIn('status', ['pending_capture', 'pending_approval'])
                ->update(['status' => 'expired']);

            // Log de éxito
            Log::info('Face descriptor saved by admin', [
                'employee_id'   => $employee->id,
                'enrollment_id' => $enrollment->id,
                'admin_id'      => $adminId,
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

    private function saveSnapshotFromBase64(string $base64, int $employeeId): ?string
    {
        try {
            $data = preg_replace('/^data:image\/\w+;base64,/', '', $base64);
            $imageData = base64_decode($data);

            if ($imageData === false) return null;

            $path = sprintf('face-snapshots/%s/emp_%d.jpg', now()->format('Y/m'), $employeeId);
            Storage::disk('public')->put($path, $imageData);

            return $path;
        } catch (\Throwable $e) {
            Log::warning('No se pudo guardar el snapshot facial (admin)', [
                'employee_id' => $employeeId,
                'error'       => $e->getMessage(),
            ]);
            return null;
        }
    }
}
