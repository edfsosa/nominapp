<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Settings\GeneralSettings;
use Barryvdh\DomPDF\Facade\Pdf;

class ContractController extends Controller
{
    /**
     * Genera y muestra el PDF del contrato laboral en el navegador (stream).
     */
    public function show(Contract $contract)
    {
        $contract->load(['employee.branch.company', 'position', 'department']);

        $settings = app(GeneralSettings::class);
        $company = $contract->employee?->company;

        $logoPath = $company?->logo ?? $settings->company_logo;
        $companyLogo = $logoPath ? storage_path('app/public/' . $logoPath) : null;

        $pdf = Pdf::loadView('pdf.contract', [
            'contract'       => $contract,
            'companyLogo'    => $companyLogo && file_exists($companyLogo) ? $companyLogo : null,
            'companyName'    => $company?->name ?? $settings->company_name,
            'companyRuc'     => $company?->ruc ?? $settings->company_ruc ?? '',
            'companyAddress' => $company?->address ?? $settings->company_address ?? '',
            'companyPhone'   => $company?->phone ?? $settings->company_phone ?? '',
            'companyEmail'   => $company?->email ?? $settings->company_email ?? '',
            'employerNumber' => $company?->employer_number ?? $settings->company_employer_number ?? '',
            'city'           => $company?->city ?? $settings->company_city ?? '',
            'salaryInWords'  => self::numberToWords((int) $contract->salary) . ' guaranies',
            'salaryTypeLabel' => $contract->salary_type === 'jornal' ? 'jornal diario' : 'salario mensual',
        ])->setPaper('a4', 'portrait');

        $employeeCi = $contract->employee?->ci ?? 'sin_ci';

        return $pdf->stream("contrato_{$employeeCi}_{$contract->start_date->format('Y_m_d')}.pdf");
    }

    /**
     * Convierte un número a su representación en palabras en español.
     */
    private static function numberToWords(int $number): string
    {
        if ($number === 0) {
            return 'cero';
        }

        $units = ['', 'un', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve'];
        $teens = ['diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciseis', 'diecisiete', 'dieciocho', 'diecinueve'];
        $tens = ['', 'diez', 'veinte', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];
        $hundreds = ['', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos', 'setecientos', 'ochocientos', 'novecientos'];

        $result = '';

        if ($number >= 1000000) {
            $millions = (int) ($number / 1000000);
            if ($millions === 1) {
                $result .= 'un millon ';
            } else {
                $result .= self::numberToWords($millions) . ' millones ';
            }
            $number %= 1000000;
        }

        if ($number >= 1000) {
            $thousands = (int) ($number / 1000);
            if ($thousands === 1) {
                $result .= 'mil ';
            } else {
                $result .= self::numberToWords($thousands) . ' mil ';
            }
            $number %= 1000;
        }

        if ($number >= 100) {
            if ($number === 100) {
                $result .= 'cien';
                return trim($result);
            }
            $result .= $hundreds[(int) ($number / 100)] . ' ';
            $number %= 100;
        }

        if ($number >= 21 && $number <= 29) {
            $veinti = ['', 'veintiun', 'veintidos', 'veintitres', 'veinticuatro', 'veinticinco', 'veintiseis', 'veintisiete', 'veintiocho', 'veintinueve'];
            $result .= $veinti[$number - 20];
        } elseif ($number >= 30) {
            $result .= $tens[(int) ($number / 10)];
            if ($number % 10 > 0) {
                $result .= ' y ' . $units[$number % 10];
            }
        } elseif ($number === 20) {
            $result .= 'veinte';
        } elseif ($number >= 10) {
            $result .= $teens[$number - 10];
        } elseif ($number > 0) {
            $result .= $units[$number];
        }

        return trim($result);
    }
}
