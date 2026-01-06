<?php

use App\Http\Controllers\AttendanceExportController;
use App\Http\Controllers\AttendanceFaceMarkController;
use App\Http\Controllers\EmployeeFaceController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\ScheduleEmployeeController;
use Illuminate\Support\Facades\Route;
use App\Models\Employee;
use Illuminate\Http\Request;

Route::get('/marcar', [AttendanceFaceMarkController::class, 'show'])->name('mark.show');
Route::post('/marcar/identificar', [AttendanceFaceMarkController::class, 'identify'])->name('mark.identify');
Route::post('/marcar', [AttendanceFaceMarkController::class, 'store'])->name('mark.store');

// Terminal/Kiosco mode - uses same backend endpoints
Route::get('/terminal', [AttendanceFaceMarkController::class, 'terminal'])->name('terminal.show');

Route::get('/api/employees', function (Request $request) {
    $branch_id = $request->query('branch_id'); // Obtener branch_id del parámetro de consulta

    $employees = Employee::where('status', 'activo')
        ->where('branch_id', $branch_id) // Filtrar por sucursal
        ->whereNotNull('photo')
        ->select('id', 'first_name', 'last_name', 'ci', 'photo')
        ->get();

    return response()->json($employees);
});

Route::middleware(['auth'])->group(function () {
    Route::get('/employees/{employee}/capture-face', [EmployeeFaceController::class, 'show'])->name('face.capture');
    Route::post('/employees/{employee}/capture-face', [EmployeeFaceController::class, 'store'])->name('face.capture.store');

    Route::get('/asistencias/{attendance_day}/export', [AttendanceExportController::class, 'export'])->name('attendance-days.export');

    Route::get('/recibos/{payroll}/download', [PayrollController::class, 'download'])->name('payrolls.download');
    Route::get('/recibos/{payroll}/view', [PayrollController::class, 'view'])->name('payrolls.view');

    Route::post('/admin/schedules/{schedule}/remove-employee/{employee}', [ScheduleEmployeeController::class, 'removeEmployee'])->name('schedules.remove-employee');
});
