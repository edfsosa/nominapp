<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class PayrollController extends Controller
{
    // Descargar PDF existente (desde storage)
    public function download(Payroll $payroll)
    {
        if (!$payroll->pdf_path || !Storage::exists($payroll->pdf_path)) {
            abort(404, 'PDF no disponible.');
        }

        return Storage::download($payroll->pdf_path, 'recibo_' . $payroll->id . '.pdf');
    }

    // Visualizar el PDF en el navegador
    public function view(Payroll $payroll)
    {
        if (!$payroll->pdf_path || !Storage::exists($payroll->pdf_path)) {
            abort(404, 'PDF no disponible.');
        }

        $pdf = Storage::get($payroll->pdf_path);
        return response($pdf)->header('Content-Type', 'application/pdf');
    }
}
