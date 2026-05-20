<?php

namespace App\Services;

use App\Models\Liquidacion;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class LiquidacionPDFGenerator
{
    public function generate(Liquidacion $liquidacion): string
    {
        $company = $liquidacion->employee?->company;

        $logoPath = $company?->logo;
        $companyLogo = $logoPath ? storage_path('app/public/'.$logoPath) : null;

        $pdf = Pdf::loadView('pdf.liquidacion', [
            'liquidacion' => $liquidacion->load(['employee.activeContract.position.department', 'items']),
            'companyLogo' => $companyLogo && file_exists($companyLogo) ? $companyLogo : null,
            'companyName' => $company?->name ?? '',
            'companyRuc' => $company?->ruc ?? '',
            'companyAddress' => $company?->address ?? '',
            'companyPhone' => $company?->phone ?? '',
            'companyEmail' => $company?->email ?? '',
            'employerNumber' => $company?->employer_number ?? '',
            'city' => $company?->city ?? '',
        ])->setPaper('A4');

        $employee = $liquidacion->employee;
        $fileName = 'liquidaciones/liquidacion_'.$liquidacion->id.'-'.str_replace(' ', '_', $employee?->full_name ?? 'unknown').'.pdf';

        Storage::disk('public')->put($fileName, $pdf->output());

        return $fileName;
    }
}
