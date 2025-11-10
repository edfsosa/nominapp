<?php

use App\Http\Controllers\AttendanceExportController;
use App\Http\Controllers\AttendanceFaceMarkController;
use App\Http\Controllers\EmployeeFaceController;
use Illuminate\Support\Facades\Route;
use App\Models\Employee;
use Illuminate\Http\Request;

Route::get('/marcar', [AttendanceFaceMarkController::class, 'show'])->name('mark.show');
Route::post('/marcar/identificar', [AttendanceFaceMarkController::class, 'identify'])->name('mark.identify');
Route::post('/marcar', [AttendanceFaceMarkController::class, 'store'])->name('mark.store');

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

});
