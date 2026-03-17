<?php

namespace App\Services;

use App\Models\Aguinaldo;
use App\Settings\GeneralSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AguinaldoPDFGenerator
{
    public function generate(Aguinaldo $aguinaldo): string
    {
        $settings = app(GeneralSettings::class);

        // Obtener empresa del período
        $company = $aguinaldo->period->company;

        // Obtener ruta del logo
        $logoPath = $company?->logo ?? $settings->company_logo;
        $companyLogo = $logoPath ? storage_path('app/public/' . $logoPath) : null;

        $pdf = Pdf::loadView('pdf.aguinaldo', [
            'aguinaldo' => $aguinaldo,
            'companyLogo' => $companyLogo && file_exists($companyLogo) ? $companyLogo : null,
            'companyName' => $company?->name ?? $settings->company_name,
            'companyRuc' => $company?->ruc ?? $settings->company_ruc ?? '',
            'companyAddress' => $company?->address ?? $settings->company_address ?? '',
            'companyPhone' => $company?->phone ?? $settings->company_phone ?? '',
            'companyEmail' => $company?->email ?? $settings->company_email ?? '',
            'employerNumber' => $company?->employer_number ?? $settings->company_employer_number ?? '',
            'city' => $company?->city ?? $settings->company_city ?? '',
        ])->setPaper('A4');

        $employee = $aguinaldo->employee;
        $fileName = 'aguinaldos/aguinaldo_' . $aguinaldo->period->year . '_' . $aguinaldo->id . '-' . Str::slug($employee->full_name) . '.pdf';

        // Eliminar PDF anterior si el nombre cambió (ej. cambio de nombre del empleado)
        if ($aguinaldo->pdf_path && $aguinaldo->pdf_path !== $fileName) {
            Storage::disk('public')->delete($aguinaldo->pdf_path);
        }

        Storage::disk('public')->put($fileName, $pdf->output());

        return $fileName;
    }
}
