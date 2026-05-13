<?php

namespace App\Filament\Resources\AdvanceResource\Pages;

use App\Exports\AdvancesExport;
use App\Exports\AdvancesTemplateExport;
use App\Filament\Resources\AdvanceResource;
use App\Imports\AdvancesImport;
use App\Models\Advance;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Services\BankPaymentExportService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
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
            CreateAction::make()
                ->label('Nuevo Adelanto')
                ->icon('heroicon-o-plus'),

            ActionGroup::make([
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

                                Select::make('payment_method')
                                    ->label('Método de pago')
                                    ->options(Advance::getPaymentMethodOptions())
                                    ->default('transfer')
                                    ->required()
                                    ->native(false),
                            ]),

                        Textarea::make('notes')
                            ->label('Notas')
                            ->placeholder('Motivo u observaciones...')
                            ->rows(2),

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
                                'payment_method' => $data['payment_method'],
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

                Action::make('download_template')
                    ->label('Descargar Plantilla')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->modalHeading('Descargar Plantilla de Importación')
                    ->modalDescription('Se generará un archivo Excel con los empleados activos pre-cargados. Completá el Monto y ajustá el Método de pago para cada empleado.')
                    ->modalSubmitActionLabel('Descargar')
                    ->form([
                        Grid::make(2)
                            ->schema([
                                Select::make('company_id')
                                    ->label('Empresa')
                                    ->options(Company::orderBy('name')->get()->pluck('display_name', 'id'))
                                    ->native(false)
                                    ->live()
                                    ->placeholder('Todas las empresas')
                                    ->afterStateUpdated(fn (Set $set) => $set('branch_id', null)),

                                Select::make('branch_id')
                                    ->label('Sucursal')
                                    ->options(fn (Get $get) => Branch::when(
                                        $get('company_id'),
                                        fn ($q, $id) => $q->where('company_id', $id)
                                    )->orderBy('name')->pluck('name', 'id'))
                                    ->native(false)
                                    ->placeholder('Todas las sucursales'),
                            ]),
                    ])
                    ->action(function (array $data) {
                        return Excel::download(
                            new AdvancesTemplateExport($data['company_id'] ?? null, $data['branch_id'] ?? null),
                            'plantilla_adelantos_'.now()->format('Y_m_d_H_i_s').'.xlsx'
                        );
                    }),

                Action::make('import_advances')
                    ->label('Importar Adelantos')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('gray')
                    ->modalHeading('Importar Adelantos desde Excel')
                    ->modalSubmitActionLabel('Importar')
                    ->form([
                        Placeholder::make('import_info')
                            ->label('')
                            ->content(new HtmlString(
                                '<p class="text-sm text-gray-600 dark:text-gray-400">'.
                                'Subí el archivo Excel completado a partir de la plantilla. '.
                                'Se crearán adelantos en estado <strong>Pendiente</strong> para las filas válidas. '.
                                'Las columnas CI, Nombre y Sucursal son de referencia y no se modifican.'.
                                '</p>'
                            )),

                        FileUpload::make('file')
                            ->label('Archivo Excel (.xlsx)')
                            ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'])
                            ->required()
                            ->disk('local')
                            ->directory('imports/advances')
                            ->maxSize(5120),
                    ])
                    ->action(function (array $data) {
                        $path = Storage::disk('local')->path($data['file']);

                        $import = new AdvancesImport;
                        Excel::import($import, $path);

                        Storage::disk('local')->delete($data['file']);

                        $created = $import->created;
                        $failures = $import->failures;

                        if ($created === 0 && count($failures) === 0) {
                            Notification::make()
                                ->warning()
                                ->title('Sin datos')
                                ->body('El archivo no contenía filas para procesar.')
                                ->send();

                            return;
                        }

                        $body = "Se crearon {$created} adelanto(s) en estado Pendiente.";

                        if (count($failures) > 0) {
                            $lines = array_map(
                                fn ($f) => "• Fila {$f['row']} ({$f['name']}): {$f['reason']}",
                                array_slice($failures, 0, 10)
                            );
                            if (count($failures) > 10) {
                                $lines[] = '… y '.(count($failures) - 10).' más.';
                            }
                            $body .= ' '.count($failures).' fila(s) con error:<br>'.implode('<br>', $lines);
                        }

                        Notification::make()
                            ->title('Importación Completada')
                            ->body(new HtmlString($body))
                            ->{count($failures) > 0 ? 'warning' : 'success'}()
                            ->send()
                            ->persistent();
                    }),
            ])
                ->label('Creación masiva')
                ->icon('heroicon-o-square-3-stack-3d')
                ->color('warning')
                ->button(),

            ActionGroup::make([
                Action::make('export_banco')
                    ->label('Exportar para banco (Excel)')
                    ->icon('heroicon-o-building-library')
                    ->color('gray')
                    ->form([
                        Select::make('company_id')
                            ->label('Empresa')
                            ->options(Company::orderBy('name')->pluck('name', 'id'))
                            ->required()
                            ->native(false)
                            ->searchable()
                            ->live(),

                        Placeholder::make('banco_info')
                            ->label('Cuenta bancaria principal')
                            ->content(function (Get $get) {
                                $companyId = $get('company_id');

                                if (! $companyId) {
                                    return 'Seleccioná una empresa para ver la cuenta bancaria.';
                                }

                                $account = \App\Models\CompanyBankAccount::where('company_id', $companyId)
                                    ->where('is_primary', true)
                                    ->where('status', 'active')
                                    ->first();

                                if (! $account) {
                                    return 'Sin cuenta bancaria principal configurada.';
                                }

                                $parts = [\App\Models\CompanyBankAccount::getBankLabel($account->bank)];
                                $parts[] = $account->bank_company_id
                                    ? 'ID Empresa: '.$account->bank_company_id
                                    : '⚠ ID Empresa no configurado';
                                $parts[] = 'Cuenta: '.$account->account_number;

                                return implode(' · ', $parts);
                            })
                            ->columnSpanFull(),

                        Select::make('moneda')
                            ->label('Moneda')
                            ->options(['Guaraní' => 'Guaraní', 'Dólar' => 'Dólar'])
                            ->default('Guaraní')
                            ->required()
                            ->native(false),

                        \Filament\Forms\Components\DatePicker::make('fecha_credito')
                            ->label('Fecha Crédito')
                            ->required()
                            ->native(false)
                            ->default(today())
                            ->displayFormat('d/m/Y'),

                        Select::make('tipo')
                            ->label('Tipo de transferencia')
                            ->options(['Crédito' => 'Crédito', 'Cheque' => 'Cheque'])
                            ->default('Crédito')
                            ->required()
                            ->native(false),
                    ])
                    ->action(function (array $data, Action $action) {
                        $account = \App\Models\CompanyBankAccount::where('company_id', $data['company_id'])
                            ->where('is_primary', true)
                            ->where('status', 'active')
                            ->first();

                        if (! $account) {
                            Notification::make()
                                ->danger()
                                ->title('Sin cuenta bancaria principal')
                                ->body('La empresa no tiene cuenta bancaria principal activa configurada.')
                                ->send();

                            $action->halt();

                            return;
                        }

                        if (! $account->bank_company_id) {
                            Notification::make()
                                ->danger()
                                ->title('ID Empresa no configurado')
                                ->body('Completá el ID Empresa en la cuenta bancaria principal de la empresa antes de exportar.')
                                ->send();

                            $action->halt();

                            return;
                        }

                        $fecha = Carbon::parse($data['fecha_credito'])->format('d/m/Y');

                        $advances = Advance::where('status', 'approved')
                            ->where('payment_method', 'transfer')
                            ->whereHas('employee.branch', fn ($q) => $q->where('company_id', $data['company_id']))
                            ->with(['employee.bankAccounts' => fn ($q) => $q->where('is_primary', true)->where('status', 'active')])
                            ->orderBy('created_at', 'desc')
                            ->get();

                        $params = [
                            'id_empresa' => $account->bank_company_id,
                            'cuenta_debito' => $account->account_number,
                            'moneda' => $data['moneda'],
                            'tipo' => $data['tipo'],
                            'fecha_credito' => $fecha,
                        ];

                        $tempFile = app(BankPaymentExportService::class)->generate($params, $advances);
                        $filename = 'pagos_banco_'.now()->format('Y_m_d_H_i_s').'.xlsm';

                        return response()->download($tempFile, $filename, [
                            'Content-Type' => 'application/vnd.ms-excel.sheet.macroEnabled.12',
                        ])->deleteFileAfterSend(true);
                    }),

                Action::make('export_banco_txt')
                    ->label('Descargar TXT banco')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('primary')
                    ->modalHeading('Generar TRANSFER.txt para banco')
                    ->modalDescription('Los adelantos aprobados incluidos en el archivo quedarán marcados como Pagados.')
                    ->modalSubmitActionLabel('Generar y Descargar')
                    ->form([
                        Select::make('company_id')
                            ->label('Empresa')
                            ->options(Company::orderBy('name')->pluck('name', 'id'))
                            ->required()
                            ->native(false)
                            ->searchable()
                            ->live(),

                        Placeholder::make('banco_info')
                            ->label('Cuenta bancaria principal')
                            ->content(function (Get $get) {
                                $companyId = $get('company_id');

                                if (! $companyId) {
                                    return 'Seleccioná una empresa para ver la cuenta bancaria.';
                                }

                                $account = \App\Models\CompanyBankAccount::where('company_id', $companyId)
                                    ->where('is_primary', true)
                                    ->where('status', 'active')
                                    ->first();

                                if (! $account) {
                                    return 'Sin cuenta bancaria principal configurada.';
                                }

                                $parts = [\App\Models\CompanyBankAccount::getBankLabel($account->bank)];
                                $parts[] = $account->bank_company_id
                                    ? 'ID Empresa: '.$account->bank_company_id
                                    : '⚠ ID Empresa no configurado';
                                $parts[] = 'Cuenta: '.$account->account_number;

                                return implode(' · ', $parts);
                            })
                            ->columnSpanFull(),

                        Select::make('moneda')
                            ->label('Moneda')
                            ->options(['Guaraní' => 'Guaraní', 'Dólar' => 'Dólar'])
                            ->default('Guaraní')
                            ->required()
                            ->native(false),

                        \Filament\Forms\Components\DatePicker::make('fecha_credito')
                            ->label('Fecha Crédito')
                            ->required()
                            ->native(false)
                            ->default(today())
                            ->displayFormat('d/m/Y'),

                        Select::make('tipo')
                            ->label('Tipo de transferencia')
                            ->options(['Crédito' => 'Crédito', 'Cheque' => 'Cheque'])
                            ->default('Crédito')
                            ->required()
                            ->native(false),
                    ])
                    ->action(function (array $data, Action $action) {
                        $account = \App\Models\CompanyBankAccount::where('company_id', $data['company_id'])
                            ->where('is_primary', true)
                            ->where('status', 'active')
                            ->first();

                        if (! $account) {
                            Notification::make()
                                ->danger()
                                ->title('Sin cuenta bancaria principal')
                                ->body('La empresa no tiene cuenta bancaria principal activa configurada.')
                                ->send();

                            $action->halt();

                            return;
                        }

                        if (! $account->bank_company_id) {
                            Notification::make()
                                ->danger()
                                ->title('ID Empresa no configurado')
                                ->body('Completá el ID Empresa en la cuenta bancaria principal de la empresa antes de exportar.')
                                ->send();

                            $action->halt();

                            return;
                        }

                        $advances = Advance::where('status', 'approved')
                            ->where('payment_method', 'transfer')
                            ->whereHas('employee.branch', fn ($q) => $q->where('company_id', $data['company_id']))
                            ->with(['employee.bankAccounts' => fn ($q) => $q->where('is_primary', true)->where('status', 'active')])
                            ->orderBy('created_at', 'desc')
                            ->get();

                        if ($advances->isEmpty()) {
                            Notification::make()
                                ->warning()
                                ->title('Sin adelantos aprobados')
                                ->body('No hay adelantos aprobados para esta empresa.')
                                ->send();

                            $action->halt();

                            return;
                        }

                        $params = [
                            'id_empresa' => $account->bank_company_id,
                            'cuenta_debito' => $account->account_number,
                            'moneda' => $data['moneda'],
                            'tipo' => $data['tipo'],
                            'fecha_credito' => Carbon::parse($data['fecha_credito'])->format('Y-m-d'),
                        ];

                        $content = app(BankPaymentExportService::class)->generateTxt($params, $advances);
                        $filename = 'TRANSFER_'.now()->format('Y_m_d_H_i_s').'.txt';

                        Notification::make()
                            ->success()
                            ->title('Archivo generado')
                            ->body("{$advances->count()} adelanto(s) marcados como pagados.")
                            ->send();

                        return response()->streamDownload(
                            fn () => print ($content),
                            $filename,
                            ['Content-Type' => 'text/plain; charset=UTF-8']
                        );
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
                            new AdvancesExport,
                            'adelantos_'.now()->format('Y_m_d_H_i_s').'.xlsx'
                        );
                    }),
            ])
                ->label('Exportar')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->button(),
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
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending'))
                ->badge($byStatus['pending'] ?? 0)
                ->badgeColor('warning'),

            'approved' => Tab::make('Aprobados')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'approved'))
                ->badge($byStatus['approved'] ?? 0)
                ->badgeColor('info'),

            'paid' => Tab::make('Pagados')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'paid'))
                ->badge($byStatus['paid'] ?? 0)
                ->badgeColor('success'),

            'rejected' => Tab::make('Rechazados')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'rejected'))
                ->badge($byStatus['rejected'] ?? 0)
                ->badgeColor('danger'),

            'cancelled' => Tab::make('Cancelados')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'cancelled'))
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
