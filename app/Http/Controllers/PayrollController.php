<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use App\Services\PayrollPDFGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PayrollController extends Controller
{
    public function __construct(protected PayrollPDFGenerator $pdfGenerator) {}

    /**
     * Sirve el PDF del recibo.
     *
     * - mode=print (default): A4 landscape, 2 copias. Usa el archivo en disco; lo regenera si no existe.
     * - mode=employee: A4 portrait, 1 copia. Siempre genera on-demand sin guardar en disco.
     */
    public function download(Payroll $payroll, Request $request): mixed
    {
        $mode = $request->query('mode', 'print');

        if ($mode === 'employee') {
            $content = $this->pdfGenerator->generateContent($payroll, 'employee');

            return response($content, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="recibo_empleado_'.$payroll->id.'_'.$payroll->employee->ci.'.pdf"',
            ]);
        }

        if (! $payroll->pdf_path || ! Storage::disk('public')->exists($payroll->pdf_path)) {
            try {
                $pdfPath = $this->pdfGenerator->generate($payroll);
                $payroll->update(['pdf_path' => $pdfPath]);
            } catch (\Throwable) {
                abort(404, 'No se pudo generar el recibo PDF.');
            }
        }

        $path = Storage::disk('public')->path($payroll->pdf_path);

        $filename = 'recibo_salario_'.$payroll->id.'_'.$payroll->employee->ci.'.pdf';

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    /** Descarga un archivo temporal (PDF o ZIP) generado por bulk actions. */
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

    /** Monta la vista Blade del PDF para previsualizar en el navegador. */
    public function view(Payroll $payroll): mixed
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
            'mode' => 'print',
        ]);
    }
}
