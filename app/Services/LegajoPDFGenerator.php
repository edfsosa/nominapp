<?php

namespace App\Services;

use App\Models\Employee;
use App\Settings\GeneralSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * Genera el legajo del empleado en formato PDF para visualización en línea.
 * Incluye datos personales, laborales, contrato activo y deducciones/percepciones activas.
 */
class LegajoPDFGenerator
{
    /**
     * Genera el PDF del legajo y lo retorna como respuesta inline (se abre en el navegador).
     *
     * @param  Employee  $employee
     * @return Response
     */
    public function download(Employee $employee): Response
    {
        $settings = app(GeneralSettings::class);

        $company  = $employee->company;
        $logoPath = $company?->logo ?? $settings->company_logo;

        $employee->load([
            'activeContract.position.department',
            'branch.company',
            'activeEmployeeDeductions.deduction',
            'activeEmployeePerceptions.perception',
        ]);

        $pdf = Pdf::loadView('pdf.legajo', [
            'employee'        => $employee,
            'contract'        => $employee->activeContract,
            'companyLogo'     => $logoPath ? storage_path('app/public/' . $logoPath) : null,
            'companyName'     => $company?->name ?? $settings->company_name,
            'companyRuc'      => $company?->ruc ?? $settings->company_ruc ?? '',
            'companyAddress'  => $company?->address ?? $settings->company_address ?? '',
            'companyPhone'    => $company?->phone ?? $settings->company_phone ?? '',
            'companyEmail'    => $company?->email ?? $settings->company_email ?? '',
            'employerNumber'  => $company?->employer_number ?? $settings->company_employer_number ?? '',
            'city'            => $company?->city ?? $settings->company_city ?? '',
        ])->setPaper('A4');

        $slug     = \Illuminate\Support\Str::slug($employee->full_name, '_');
        $fileName = 'legajo_' . $employee->id . '_' . $slug . '.pdf';

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
        ]);
    }
}
