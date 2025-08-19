<?php

use App\Http\Controllers\AttendanceFaceMarkController;
use App\Http\Controllers\AttendanceMarkingController;
use App\Http\Controllers\EmployeeFaceController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PayrollController;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Payroll;
use Illuminate\Http\Request;

Route::get('/payroll/{payroll}/download/{employee}', [PayrollController::class, 'downloadPayslip'])
    ->name('payroll.download')
    ->middleware('signed');

Route::get('/payroll/{payroll}/download-all', function (Payroll $payroll) {
    $zip = new ZipArchive();
    $zipName = storage_path("app/recibos-{$payroll->id}.zip");

    if ($zip->open($zipName, ZipArchive::CREATE) === TRUE) {
        foreach ($payroll->employees as $employee) {
            $pdf = app(PayrollController::class)->downloadPayslip($payroll, $employee);
            $zip->addFromString("recibo-{$employee->id}.pdf", $pdf->output());
        }
        $zip->close();
    }

    return response()->download($zipName)->deleteFileAfterSend(true);
})->name('payroll.download.all')->middleware('signed');


Route::middleware(['web']) // agrega 'auth' si querés restringir
    ->group(function () {
        Route::get('/mark', [AttendanceFaceMarkController::class, 'show'])->name('mark.show');
        Route::post('/mark/identify', [AttendanceFaceMarkController::class, 'identify'])->name('mark.identify');
        Route::post('/mark', [AttendanceFaceMarkController::class, 'store'])->name('mark.store');
    });

Route::get('/api/employees', function (Request $request) {
    $branch_id = $request->query('branch_id'); // Obtener branch_id del parámetro de consulta

    $employees = Employee::where('status', 'activo')
        ->where('branch_id', $branch_id) // Filtrar por sucursal
        ->whereNotNull('photo')
        ->select('id', 'first_name', 'last_name', 'ci', 'photo')
        ->get();

    return response()->json($employees);
});

Route::get('/api/branches', function () {
    $branches = Branch::select('id', 'name')->get();
    return response()->json($branches);
});


Route::middleware(['auth'])->group(function () {
    Route::get('/employees/{employee}/capture-face', [EmployeeFaceController::class, 'show'])
        ->name('face.capture');
    Route::post('/employees/{employee}/capture-face', [EmployeeFaceController::class, 'store'])
        ->name('face.capture.store');
});
