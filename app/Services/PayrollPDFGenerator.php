<?php

namespace App\Services;

use App\Models\Payroll;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PayrollPDFGenerator
{
    public function generate(Payroll $payroll): string
    {
        $company = $payroll->employee->company;

        $logoPath = $company?->logo;
        $companyLogo = $logoPath ? storage_path('app/public/'.$logoPath) : null;

        $pdf = Pdf::loadView('pdf.payroll', [
            'payroll' => $payroll->load(['employee.activeContract.position.department', 'items']),
            'companyLogo' => $companyLogo && file_exists($companyLogo) ? $companyLogo : null,
            'companyName' => $company?->name ?? '',
            'companyRuc' => $company?->ruc ?? '',
            'companyAddress' => $company?->address ?? '',
            'companyPhone' => $company?->phone ?? '',
            'companyEmail' => $company?->email ?? '',
            'employerNumber' => $company?->employer_number ?? '',
            'city' => $company?->city ?? '',
        ])->setPaper('A4');

        $employeeName = Str::slug($payroll->employee->first_name.' '.$payroll->employee->last_name, '_');
        $fileName = 'payrolls/recibo_'.$payroll->id.'-'.$employeeName.'.pdf';

        Storage::disk('public')->put($fileName, $pdf->output());

        return $fileName;
    }
}
