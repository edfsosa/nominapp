<?php

namespace App\Filament\Resources\VacationResource\Pages;

use App\Filament\Resources\VacationResource;
use App\Services\VacationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Str;
use ZipArchive;

/** Muestra el detalle de una solicitud de vacaciones. */
class ViewVacation extends ViewRecord
{
    protected static string $resource = VacationResource::class;

    /**
     * Define las acciones del encabezado de la página.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Aprobar')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $this->record->status === 'pending')
                ->requiresConfirmation()
                ->modalHeading('Aprobar Solicitud de Vacaciones')
                ->modalDescription(function () {
                    $record = $this->record;
                    $days = $record->business_days ?? $record->total_days;
                    $returnDate = $record->return_date?->format('d/m/Y') ?? 'No calculada';

                    return "¿Aprobar vacaciones de {$record->employee->full_name}?\n\nPeríodo: {$record->start_date->format('d/m/Y')} al {$record->end_date->format('d/m/Y')}\nDías hábiles: {$days}\nFecha de reintegro: {$returnDate}";
                })
                ->action(function () {
                    $record = $this->record;
                    VacationService::approve($record);

                    Notification::make()
                        ->title('Vacaciones aprobadas')
                        ->body("Las vacaciones de {$record->employee->full_name} fueron aprobadas exitosamente.")
                        ->success()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('view', ['record' => $record]));
                }),

            Action::make('reject')
                ->label('Rechazar')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => $this->record->status === 'pending')
                ->requiresConfirmation()
                ->modalHeading('Rechazar Solicitud de Vacaciones')
                ->modalDescription(fn () => "¿Está seguro de rechazar las vacaciones de {$this->record->employee->full_name}?")
                ->action(function () {
                    $record = $this->record;
                    VacationService::reject($record);

                    Notification::make()
                        ->title('Vacaciones rechazadas')
                        ->body("Las vacaciones de {$record->employee->full_name} fueron rechazadas.")
                        ->warning()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('view', ['record' => $record]));
                }),

            Action::make('unapprove')
                ->label('Desaprobar')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(fn () => $this->record->status === 'approved' && $this->record->payment_status !== 'paid')
                ->requiresConfirmation()
                ->modalHeading('Desaprobar Solicitud de Vacaciones')
                ->modalDescription(function () {
                    $record = $this->record;
                    $base = "¿Está seguro de revertir la aprobación de las vacaciones de {$record->employee->full_name}? La solicitud volverá a estado pendiente.";

                    if (! $record->start_date->isFuture()) {
                        $base = "⚠️ Atención: estas vacaciones ya comenzaron o ya pasaron. Al desaprobar, los días usados se devolverán al balance. Proceda solo si fue un error de carga.\n\n".$base;
                    }

                    return $base;
                })
                ->modalSubmitActionLabel('Sí, desaprobar')
                ->action(function () {
                    $record = $this->record;
                    VacationService::unapprove($record);

                    Notification::make()
                        ->title('Vacaciones desaprobadas')
                        ->body("La solicitud de {$record->employee->full_name} volvió a estado pendiente.")
                        ->warning()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('view', ['record' => $record]));
                }),

            Action::make('mark_paid')
                ->label('Marcar como pagado')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn () => $this->record->isApproved()
                    && $this->record->payment_status === 'unpaid'
                    && $this->record->payment_method === 'immediate')
                ->modalHeading('Registrar pago vacacional')
                ->modalDescription(fn () => 'Monto a registrar: Gs. '.number_format((float) $this->record->payment_amount, 0, ',', '.'))
                ->modalSubmitActionLabel('Sí, registrar pago')
                ->form([
                    DatePicker::make('paid_at')
                        ->label('Fecha de pago')
                        ->displayFormat('d/m/Y')
                        ->native(false)
                        ->closeOnDateSelection()
                        ->default(now())
                        ->required(),
                ])
                ->action(function (array $data) {
                    VacationService::recordPayment($this->record, Carbon::parse($data['paid_at']));

                    Notification::make()
                        ->success()
                        ->title('Pago registrado')
                        ->body("El pago vacacional de {$this->record->employee->full_name} fue registrado correctamente.")
                        ->send();

                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                }),

            Action::make('generateDocuments')
                ->label('Generar Documentos')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->modalHeading('Generar Documentos de Vacaciones')
                ->modalDescription('Seleccione los documentos que desea generar.')
                ->modalSubmitActionLabel('Generar y Descargar')
                ->modalIcon('heroicon-o-document-text')
                ->form([
                    CheckboxList::make('documents')
                        ->label('Documentos a generar')
                        ->options([
                            'communication' => 'Comunicación de Vacaciones',
                            'usufruct' => 'Solicitud de Usufructo de Vacaciones',
                            'settlement' => 'Recibo de Liquidación de Vacaciones',
                        ])
                        ->default(['communication', 'usufruct', 'settlement'])
                        ->required()
                        ->columns(1)
                        ->descriptions([
                            'communication' => 'Solicitud formal de vacaciones con información del empleado y período',
                            'usufruct' => 'Confirmación de que el empleado usufructuó sus vacaciones',
                            'settlement' => 'Liquidación de salario por vacaciones según Art. 220 C.L.',
                        ]),
                ])
                ->action(function (array $data) {
                    $selectedDocs = $data['documents'];

                    if (empty($selectedDocs)) {
                        return;
                    }

                    $filename = $this->generateDocumentsFile($this->record, $selectedDocs);

                    $this->js("window.open('".route('vacation.documents.download', ['filename' => $filename])."', '_blank')");

                    Notification::make()
                        ->success()
                        ->title('Documentos generados')
                        ->body('Los documentos se están descargando.')
                        ->send();
                })
                ->visible(fn () => $this->record->status === 'approved'),

            EditAction::make()
                ->label('Editar')
                ->icon('heroicon-o-pencil-square')
                ->color('primary')
                ->visible(fn () => $this->record->status !== 'approved'),
        ];
    }

    /**
     * Genera los documentos seleccionados y devuelve el nombre del archivo resultante.
     *
     * @param  mixed  $record
     * @param  array<string>  $selectedDocs
     */
    protected function generateDocumentsFile($record, array $selectedDocs): string
    {
        $tempDir = storage_path('app/public/temp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $this->cleanOldTempFiles($tempDir);

        $uniqueId = Str::uuid();

        if (count($selectedDocs) === 1) {
            $pdfData = $this->getPdfData($record, $selectedDocs[0]);
            $pdf = Pdf::loadView($pdfData['view'], $pdfData['data'])->setPaper('a4', 'portrait');

            $filename = $uniqueId.'_'.$pdfData['filename'];
            $pdf->save($tempDir.'/'.$filename);

            return $filename;
        }

        $employeeNameSlug = Str::slug($record->employee->first_name.' '.$record->employee->last_name);
        $zipFilename = $uniqueId.'_vacaciones-'.$employeeNameSlug.'-'.$record->employee->ci.'.zip';
        $zipPath = $tempDir.'/'.$zipFilename;
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \Exception('No se pudo crear el archivo ZIP');
        }

        foreach ($selectedDocs as $type) {
            $pdfData = $this->getPdfData($record, $type);
            $pdf = Pdf::loadView($pdfData['view'], $pdfData['data'])->setPaper('a4', 'portrait');
            $zip->addFromString($pdfData['filename'], $pdf->output());
        }

        $zip->close();

        return $zipFilename;
    }

    /**
     * Elimina archivos temporales con más de 1 hora de antigüedad.
     */
    protected function cleanOldTempFiles(string $dir): void
    {
        $files = glob($dir.'/*');
        $now = time();

        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file)) > 3600) {
                unlink($file);
            }
        }
    }

    /**
     * Devuelve la vista, nombre de archivo y datos para cada tipo de PDF.
     *
     * @param  mixed  $record
     * @return array<string, mixed>
     */
    protected function getPdfData($record, string $type): array
    {
        $company = $record->employee->company;

        $logoPath = $company?->logo;
        $companyLogo = $logoPath ? storage_path('app/public/'.$logoPath) : null;
        $companyLogo = $companyLogo && file_exists($companyLogo) ? $companyLogo : null;

        $companyName = $company?->name ?? '';
        $companyRuc = $company?->ruc ?? '';
        $companyAddress = $company?->address ?? '';
        $companyPhone = $company?->phone ?? '';
        $companyEmail = $company?->email ?? '';
        $employerNumber = $company?->employer_number ?? '';
        $city = $company?->city ?? '';

        $employeeNameSlug = Str::slug($record->employee->first_name.' '.$record->employee->last_name);

        $baseData = compact(
            'companyLogo', 'companyName', 'companyRuc', 'companyAddress',
            'companyPhone', 'companyEmail', 'employerNumber', 'city'
        );

        return match ($type) {
            'communication' => [
                'view' => 'pdf.vacation-form',
                'filename' => "comunicacion-vacaciones-{$employeeNameSlug}-{$record->employee->ci}.pdf",
                'data' => array_merge(['vacation' => $record], $baseData),
            ],

            'usufruct' => [
                'view' => 'pdf.vacation-usufruct-notice',
                'filename' => "solicitud-usufructo-{$employeeNameSlug}-{$record->employee->ci}.pdf",
                'data' => array_merge(['vacation' => $record], $baseData),
            ],

            'settlement' => $this->buildSettlementData($record, $baseData, $employeeNameSlug),

            default => throw new \InvalidArgumentException("Tipo de documento no válido: {$type}"),
        };
    }

    /**
     * Calcula y devuelve los datos del recibo de liquidación de vacaciones.
     *
     * @param  mixed  $record
     * @param  array<string, mixed>  $baseData
     * @return array<string, mixed>
     */
    private function buildSettlementData($record, array $baseData, string $employeeNameSlug): array
    {
        $employee = $record->employee;
        $days = $record->business_days ?? $record->total_days;
        $baseSalary = (float) ($employee->base_salary ?? 0);
        $dailySalary = $baseSalary / 30;
        $totalSalary = $dailySalary * $days;
        $ipsRate = app(\App\Settings\PayrollSettings::class)->ips_employee_rate;
        $ipsDeduction = round($totalSalary * ($ipsRate / 100));
        $netAmount = $totalSalary - $ipsDeduction;

        return [
            'view' => 'pdf.vacation-settlement-receipt',
            'filename' => "recibo-liquidacion-{$employeeNameSlug}-{$record->employee->ci}.pdf",
            'data' => array_merge(['vacation' => $record], $baseData, [
                'days' => $days,
                'dailySalary' => $dailySalary,
                'subTotal' => $totalSalary,
                'totalSalary' => $totalSalary,
                'ipsRate' => $ipsRate,
                'ipsDeduction' => $ipsDeduction,
                'totalDeductions' => $ipsDeduction,
                'netAmount' => $netAmount,
            ]),
        ];
    }
}
