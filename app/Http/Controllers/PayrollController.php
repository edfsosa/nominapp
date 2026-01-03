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
        if (Storage::disk('public')->exists($payroll->pdf_path)) {
            return Storage::disk('public')->download($payroll->pdf_path);
        } else {
            return redirect()->back()->with('error', 'El archivo PDF no existe.');
        }
    }

    // Montar vista para mostrar el PDF en el navegador
    public function view(Payroll $payroll)
    {
        return view('pdf.payroll', compact('payroll'));
    }
}
