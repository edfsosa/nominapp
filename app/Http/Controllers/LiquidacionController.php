<?php

namespace App\Http\Controllers;

use App\Models\Liquidacion;
use Illuminate\Support\Facades\Storage;

class LiquidacionController extends Controller
{
    public function download(Liquidacion $liquidacion)
    {
        if ($liquidacion->pdf_path && Storage::disk('public')->exists($liquidacion->pdf_path)) {
            $path = Storage::disk('public')->path($liquidacion->pdf_path);

            return response()->file($path, [
                'Content-Type' => 'application/pdf',
            ]);
        } else {
            return redirect()->back()->with('error', 'El archivo PDF no existe.');
        }
    }

    public function view(Liquidacion $liquidacion)
    {
        $company = $liquidacion->employee?->company;

        $logoPath = $company?->logo;
        $companyLogo = $logoPath ? storage_path('app/public/'.$logoPath) : null;

        return view('pdf.liquidacion', [
            'liquidacion' => $liquidacion->load(['employee.activeContract.position.department', 'items']),
            'companyLogo' => $companyLogo && file_exists($companyLogo) ? $companyLogo : null,
            'companyName' => $company?->name ?? '',
            'companyRuc' => $company?->ruc ?? '',
            'companyAddress' => $company?->address ?? '',
            'companyPhone' => $company?->phone ?? '',
            'companyEmail' => $company?->email ?? '',
            'employerNumber' => $company?->employer_number ?? '',
            'city' => $company?->city ?? '',
        ]);
    }
}
