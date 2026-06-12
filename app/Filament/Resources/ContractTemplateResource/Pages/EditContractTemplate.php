<?php

namespace App\Filament\Resources\ContractTemplateResource\Pages;

use App\Filament\Resources\ContractTemplateResource;
use App\Models\ContractTemplate;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

/** Edición de la plantilla de cuerpo/cláusulas para un tipo de contrato. */
class EditContractTemplate extends EditRecord
{
    protected static string $resource = ContractTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview_pdf')
                ->label('Vista Previa PDF')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->url(fn () => route('contract-templates.preview', $this->record))
                ->openUrlInNewTab(),

            Action::make('restore_defaults')
                ->label('Restaurar valores base')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('¿Restaurar todos los textos al valor base?')
                ->modalDescription('Esto reemplazará el texto de introducción, cuerpo, cierre y notas de firma con el contenido base del sistema. Los campos de presentación (título, subtítulo, etiquetas) no se modificarán. Esta acción no se puede deshacer.')
                ->modalSubmitActionLabel('Sí, restaurar')
                ->action(function () {
                    $this->record->update([
                        'intro_text'      => ContractTemplate::getDefaultIntroText(),
                        'body'            => ContractTemplate::getDefaultBodyText(),
                        'closing_text'    => ContractTemplate::getDefaultClosingText(),
                        'signature_notes' => ContractTemplate::getDefaultSignatureNotes(),
                    ]);

                    $this->fillForm();

                    Notification::make()
                        ->success()
                        ->title('Textos restaurados')
                        ->body('Las 4 secciones de texto fueron restauradas al contenido base.')
                        ->send();
                }),

            Action::make('back')
                ->label('Volver al listado')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(ContractTemplateResource::getUrl('index')),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return ContractTemplateResource::getUrl('index');
    }

    /**
     * Retorna la notificación de éxito al guardar la plantilla.
     */
    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Plantilla guardada')
            ->body('La plantilla de cláusulas fue actualizada correctamente.');
    }
}
