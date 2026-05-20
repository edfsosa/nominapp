<?php

namespace App\Services;

use App\Models\Aguinaldo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AguinaldoPDFGenerator
{
    public function generate(Aguinaldo $aguinaldo): string
    {
        $company = $aguinaldo->period->company;

        $logoPath = $company?->logo;
        $companyLogo = $logoPath ? storage_path('app/public/'.$logoPath) : null;

        $pdf = Pdf::loadView('pdf.aguinaldo', [
            'aguinaldo' => $aguinaldo,
            'companyLogo' => $companyLogo && file_exists($companyLogo) ? $companyLogo : null,
            'companyName' => $company?->name ?? '',
            'companyRuc' => $company?->ruc ?? '',
            'companyAddress' => $company?->address ?? '',
            'companyPhone' => $company?->phone ?? '',
            'companyEmail' => $company?->email ?? '',
            'employerNumber' => $company?->employer_number ?? '',
            'city' => $company?->city ?? '',
        ])->setPaper('A4');

        $employee = $aguinaldo->employee;
        $fileName = 'aguinaldos/aguinaldo_'.$aguinaldo->period->year.'_'.$aguinaldo->id.'-'.Str::slug($employee->full_name).'.pdf';

        if ($aguinaldo->pdf_path && $aguinaldo->pdf_path !== $fileName) {
            Storage::disk('public')->delete($aguinaldo->pdf_path);
        }

        Storage::disk('public')->put($fileName, $pdf->output());

        return $fileName;
    }
}
