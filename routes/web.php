<?php

use App\Http\Controllers\AttendanceExportController;
use App\Http\Controllers\AttendanceFaceMarkController;
use App\Http\Controllers\EmployeeFaceController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\ScheduleEmployeeController;
use App\Http\Controllers\VacationDocumentController;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas Públicas - Marcación de Asistencia
|--------------------------------------------------------------------------
|
| Rutas para el sistema de marcación facial sin autenticación.
| Estas rutas son accesibles desde kioscos/terminales públicas.
|
*/

Route::prefix('marcar')->name('mark.')->group(function () {
    Route::get('/', [AttendanceFaceMarkController::class, 'show'])->name('show');
    Route::post('/identificar', [AttendanceFaceMarkController::class, 'identify'])->name('identify');
    Route::post('/', [AttendanceFaceMarkController::class, 'store'])->name('store');
});

// Terminal/Kiosco mode (interfaz alternativa para marcación)
Route::get('/terminal', [AttendanceFaceMarkController::class, 'terminal'])->name('terminal.show');

/*
|--------------------------------------------------------------------------
| API Pública - Empleados
|--------------------------------------------------------------------------
|
| Endpoint para obtener lista de empleados activos con rostro registrado.
| Usado por el sistema de reconocimiento facial para identificación.
| Requiere que el empleado tenga face_descriptor (datos biométricos).
|
*/

Route::get('/api/employees', function (Request $request) {
    $branchId = $request->query('branch_id');

    $employees = Employee::query()
        ->where('status', 'activo')
        ->when($branchId, fn($query) => $query->where('branch_id', $branchId))
        ->whereNotNull('face_descriptor') // Requiere rostro registrado para reconocimiento
        ->select('id', 'first_name', 'last_name', 'ci', 'photo', 'face_descriptor')
        ->get();

    return response()->json($employees);
})->name('api.employees');

/*
|--------------------------------------------------------------------------
| Rutas Autenticadas
|--------------------------------------------------------------------------
|
| Rutas que requieren autenticación de usuario.
| Incluyen exportaciones, gestión de horarios, recibos, etc.
|
*/

Route::middleware(['auth'])->group(function () {

    // Captura de rostro de empleados
    Route::prefix('employees/{employee}')->name('face.')->group(function () {
        Route::get('/capture-face', [EmployeeFaceController::class, 'show'])->name('capture');
        Route::post('/capture-face', [EmployeeFaceController::class, 'store'])->name('capture.store');
    });

    // Exportación de asistencias
    Route::get('/asistencias/{attendance_day}/export', [AttendanceExportController::class, 'export'])
        ->name('attendance-days.export');

    // Recibos de pago (nómina)
    Route::prefix('recibos/{payroll}')->name('payrolls.')->group(function () {
        Route::get('/download', [PayrollController::class, 'download'])->name('download');
        Route::get('/view', [PayrollController::class, 'view'])->name('view');
    });

    // Préstamos y adelantos
    Route::get('/prestamos/{loan}/pdf', [LoanController::class, 'show'])->name('loans.pdf');

    // Administración de horarios
    Route::post('/admin/schedules/{schedule}/remove-employee/{employee}', [ScheduleEmployeeController::class, 'removeEmployee'])
        ->name('schedules.remove-employee');

    // Descarga de documentos de vacaciones
    Route::get('/vacaciones/documentos/{filename}', [VacationDocumentController::class, 'download'])
        ->name('vacation.documents.download');
});
