<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use App\Services\PayrollPDFGenerator;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PayrollController extends Controller
{
    public function __construct(protected PayrollPDFGenerator $pdfGenerator) {}

    /** Muestra el PDF del recibo en el navegador. Si el archivo no existe en disco, lo regenera. */
    public function download(Payroll $payroll)
    {
        if (! $payroll->pdf_path || ! Storage::disk('public')->exists($payroll->pdf_path)) {
            try {
                $pdfPath = $this->pdfGenerator->generate($payroll);
                $payroll->update(['pdf_path' => $pdfPath]);
            } catch (\Throwable) {
                abort(404, 'No se pudo generar el recibo PDF.');
            }
        }

        $path = Storage::disk('public')->path($payroll->pdf_path);

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    // Descargar un archivo temporal (PDF o ZIP) generado por bulk actions
    public function downloadTemp(string $filename): BinaryFileResponse
    {
        $path = storage_path('app/public/temp/'.$filename);

        if (! file_exists($path)) {
            abort(404, 'Archivo no encontrado');
        }

        $cleanFilename = preg_replace('/^[a-f0-9-]+_/', '', $filename);

        if (str_ends_with($filename, '.zip')) {
            return response()->download($path, $cleanFilename)->deleteFileAfterSend(true);
        }

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$cleanFilename.'"',
        ])->deleteFileAfterSend(true);
    }

    // Montar vista para mostrar el PDF en el navegador
    public function view(Payroll $payroll)
    {
        $company = $payroll->employee?->company;

        $logoPath = $company?->logo;
        $companyLogo = $logoPath ? storage_path('app/public/'.$logoPath) : null;

        return view('pdf.payroll', [
            'payroll' => $payroll->load(['employee.activeContract.position.department', 'items']),
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
