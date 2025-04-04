<?php

use Illuminate\Support\Facades\Route;
use App\Models\Payroll;
use Barryvdh\DomPDF\Facade\Pdf;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/payroll/{payroll}/pdf', function (Payroll $payroll) {
    $pdf = Pdf::loadView('pdf.payroll', ['payroll' => $payroll]);
    return $pdf->stream("recibo-{$payroll->id}.pdf");
})->name('payroll.pdf');
