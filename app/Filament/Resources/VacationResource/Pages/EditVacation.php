<?php

namespace App\Filament\Resources\VacationResource\Pages;

use App\Filament\Resources\VacationResource;
use App\Models\Employee;
use App\Services\VacationService;
use App\Settings\GeneralSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class EditVacation extends EditRecord
{
    protected static string $resource = VacationResource::class;

    /**
     * Define las acciones del encabezado de la página.
     *
     * @return array
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Aprobar')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn() => $this->record->status === 'pending')
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

                    $this->refreshFormData(['status']);
                }),

            Action::make('reject')
                ->label('Rechazar')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn() => $this->record->status === 'pending')
                ->requiresConfirmation()
                ->modalHeading('Rechazar Solicitud de Vacaciones')
                ->modalDescription(fn() => "¿Está seguro de rechazar las vacaciones de {$this->record->employee->full_name}?")
                ->action(function () {
                    $record = $this->record;
                    VacationService::reject($record);

                    Notification::make()
                        ->title('Vacaciones rechazadas')
                        ->body("Las vacaciones de {$record->employee->full_name} fueron rechazadas.")
                        ->warning()
                        ->send();

                    $this->refreshFormData(['status']);
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
                    $record = $this->record;
                    $selectedDocs = $data['documents'];

                    if (empty($selectedDocs)) {
                        return;
                    }

                    $filename = $this->generateDocumentsFile($record, $selectedDocs);

                    // Redirigir a la descarga
                    $this->js("window.open('" . route('vacation.documents.download', ['filename' => $filename]) . "', '_blank')");

                    Notification::make()
                        ->success()
                        ->title('Documentos generados')
                        ->body('Los documentos se están descargando.')
                        ->send();
                })
                ->visible(fn() => $this->record->status === 'approved'),

            DeleteAction::make()
                ->before(function () {
                    VacationService::releaseOnDelete($this->record);
                }),
        ];
    }

    /**
     * Genera los documentos y devuelve el nombre del archivo.
     */
    protected function generateDocumentsFile($record, array $selectedDocs): string
    {
        $tempDir = storage_path('app/public/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // Limpiar archivos antiguos (más de 1 hora)
        $this->cleanOldTempFiles($tempDir);

        $uniqueId = Str::uuid();

        if (count($selectedDocs) === 1) {
            // Un solo documento - generar PDF directamente
            $pdfData = $this->getPdfData($record, $selectedDocs[0]);
            $pdf = Pdf::loadView($pdfData['view'], $pdfData['data'])
                ->setPaper('a4', 'portrait');

            $filename = $uniqueId . '_' . $pdfData['filename'];
            $pdf->save($tempDir . '/' . $filename);

            return $filename;
        }

        // Múltiples documentos - generar ZIP
        $employeeNameSlug = Str::slug($record->employee->first_name . ' ' . $record->employee->last_name);
        $zipFilename = $uniqueId . '_vacaciones-' . $employeeNameSlug . '-' . $record->employee->ci . '.zip';
        $zipPath = $tempDir . '/' . $zipFilename;
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \Exception('No se pudo crear el archivo ZIP');
        }

        foreach ($selectedDocs as $type) {
            $pdfData = $this->getPdfData($record, $type);
            $pdf = Pdf::loadView($pdfData['view'], $pdfData['data'])
                ->setPaper('a4', 'portrait');

            $zip->addFromString($pdfData['filename'], $pdf->output());
        }

        $zip->close();

        return $zipFilename;
    }

    /**
     * Limpia archivos temporales antiguos.
     */
    protected function cleanOldTempFiles(string $dir): void
    {
        $files = glob($dir . '/*');
        $now = time();

        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file)) > 3600) {
                unlink($file);
            }
        }
    }

    /**
     * Obtiene los datos para generar cada tipo de PDF.
     */
    protected function getPdfData($record, string $type): array
    {
        $settings = app(GeneralSettings::class);

        // Obtener datos de la empresa del empleado, si no usar GeneralSettings
        $company = $record->employee->company;

        // Obtener ruta del logo (empresa o general)
        $logoPath = $company?->logo ?? $settings->company_logo;
        $companyLogo = $logoPath ? storage_path('app/public/' . $logoPath) : null;
        $companyLogo = $companyLogo && file_exists($companyLogo) ? $companyLogo : null;

        $companyName = $company?->name ?? $settings->company_name;
        $companyRuc = $company?->ruc ?? $settings->company_ruc ?? '';
        $companyAddress = $company?->address ?? $settings->company_address ?? '';
        $companyPhone = $company?->phone ?? $settings->company_phone ?? '';
        $companyEmail = $company?->email ?? $settings->company_email ?? '';
        $employerNumber = $company?->employer_number ?? $settings->company_employer_number ?? '';
        $city = $company?->city ?? $settings->company_city ?? '';

        $employeeNameSlug = Str::slug($record->employee->first_name . ' ' . $record->employee->last_name);

        switch ($type) {
            case 'communication':
                return [
                    'view' => 'pdf.vacation-form',
                    'filename' => "comunicacion-vacaciones-{$employeeNameSlug}-{$record->employee->ci}.pdf",
                    'data' => [
                        'vacation' => $record,
                        'companyLogo' => $companyLogo,
                        'companyName' => $companyName,
                        'companyRuc' => $companyRuc,
                        'companyAddress' => $companyAddress,
                        'companyPhone' => $companyPhone,
                        'companyEmail' => $companyEmail,
                        'employerNumber' => $employerNumber,
                        'city' => $city,
                    ],
                ];

            case 'usufruct':
                return [
                    'view' => 'pdf.vacation-usufruct-notice',
                    'filename' => "solicitud-usufructo-{$employeeNameSlug}-{$record->employee->ci}.pdf",
                    'data' => [
                        'vacation' => $record,
                        'companyLogo' => $companyLogo,
                        'companyName' => $companyName,
                        'companyRuc' => $companyRuc,
                        'companyAddress' => $companyAddress,
                        'companyPhone' => $companyPhone,
                        'companyEmail' => $companyEmail,
                        'employerNumber' => $employerNumber,
                        'city' => $city,
                    ],
                ];

            case 'settlement':
                // Calcular datos de liquidación
                $employee = $record->employee;
                $days = $record->business_days ?? $record->total_days;
                $baseSalary = (float) ($employee->base_salary ?? 0);
                $dailySalary = $baseSalary / 30;
                $subTotal = $dailySalary * $days;
                $totalSalary = $subTotal;
                $ipsRate = app(\App\Settings\PayrollSettings::class)->ips_employee_rate;
                $ipsDeduction = round($totalSalary * ($ipsRate / 100));
                $totalDeductions = $ipsDeduction;
                $netAmount = $totalSalary - $totalDeductions;

                return [
                    'view' => 'pdf.vacation-settlement-receipt',
                    'filename' => "recibo-liquidacion-{$employeeNameSlug}-{$record->employee->ci}.pdf",
                    'data' => [
                        'vacation' => $record,
                        'companyLogo' => $companyLogo,
                        'companyName' => $companyName,
                        'companyRuc' => $companyRuc,
                        'companyAddress' => $companyAddress,
                        'companyPhone' => $companyPhone,
                        'companyEmail' => $companyEmail,
                        'employerNumber' => $employerNumber,
                        'city' => $city,
                        'days' => $days,
                        'dailySalary' => $dailySalary,
                        'subTotal' => $subTotal,
                        'totalSalary' => $totalSalary,
                        'ipsRate' => $ipsRate,
                        'ipsDeduction' => $ipsDeduction,
                        'totalDeductions' => $totalDeductions,
                        'netAmount' => $netAmount,
                    ],
                ];

            default:
                throw new \InvalidArgumentException("Tipo de documento no válido: {$type}");
        }
    }

    /**
     * Define la URL a la que se redirige después de guardar.
     *
     * @return string
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Mutar los datos del formulario antes de guardarlos.
     *
     * @param array $data
     * @return array
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Recalcular balance si cambian las fechas o empleado
        if (!empty($data['employee_id']) && !empty($data['start_date'])) {
            $employee = Employee::find($data['employee_id']);
            if (!$employee instanceof Employee) {
                return $data;
            }
            $year = Carbon::parse($data['start_date'])->year;
            $balance = VacationService::getOrCreateBalance($employee, $year);
            $data['vacation_balance_id'] = $balance->id;
        }

        return $data;
    }

    /**
     * Define las acciones antes de guardar el registro.
     *
     * @return void
     */
    protected function beforeSave(): void
    {
        $record = $this->record;
        $oldBusinessDays = $record->getOriginal('business_days') ?? 0;
        $newBusinessDays = $this->data['business_days'] ?? 0;

        // Si cambiaron los días y está pendiente, actualizar el balance
        if ($record->isPending() && $oldBusinessDays !== $newBusinessDays) {
            if ($record->vacation_balance_id && $record->vacationBalance) {
                // Liberar los días anteriores y agregar los nuevos
                $record->vacationBalance->releasePendingDays($oldBusinessDays);
                $record->vacationBalance->addPendingDays($newBusinessDays);
            }
        }
    }

    /**
     * Define el título de la notificación después de guardar.
     *
     * @return string|null
     */
    protected function getSavedNotificationTitle(): ?string
    {
        return 'Solicitud de vacaciones actualizada';
    }
}
