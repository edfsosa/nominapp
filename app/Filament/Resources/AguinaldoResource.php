<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AguinaldoResource\Pages;
use App\Filament\Resources\AguinaldoResource\RelationManagers\ItemsRelationManager;
use App\Models\Aguinaldo;
use App\Models\Company;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AguinaldoResource extends Resource
{
    protected static ?string $model = Aguinaldo::class;
    protected static ?string $navigationGroup = 'Nóminas';
    protected static ?string $navigationLabel = 'Recibos Aguinaldo';
    protected static ?string $label = 'Recibo de Aguinaldo';
    protected static ?string $pluralLabel = 'Recibos de Aguinaldo';
    protected static ?string $slug = 'aguinaldo-recibos';
    protected static ?string $navigationIcon = 'heroicon-o-gift-top';
    protected static ?int $navigationSort = 7;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Información del Aguinaldo')
                    ->schema([
                        Select::make('aguinaldo_period_id')
                            ->label('Período')
                            ->relationship('period', 'year')
                            ->getOptionLabelFromRecordUsing(fn($record) => "{$record->year} - {$record->company->name}")
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required()
                            ->disabled()
                            ->columnSpan(1),

                        Select::make('employee_id')
                            ->label('Empleado')
                            ->relationship('employee', 'id')
                            ->getOptionLabelFromRecordUsing(fn($record) => "{$record->full_name} - CI: {$record->ci}")
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required()
                            ->disabled()
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Section::make('Cálculo')
                    ->schema([
                        TextInput::make('total_earned')
                            ->label('Total Devengado')
                            ->numeric()
                            ->prefix('₲')
                            ->disabled()
                            ->columnSpan(1),

                        TextInput::make('months_worked')
                            ->label('Meses Trabajados')
                            ->numeric()
                            ->disabled()
                            ->columnSpan(1),

                        TextInput::make('aguinaldo_amount')
                            ->label('Aguinaldo a Pagar')
                            ->numeric()
                            ->prefix('₲')
                            ->disabled()
                            ->columnSpan(2),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee.ci')
                    ->label('CI')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('CI copiado')
                    ->weight('bold'),

                TextColumn::make('employee.full_name')
                    ->label('Empleado')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['first_name', 'last_name'])
                    ->wrap(),

                TextColumn::make('period.company.name')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('period.year')
                    ->label('Año')
                    ->sortable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('months_worked')
                    ->label('Meses')
                    ->alignCenter()
                    ->formatStateUsing(fn($state) => number_format($state, 0))
                    ->sortable(),

                TextColumn::make('total_earned')
                    ->label('Total Devengado')
                    ->money('PYG', locale: 'es_PY')
                    ->sortable()
                    ->toggleable()
                    ->summarize([
                        Sum::make()
                            ->money('PYG', locale: 'es_PY')
                            ->label('Total'),
                    ]),

                TextColumn::make('aguinaldo_amount')
                    ->label('Aguinaldo')
                    ->money('PYG', locale: 'es_PY')
                    ->sortable()
                    ->weight('bold')
                    ->color('success')
                    ->summarize([
                        Sum::make()
                            ->money('PYG', locale: 'es_PY')
                            ->label('Total a Pagar'),
                    ]),

                TextColumn::make('generated_at')
                    ->label('Generado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('company')
                    ->label('Empresa')
                    ->options(Company::active()->get()->mapWithKeys(fn($c) =>
                        [$c->id => $c->name . ($c->trade_name ? ' (' . $c->trade_name . ')' : '')]
                    ))
                    ->query(function ($query, array $data) {
                        if (filled($data['value'])) {
                            return $query->whereHas('period', fn($q) => $q->where('company_id', $data['value']));
                        }
                    })
                    ->searchable()
                    ->preload()
                    ->native(false),

                SelectFilter::make('aguinaldo_period_id')
                    ->label('Período')
                    ->relationship('period', 'year')
                    ->getOptionLabelFromRecordUsing(fn($record) => "{$record->year} - {$record->company->name}")
                    ->searchable()
                    ->preload()
                    ->native(false),

                SelectFilter::make('employee_id')
                    ->label('Empleado')
                    ->relationship('employee', 'first_name')
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->getOptionLabelFromRecordUsing(fn($record) => "{$record->full_name}"),

                SelectFilter::make('year')
                    ->label('Año')
                    ->options(function () {
                        $years = [];
                        for ($y = now()->year + 1; $y >= now()->year - 5; $y--) {
                            $years[$y] = $y;
                        }
                        return $years;
                    })
                    ->query(function ($query, array $data) {
                        if (filled($data['value'])) {
                            return $query->whereHas('period', fn($q) => $q->where('year', $data['value']));
                        }
                    })
                    ->native(false)
                    ->default(now()->year),
            ])
            ->actions([
                ViewAction::make(),

                Action::make('download_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->url(fn(Aguinaldo $record) => route('aguinaldos.download', $record))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('download_pdfs')
                        ->label('Descargar PDFs')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('info')
                        ->action(function ($records) {
                            // Lógica para descargar múltiples PDFs (ZIP) - futuro
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No hay recibos de aguinaldo')
            ->emptyStateDescription('Los recibos de aguinaldo se generan automáticamente desde los períodos de aguinaldo.')
            ->emptyStateIcon('heroicon-o-gift');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfolistSection::make('Información del Empleado')
                    ->schema([
                        Group::make([
                            TextEntry::make('employee.ci')
                                ->label('Cédula de Identidad')
                                ->icon('heroicon-o-identification')
                                ->copyable(),

                            TextEntry::make('employee.full_name')
                                ->label('Nombre Completo'),
                        ])->columns(2),

                        Group::make([
                            TextEntry::make('employee.position.name')
                                ->label('Cargo')
                                ->icon('heroicon-o-briefcase')
                                ->badge()
                                ->color('info'),

                            TextEntry::make('employee.position.department.name')
                                ->label('Departamento')
                                ->icon('heroicon-o-building-office-2')
                                ->badge()
                                ->color('primary')
                                ->default('N/A'),
                        ])->columns(2),
                    ]),

                InfolistSection::make('Información del Período')
                    ->schema([
                        Group::make([
                            TextEntry::make('period.company.name')
                                ->label('Empresa')
                                ->icon('heroicon-o-building-office'),

                            TextEntry::make('period.year')
                                ->label('Año Fiscal')
                                ->badge()
                                ->color('info'),
                        ])->columns(2),

                        Group::make([
                            TextEntry::make('period.status')
                                ->label('Estado del Período')
                                ->badge()
                                ->color(fn(string $state): string => match ($state) {
                                    'draft'      => 'gray',
                                    'processing' => 'warning',
                                    'closed'     => 'success',
                                    default      => 'primary',
                                })
                                ->formatStateUsing(fn(string $state): string => match ($state) {
                                    'draft'      => 'Borrador',
                                    'processing' => 'En Proceso',
                                    'closed'     => 'Cerrado',
                                    default      => $state,
                                }),
                        ])->columns(1),
                    ]),

                InfolistSection::make('Cálculo del Aguinaldo')
                    ->schema([
                        Group::make([
                            TextEntry::make('total_earned')
                                ->label('Total Devengado en el Año')
                                ->money('PYG', locale: 'es_PY')
                                ->icon('heroicon-o-banknotes'),

                            TextEntry::make('months_worked')
                                ->label('Meses Trabajados')
                                ->formatStateUsing(fn($state) => number_format($state, 0) . ' meses')
                                ->icon('heroicon-o-calendar'),
                        ])->columns(2),

                        TextEntry::make('aguinaldo_amount')
                            ->label('Aguinaldo a Pagar (Total / 12)')
                            ->money('PYG', locale: 'es_PY')
                            ->size('lg')
                            ->weight('bold')
                            ->color('success')
                            ->icon('heroicon-o-gift'),
                    ]),

                InfolistSection::make('Desglose Mensual')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                TextEntry::make('month')
                                    ->label('Mes'),
                                TextEntry::make('base_salary')
                                    ->label('Salario Base')
                                    ->money('PYG', locale: 'es_PY'),
                                TextEntry::make('perceptions')
                                    ->label('Percepciones')
                                    ->money('PYG', locale: 'es_PY'),
                                TextEntry::make('extra_hours')
                                    ->label('Horas Extras')
                                    ->money('PYG', locale: 'es_PY'),
                                TextEntry::make('total')
                                    ->label('Total')
                                    ->money('PYG', locale: 'es_PY')
                                    ->weight('bold'),
                            ])
                            ->columns(5),
                    ])
                    ->collapsible(),

                InfolistSection::make('Información del Sistema')
                    ->schema([
                        Group::make([
                            TextEntry::make('generated_at')
                                ->label('Fecha de Generación')
                                ->dateTime('d/m/Y H:i')
                                ->icon('heroicon-o-clock'),

                            TextEntry::make('pdf_path')
                                ->label('PDF Generado')
                                ->formatStateUsing(fn($state) => $state ? 'Disponible' : 'No generado')
                                ->badge()
                                ->color(fn($state) => $state ? 'success' : 'gray')
                                ->icon(fn($state) => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),
                        ])->columns(2),

                        Group::make([
                            TextEntry::make('created_at')
                                ->label('Creado')
                                ->dateTime('d/m/Y H:i'),

                            TextEntry::make('updated_at')
                                ->label('Actualizado')
                                ->dateTime('d/m/Y H:i'),
                        ])->columns(2),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAguinaldos::route('/'),
            'view' => Pages\ViewAguinaldo::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
