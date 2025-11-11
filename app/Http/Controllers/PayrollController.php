<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use Barryvdh\DomPDF\Facade\Pdf;

class PayrollController extends Controller
{
    public function generate(Payroll $payroll)
    {
        $pdf = Pdf::loadView('pdf.payroll', compact('payroll'));
        return $pdf->stream('payroll_' . $payroll->id . '.pdf');
    }
}