<?php

namespace App\Filament\Resources\ContractTemplateResource\Pages;

use App\Filament\Resources\ContractTemplateResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

/** Vista de detalle de plantilla de contrato con historial de cambios. */
class ViewContractTemplate extends ViewRecord
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

            Action::make('edit')
                ->label('Editar')
                ->icon('heroicon-o-pencil-square')
                ->url(fn () => ContractTemplateResource::getUrl('edit', ['record' => $this->record])),
        ];
    }
}
