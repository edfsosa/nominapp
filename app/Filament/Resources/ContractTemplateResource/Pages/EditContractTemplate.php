<?php

namespace App\Filament\Resources\ContractTemplateResource\Pages;

use App\Filament\Resources\ContractTemplateResource;
use App\Models\Company;
use App\Models\ContractTemplate;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Get;
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

            Action::make('copy_to_company')
                ->label('Copiar a empresa')
                ->icon('heroicon-o-document-duplicate')
                ->color('info')
                ->visible(fn () => Company::active()->where('id', '!=', $this->record->company_id)->exists())
                ->form([
                    Select::make('target_company_id')
                        ->label('Empresa destino')
                        ->options(fn () => Company::active()
                            ->where('id', '!=', $this->record->company_id)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->toArray()
                        )
                        ->required()
                        ->live(),

                    \Filament\Forms\Components\Placeholder::make('overwrite_warning')
                        ->label('')
                        ->content(function (Get $get): ?string {
                            $targetId = $get('target_company_id');
                            if (! $targetId) {
                                return null;
                            }
                            $exists = ContractTemplate::where('company_id', $targetId)
                                ->where('type', $this->record->type)
                                ->exists();
                            if (! $exists) {
                                return null;
                            }
                            $companyName = Company::find($targetId)?->name;

                            return "⚠️ Ya existe una plantilla de tipo \"{$this->record->type}\" en {$companyName}. Se sobreescribirá con el contenido actual.";
                        })
                        ->visible(fn (Get $get): bool => filled($get('target_company_id')) &&
                            ContractTemplate::where('company_id', $get('target_company_id'))
                                ->where('type', $this->record->type)
                                ->exists()
                        ),
                ])
                ->modalHeading('Copiar plantilla a otra empresa')
                ->modalSubmitActionLabel('Copiar')
                ->action(function (array $data) {
                    $targetId = $data['target_company_id'];

                    ContractTemplate::updateOrCreate(
                        ['company_id' => $targetId, 'type' => $this->record->type],
                        [
                            'intro_text'                  => $this->record->intro_text,
                            'body'                        => $this->record->body,
                            'closing_text'                => $this->record->closing_text,
                            'signature_notes'             => $this->record->signature_notes,
                            'document_title'              => $this->record->document_title,
                            'document_subtitle'           => $this->record->document_subtitle,
                            'document_art_reference'      => $this->record->document_art_reference,
                            'signature_employee_label'    => $this->record->signature_employee_label,
                            'signature_employer_label'    => $this->record->signature_employer_label,
                            'signature_employer_sublabel' => $this->record->signature_employer_sublabel,
                            'show_header'                 => $this->record->show_header,
                            'show_footer'                 => $this->record->show_footer,
                        ]
                    );

                    $companyName = Company::find($targetId)?->name;

                    Notification::make()
                        ->success()
                        ->title('Plantilla copiada')
                        ->body("La plantilla fue copiada a {$companyName} correctamente.")
                        ->send();
                }),

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
