<?php

namespace App\Services;

use App\Models\Payroll;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPDF;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PayrollPDFGenerator
{
    /**
     * Genera el PDF de impresión (A4 landscape, 2 copias) y lo guarda en disco.
     *
     * @return string Ruta relativa en el disco 'public'
     */
    public function generate(Payroll $payroll): string
    {
        $pdf = $this->buildPdf($payroll, 'print');

        $employeeName = Str::slug($payroll->employee->first_name.' '.$payroll->employee->last_name, '_');
        $fileName = 'payrolls/recibo_'.$payroll->id.'-'.$employeeName.'.pdf';

        Storage::disk('public')->put($fileName, $pdf->output());

        return $fileName;
    }

    /**
     * Genera el contenido binario del PDF en el modo indicado sin guardarlo en disco.
     *
     * @param  string  $mode  'print' (landscape, 2 copias) | 'employee' (portrait, 1 copia)
     * @return string Contenido binario del PDF
     */
    public function generateContent(Payroll $payroll, string $mode = 'print'): string
    {
        return $this->buildPdf($payroll, $mode)->output();
    }

    /** Construye la instancia DomPDF para el modo indicado. */
    private function buildPdf(Payroll $payroll, string $mode): DomPDF
    {
        $company = $payroll->employee->company;
        $logoPath = $company?->logo;
        $companyLogo = $logoPath ? storage_path('app/public/'.$logoPath) : null;

        $orientation = $mode === 'print' ? 'landscape' : 'portrait';

        return Pdf::loadView('pdf.payroll', [
            'payroll' => $payroll->load(['employee.activeContract.position.department', 'items']),
            'companyLogo' => $companyLogo && file_exists($companyLogo) ? $companyLogo : null,
            'companyName' => $company?->name ?? '',
            'companyRuc' => $company?->ruc ?? '',
            'companyAddress' => $company?->address ?? '',
            'companyPhone' => $company?->phone ?? '',
            'companyEmail' => $company?->email ?? '',
            'employerNumber' => $company?->employer_number ?? '',
            'city' => $company?->city ?? '',
            'mode' => $mode,
        ])->setPaper('A4', $orientation);
    }
}
