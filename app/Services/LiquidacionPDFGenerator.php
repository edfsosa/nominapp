<?php

namespace App\Services;

use App\Models\Liquidacion;
use App\Settings\GeneralSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class LiquidacionPDFGenerator
{
    public function generate(Liquidacion $liquidacion): string
    {
        $settings = app(GeneralSettings::class);
        $company = $liquidacion->employee?->company;

        $logoPath = $company?->logo ?? $settings->company_logo;
        $companyLogo = $logoPath ? storage_path('app/public/' . $logoPath) : null;

        $pdf = Pdf::loadView('pdf.liquidacion', [
            'liquidacion' => $liquidacion->load(['employee.position.department', 'items']),
            'companyLogo' => $companyLogo && file_exists($companyLogo) ? $companyLogo : null,
            'companyName' => $company?->name ?? $settings->company_name,
            'companyRuc' => $company?->ruc ?? $settings->company_ruc ?? '',
            'companyAddress' => $company?->address ?? $settings->company_address ?? '',
            'companyPhone' => $company?->phone ?? $settings->company_phone ?? '',
            'companyEmail' => $company?->email ?? $settings->company_email ?? '',
            'employerNumber' => $company?->employer_number ?? $settings->company_employer_number ?? '',
            'city' => $company?->city ?? $settings->company_city ?? '',
        ])->setPaper('A4');

        $employee = $liquidacion->employee;
        $fileName = 'liquidaciones/liquidacion_' . $liquidacion->id . '-' . str_replace(' ', '_', $employee?->full_name ?? 'unknown') . '.pdf';

        Storage::disk('public')->put($fileName, $pdf->output());

        return $fileName;
    }
}
