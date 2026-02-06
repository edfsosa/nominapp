<?php

namespace App\Http\Controllers;

use App\Models\Aguinaldo;
use App\Settings\GeneralSettings;
use Illuminate\Support\Facades\Storage;

class AguinaldoController extends Controller
{
    public function download(Aguinaldo $aguinaldo)
    {
        if ($aguinaldo->pdf_path && Storage::disk('public')->exists($aguinaldo->pdf_path)) {
            $path = Storage::disk('public')->path($aguinaldo->pdf_path);

            return response()->file($path, [
                'Content-Type' => 'application/pdf',
            ]);
        } else {
            return redirect()->back()->with('error', 'El archivo PDF no existe.');
        }
    }

    public function view(Aguinaldo $aguinaldo)
    {
        $settings = app(GeneralSettings::class);
        $company = $aguinaldo->period->company;

        $logoPath = $company?->logo ?? $settings->company_logo;
        $companyLogo = $logoPath ? storage_path('app/public/' . $logoPath) : null;

        return view('pdf.aguinaldo', [
            'aguinaldo' => $aguinaldo,
            'companyLogo' => $companyLogo && file_exists($companyLogo) ? $companyLogo : null,
            'companyName' => $company?->name ?? $settings->company_name,
            'companyRuc' => $company?->ruc ?? $settings->company_ruc ?? '',
            'companyAddress' => $company?->address ?? $settings->company_address ?? '',
            'companyPhone' => $company?->phone ?? $settings->company_phone ?? '',
            'companyEmail' => $company?->email ?? $settings->company_email ?? '',
            'employerNumber' => $company?->employer_number ?? $settings->company_employer_number ?? '',
            'city' => $company?->city ?? $settings->company_city ?? '',
        ]);
    }
}
