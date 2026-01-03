<?php

namespace App\Services;

use App\Models\Payroll;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class PayrollPDFGenerator
{
    public function generate(Payroll $payroll): string
    {
        $pdf = Pdf::loadView('pdf.payroll', compact('payroll'))
            ->setPaper('A4');

        $fileName = 'payrolls/recibo_' . $payroll->id . '-' . $payroll->employee->first_name . '_' . $payroll->employee->last_name . '.pdf';

        // Guardar en disco 'public'
        Storage::disk('public')->put($fileName, $pdf->output());

        // Devolver la ruta para guardarla en la BD
        return $fileName;
    }
}
