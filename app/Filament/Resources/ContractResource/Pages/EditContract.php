<?php

namespace App\Filament\Resources\ContractResource\Pages;

use App\Models\Contract;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\ContractResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class EditContract extends EditRecord
{
    protected static string $resource = ContractResource::class;

    /**
     * Define las acciones disponibles en el encabezado de la página de edición.
     *
     * @return array
     */
    protected function getHeaderActions(): array
    {
        return [
            // --- Acciones de documento ---

            Action::make('generate_pdf')
                ->label('Generar PDF')
                ->icon('heroicon-o-printer')
                ->color('info')
                ->url(fn(Contract $record) => route('contracts.pdf', $record))
                ->openUrlInNewTab(),

            Action::make('upload_signed')
                ->label(fn(Contract $record) => $record->document_path ? 'Reemplazar Firmado' : 'Subir Firmado')
                ->icon(fn(Contract $record) => $record->document_path ? 'heroicon-o-arrow-path' : 'heroicon-o-arrow-up-tray')
                ->color(fn(Contract $record) => $record->document_path ? 'warning' : 'success')
                ->visible(fn(Contract $record) => $record->status === 'active')
                ->form([
                    FileUpload::make('document_path')
                        ->label('Contrato Firmado (PDF)')
                        ->disk('public')
                        ->directory('contracts')
                        ->acceptedFileTypes(['application/pdf'])
                        ->maxSize(10240)
                        ->required()
                        ->helperText('Suba el documento escaneado del contrato firmado. Solo PDF, máximo 10 MB.'),
                ])
                ->modalHeading(fn(Contract $record) => $record->document_path ? 'Reemplazar Documento Firmado' : 'Subir Contrato Firmado')
                ->modalDescription(fn(Contract $record) => $record->document_path
                    ? 'El documento actual será reemplazado por el nuevo archivo.'
                    : 'Suba el contrato escaneado con las firmas correspondientes.')
                ->action(function (Contract $record, array $data) {
                    if ($record->document_path && Storage::disk('public')->exists($record->document_path)) {
                        Storage::disk('public')->delete($record->document_path);
                    }

                    $record->update(['document_path' => $data['document_path']]);

                    Notification::make()
                        ->title('Documento subido')
                        ->body('El contrato firmado se ha guardado correctamente.')
                        ->success()
                        ->send();
                }),

            Action::make('download_signed')
                ->label('Descargar Firmado')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn(Contract $record) => (bool) $record->document_path)
                ->action(fn(Contract $record) => response()->download(
                    Storage::disk('public')->path($record->document_path),
                    "contrato_firmado_{$record->employee->ci}_{$record->start_date->format('Y_m_d')}.pdf"
                )),

            // --- Acciones de gestión ---

            Action::make('renew')
                ->label('Renovar')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->visible(fn(Contract $record) => $record->status === 'active' && $record->type !== 'indefinido')
                ->requiresConfirmation()
                ->modalHeading('Renovar Contrato')
                ->modalDescription(function (Contract $record) {
                    $msg = "Se creará un nuevo contrato para {$record->employee->full_name} y el contrato actual pasará a estado 'Renovado'.";
                    if ($record->wouldBecomeIndefiniteOnRenewal()) {
                        $msg .= "\n\n⚠ Art. 53 CLT: Este contrato a plazo fijo ya fue renovado anteriormente. Al renovar nuevamente, el nuevo contrato será automáticamente de tipo INDEFINIDO.";
                    }
                    return $msg;
                })
                ->form([
                    DatePicker::make('start_date')
                        ->label('Fecha de Inicio')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->required()
                        ->default(fn(Contract $record) => $record->end_date ?? now())
                        ->closeOnDateSelection(),

                    DatePicker::make('end_date')
                        ->label('Fecha de Finalización')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->closeOnDateSelection()
                        ->visible(fn(Contract $record) => !$record->wouldBecomeIndefiniteOnRenewal())
                        ->required(fn(Contract $record) => !$record->wouldBecomeIndefiniteOnRenewal())
                        ->helperText(fn(Contract $record) => $record->type === 'plazo_fijo' ? 'Art. 53 CLT: Máximo 1 año' : null),

                    TextInput::make('salary')
                        ->label(fn(Contract $record) => $record->salary_type === 'jornal' ? 'Jornal Diario' : 'Salario Mensual')
                        ->numeric()
                        ->required()
                        ->prefix('Gs.')
                        ->suffix(fn(Contract $record) => $record->salary_type === 'jornal' ? '/día' : '/mes')
                        ->default(fn(Contract $record) => $record->salary),

                    Textarea::make('notes')
                        ->label('Notas')
                        ->placeholder('Notas sobre la renovación...')
                        ->rows(2),
                ])
                ->action(function (Contract $record, array $data) {
                    $newContract = $record->renew($data);
                    $newContract->update(['created_by_id' => Auth::id()]);
                    $newContract->syncToEmployee();

                    $typeMsg = $newContract->type === 'indefinido' && $record->type !== 'indefinido'
                        ? ' (convertido a INDEFINIDO por Art. 53 CLT)'
                        : '';

                    Notification::make()
                        ->title('Contrato Renovado')
                        ->body("Se creó un nuevo contrato{$typeMsg} para {$record->employee->full_name}.")
                        ->success()
                        ->send();

                    return redirect(ContractResource::getUrl('edit', ['record' => $newContract]));
                }),

            Action::make('terminate')
                ->label('Terminar')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn(Contract $record) => $record->status === 'active')
                ->requiresConfirmation()
                ->modalHeading('Terminar Contrato')
                ->modalDescription(fn(Contract $record) => "¿Está seguro de que desea terminar el contrato de {$record->employee->full_name}?")
                ->form([
                    Textarea::make('termination_notes')
                        ->label('Motivo de terminación')
                        ->placeholder('Ingrese el motivo...')
                        ->rows(3),
                ])
                ->action(function (Contract $record, array $data) {
                    $record->update([
                        'status' => 'terminated',
                        'notes'  => $record->notes
                            ? $record->notes . "\n\nTerminación: " . ($data['termination_notes'] ?? 'Sin motivo especificado')
                            : "Terminación: " . ($data['termination_notes'] ?? 'Sin motivo especificado'),
                    ]);

                    Notification::make()
                        ->title('Contrato Terminado')
                        ->body("El contrato de {$record->employee->full_name} ha sido terminado.")
                        ->warning()
                        ->persistent()
                        ->actions([
                            \Filament\Notifications\Actions\Action::make('create_liquidacion')
                                ->label('Crear Liquidación')
                                ->url(route('filament.admin.resources.liquidaciones.create', [
                                    'employee_id' => $record->employee_id,
                                ]))
                                ->button(),
                        ])
                        ->send();
                }),

            DeleteAction::make(),
        ];
    }

    /**
     * Después de guardar el contrato, sincroniza los cambios al empleado si el contrato está activo.
     *
     * @return void
     */
    protected function afterSave(): void
    {
        if ($this->record->status === 'active') {
            $this->record->syncToEmployee();
        }
    }

    /**
     * Personaliza el mensaje de notificación después de guardar el contrato.
     *
     * @return string|null
     */
    protected function getSavedNotificationTitle(): ?string
    {
        return 'Contrato actualizado exitosamente';
    }
}
