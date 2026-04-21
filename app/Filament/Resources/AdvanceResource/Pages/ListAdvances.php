<?php

namespace App\Filament\Resources\AdvanceResource\Pages;

use App\Exports\AdvancesExport;
use App\Filament\Resources\AdvanceResource;
use App\Models\Advance;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;

class ListAdvances extends ListRecords
{
    protected static string $resource = AdvanceResource::class;

    /** @var array<string, mixed>|null Cache de conteos para badges de tabs. */
    protected ?array $advanceCounts = null;

    /**
     * Obtiene conteos por estado para los badges de tabs (cacheado para evitar N+1).
     *
     * @return array<string, mixed>
     */
    protected function getAdvanceCounts(): array
    {
        if ($this->advanceCounts === null) {
            $counts = Advance::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->toArray();

            $this->advanceCounts = [
                'total' => array_sum($counts),
                'by_status' => $counts,
            ];
        }

        return $this->advanceCounts;
    }

    /**
     * Define las acciones del encabezado de la página.
     *
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate_bulk')
                ->label('Generar Adelantos')
                ->icon('heroicon-o-bolt')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Generar Adelantos Masivos')
                ->modalDescription('Se crearán adelantos en estado Pendiente para los empleados seleccionados. Se respetará el límite de adelantos por período y el tope máximo por empleado.')
                ->modalSubmitActionLabel('Generar')
                ->modalWidth('4xl')
                ->form([
                    Grid::make(2)
                        ->schema([
                            Select::make('company_id')
                                ->label('Empresa')
                                ->options(Company::orderBy('name')->get()->pluck('display_name', 'id'))
                                ->native(false)
                                ->live()
                                ->placeholder('Todas las empresas')
                                ->afterStateUpdated(function (Set $set) {
                                    $set('branch_id', null);
                                    $set('employee_ids', []);
                                }),

                            Select::make('branch_id')
                                ->label('Sucursal')
                                ->options(fn (Get $get) => Branch::when(
                                    $get('company_id'),
                                    fn ($q, $id) => $q->where('company_id', $id)
                                )->orderBy('name')->pluck('name', 'id'))
                                ->native(false)
                                ->live()
                                ->placeholder('Todas las sucursales')
                                ->afterStateUpdated(fn (Set $set) => $set('employee_ids', [])),
                        ]),

                    Grid::make(2)
                        ->schema([
                            TextInput::make('amount')
                                ->label('Monto (Gs.)')
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->prefix('Gs.')
                                ->helperText('Se verificará que no supere el tope máximo de cada empleado.'),

                            Textarea::make('notes')
                                ->label('Notas')
                                ->placeholder('Motivo u observaciones...')
                                ->rows(2),
                        ]),

                    Select::make('employee_ids')
                        ->label('Empleados')
                        ->multiple()
                        ->native(false)
                        ->options(fn (Get $get) => Employee::query()
                            ->where('status', 'active')
                            ->whereHas('activeContract', fn ($c) => $c->whereNotNull('salary')->where('salary', '>', 0))
                            ->when($get('branch_id'), fn ($q, $id) => $q->where('branch_id', $id))
                            ->when(
                                $get('company_id') && ! $get('branch_id'),
                                fn ($q) => $q->whereHas('branch', fn ($b) => $b->where('company_id', $get('company_id')))
                            )
                            ->orderBy('first_name')
                            ->orderBy('last_name')
                            ->get()
                            ->pluck('full_name_with_ci', 'id')
                            ->toArray())
                        ->searchable()
                        ->helperText('Dejar vacío para aplicar a todos los empleados del filtro seleccionado.'),
                ])
                ->action(function (array $data) {
                    $amount = (float) $data['amount'];
                    $employeeIds = $data['employee_ids'] ?? [];
                    $maxPerPeriod = app(\App\Settings\PayrollSettings::class)->advance_max_per_period;

                    $query = Employee::query()
                        ->where('status', 'active')
                        ->whereHas('activeContract', fn ($c) => $c->whereNotNull('salary')->where('salary', '>', 0))
                        ->with('activeContract');

                    if (! empty($employeeIds)) {
                        $query->whereIn('id', $employeeIds);
                    } elseif (! empty($data['branch_id'])) {
                        $query->where('branch_id', $data['branch_id']);
                    } elseif (! empty($data['company_id'])) {
                        $query->whereHas('branch', fn ($b) => $b->where('company_id', $data['company_id']));
                    }

                    $created = 0;
                    $skippedLimit = 0;
                    $skippedAmount = 0;

                    foreach ($query->get() as $employee) {
                        if ($maxPerPeriod > 0) {
                            $activeCount = Advance::where('employee_id', $employee->id)
                                ->whereIn('status', ['pending', 'approved'])
                                ->count();

                            if ($activeCount >= $maxPerPeriod) {
                                $skippedLimit++;
                                continue;
                            }
                        }

                        $max = $employee->getMaxAdvanceAmount();
                        if ($max !== null && $amount > $max) {
                            $skippedAmount++;
                            continue;
                        }

                        Advance::create([
                            'employee_id' => $employee->id,
                            'amount' => $amount,
                            'status' => 'pending',
                            'notes' => $data['notes'] ?? null,
                        ]);
                        $created++;
                    }

                    $body = "Se crearon {$created} adelantos en estado Pendiente.";
                    if ($skippedLimit > 0) {
                        $body .= " {$skippedLimit} omitidos por alcanzar el límite de adelantos por período.";
                    }
                    if ($skippedAmount > 0) {
                        $body .= " {$skippedAmount} omitidos por superar el tope máximo del empleado.";
                    }

                    Notification::make()
                        ->title('Generación Completada')
                        ->body($body)
                        ->{($skippedLimit + $skippedAmount) > 0 ? 'warning' : 'success'}()
                        ->send()
                        ->persistent();
                }),

            Action::make('export')
                ->label('Exportar Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Exportar Adelantos')
                ->modalDescription('Se exportarán todos los adelantos registrados.')
                ->modalSubmitActionLabel('Exportar')
                ->action(function () {
                    Notification::make()
                        ->title('Exportación iniciada')
                        ->body('El archivo se descargará en un momento.')
                        ->success()
                        ->send();

                    return Excel::download(
                        new AdvancesExport(),
                        'adelantos_' . now()->format('Y_m_d_H_i_s') . '.xlsx'
                    );
                }),

            CreateAction::make()
                ->label('Nuevo Adelanto')
                ->icon('heroicon-o-plus'),
        ];
    }

    /**
     * Define las pestañas de filtrado por estado.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $counts = $this->getAdvanceCounts();
        $byStatus = $counts['by_status'];

        return [
            'all' => Tab::make('Todos')
                ->badge($counts['total']),

            'pending' => Tab::make('Pendientes')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'pending'))
                ->badge($byStatus['pending'] ?? 0)
                ->badgeColor('warning'),

            'approved' => Tab::make('Aprobados')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'approved'))
                ->badge($byStatus['approved'] ?? 0)
                ->badgeColor('info'),

            'paid' => Tab::make('Pagados')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'paid'))
                ->badge($byStatus['paid'] ?? 0)
                ->badgeColor('success'),

            'rejected' => Tab::make('Rechazados')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'rejected'))
                ->badge($byStatus['rejected'] ?? 0)
                ->badgeColor('danger'),

            'cancelled' => Tab::make('Cancelados')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'cancelled'))
                ->badge($byStatus['cancelled'] ?? 0)
                ->badgeColor('gray'),
        ];
    }

    /**
     * Pestaña activa por defecto.
     */
    public function getDefaultActiveTab(): string|int|null
    {
        return 'all';
    }
}
