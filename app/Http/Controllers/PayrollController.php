<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use Illuminate\Support\Facades\Storage;

class PayrollController extends Controller
{
    // Mostrar PDF existente en el navegador (desde storage)
    public function download(Payroll $payroll)
    {
        if (Storage::disk('public')->exists($payroll->pdf_path)) {
            $path = Storage::disk('public')->path($payroll->pdf_path);

            return response()->file($path, [
                'Content-Type' => 'application/pdf',
            ]);
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
