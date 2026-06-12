<?php

namespace App\Filament\Resources\ContractTemplateResource\Pages;

use App\Filament\Resources\ContractTemplateResource;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractTemplate;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Tab;
use Illuminate\Database\Eloquent\Builder;

/** Listado de plantillas de contrato con un tab por empresa activa. */
class ListContractTemplates extends ListRecords
{
    protected static string $resource = ContractTemplateResource::class;

    /**
     * Retorna un tab por empresa activa, filtrando las plantillas por company_id.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $companies = Company::active()->orderBy('name')->get();

        $tabs = [];
        foreach ($companies as $company) {
            $count = ContractTemplate::where('company_id', $company->id)->count();
            $tabs[(string) $company->id] = Tab::make($company->name)
                ->badge($count)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('company_id', $company->id));
        }

        return $tabs;
    }

    /**
     * Retorna las acciones del encabezado: creación de nueva plantilla para la empresa activa.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('create_template')
                ->label('Nueva Plantilla')
                ->icon('heroicon-o-plus')
                ->visible(function () {
                    if ($this->activeTab === null) {
                        return false;
                    }
                    $companyId = (int) $this->activeTab;
                    $existing = ContractTemplate::where('company_id', $companyId)->count();

                    return $existing < count(Contract::getTypeOptions());
                })
                ->form([
                    Select::make('type')
                        ->label('Tipo de Contrato')
                        ->options(function () {
                            $companyId = (int) $this->activeTab;
                            $existing = ContractTemplate::where('company_id', $companyId)->pluck('type')->toArray();

                            return collect(Contract::getTypeOptions())
                                ->reject(fn ($label, $key) => in_array($key, $existing))
                                ->toArray();
                        })
                        ->native(false)
                        ->required(),
                ])
                ->action(function (array $data) {
                    ContractTemplate::create([
                        'company_id' => (int) $this->activeTab,
                        'type' => $data['type'],
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Plantilla creada')
                        ->body('Podés editar el contenido ahora.')
                        ->send();
                }),
        ];
    }
}
