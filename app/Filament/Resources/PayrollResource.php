<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PayrollResource\Pages;
use App\Filament\Resources\PayrollResource\RelationManagers;
use App\Models\Payroll;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PayrollResource extends Resource
{
    protected static ?string $model = Payroll::class;
    protected static ?string $navigationGroup = 'Nóminas';
    protected static ?string $navigationLabel = 'Recibos';
    protected static ?string $label = 'Recibo';
    protected static ?string $pluralLabel = 'Recibos';
    protected static ?string $slug = 'recibos';
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Información del Recibo')
                    ->schema([
                        Select::make('employee_id')
                            ->label('Empleado')
                            ->relationship('employee', 'id')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required()
                            ->getOptionLabelFromRecordUsing(function ($record) {
                                return "{$record->first_name} {$record->last_name} - CI: {$record->ci}";
                            })
                            ->columnSpan(1),

                        Select::make('payroll_period_id')
                            ->label('Período')
                            ->relationship('period', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required()
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Section::make('Salarios')
                    ->schema([
                        TextInput::make('base_salary')
                            ->label('Salario Base')
                            ->numeric()
                            ->prefix('₲')
                            ->required()
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Salario base del empleado')
                            ->columnSpan(1),

                        TextInput::make('gross_salary')
                            ->label('Salario Bruto')
                            ->numeric()
                            ->prefix('₲')
                            ->required()
                            ->helperText('Salario base + percepciones')
                            ->columnSpan(1),

                        TextInput::make('total_perceptions')
                            ->label('Total Percepciones')
                            ->numeric()
                            ->prefix('₲')
                            ->default(0.00)
                            ->required()
                            ->columnSpan(1),

                        TextInput::make('total_deductions')
                            ->label('Total Deducciones')
                            ->numeric()
                            ->prefix('₲')
                            ->default(0.00)
                            ->required()
                            ->columnSpan(1),

                        TextInput::make('net_salary')
                            ->label('Salario Neto')
                            ->numeric()
                            ->prefix('₲')
                            ->required()
                            ->helperText('Salario bruto - deducciones')
                            ->columnSpan(2),
                    ])
                    ->columns(2),

                Section::make('Información Adicional')
                    ->schema([
                        DateTimePicker::make('generated_at')
                            ->label('Fecha de Generación')
                            ->displayFormat('d/m/Y H:i')
                            ->native(false)
                            ->disabled()
                            ->dehydrated()
                            ->default(now())
                            ->columnSpan(1),

                        TextInput::make('pdf_path')
                            ->label('Ruta del PDF')
                            ->disabled()
                            ->dehydrated()
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsed(),
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

                TextColumn::make('period.name')
                    ->label('Período')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('base_salary')
                    ->label('Salario Base')
                    ->money('PYG', locale: 'es_PY')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('total_perceptions')
                    ->label('Percepciones')
                    ->money('PYG', locale: 'es_PY')
                    ->sortable()
                    ->color('success')
                    ->toggleable(),

                TextColumn::make('total_deductions')
                    ->label('Deducciones')
                    ->money('PYG', locale: 'es_PY')
                    ->sortable()
                    ->color('danger')
                    ->toggleable(),

                TextColumn::make('gross_salary')
                    ->label('Salario Bruto')
                    ->money('PYG', locale: 'es_PY')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('net_salary')
                    ->label('Salario Neto')
                    ->money('PYG', locale: 'es_PY')
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),

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

                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('payroll_period_id')
                    ->label('Período')
                    ->relationship('period', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),

                SelectFilter::make('employee_id')
                    ->label('Empleado')
                    ->relationship('employee', 'first_name')
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->getOptionLabelFromRecordUsing(function ($record) {
                        return "{$record->first_name} {$record->last_name}";
                    }),

                Filter::make('current_year')
                    ->label('Año Actual')
                    ->query(fn($query) => $query->whereHas('period', function ($q) {
                        $q->whereYear('start_date', now()->year);
                    }))
                    ->default(),
            ])
            ->actions([
                ViewAction::make(),

                Action::make('download_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->url(fn(Payroll $record) => route('payrolls.download', $record))
                    ->openUrlInNewTab(),

                EditAction::make()
                    ->visible(fn(Payroll $record) => $record->period->status === 'draft'),

                DeleteAction::make()
                    ->visible(fn(Payroll $record) => $record->period->status === 'draft'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('download_pdfs')
                        ->label('Descargar PDFs')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('info')
                        ->action(function ($records) {
                            // Lógica para descargar múltiples PDFs (ZIP)
                        }),

                    DeleteBulkAction::make()
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                if ($record->period->status === 'draft') {
                                    $record->delete();
                                }
                            }
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No hay recibos de nómina')
            ->emptyStateDescription('Los recibos se generan automáticamente desde los períodos de nómina.')
            ->emptyStateIcon('heroicon-o-document-text');
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
                                ->color('primary'),
                        ])->columns(2),
                    ]),

                InfolistSection::make('Información del Período')
                    ->schema([
                        Group::make([
                            TextEntry::make('period.name')
                                ->label('Período')
                                ->icon('heroicon-o-calendar-days')
                                ->badge()
                                ->color('info'),

                            TextEntry::make('period.frequency')
                                ->label('Frecuencia')
                                ->formatStateUsing(fn(string $state): string => match ($state) {
                                    'monthly'  => 'Mensual',
                                    'biweekly' => 'Quincenal',
                                    'weekly'   => 'Semanal',
                                    default    => $state,
                                })
                                ->badge(),
                        ])->columns(2),

                        Group::make([
                            TextEntry::make('period.start_date')
                                ->label('Fecha Inicio')
                                ->date('d/m/Y')
                                ->icon('heroicon-o-calendar'),

                            TextEntry::make('period.end_date')
                                ->label('Fecha Fin')
                                ->date('d/m/Y')
                                ->icon('heroicon-o-calendar'),
                        ])->columns(2),
                    ]),

                InfolistSection::make('Detalle de Nómina')
                    ->schema([
                        Group::make([
                            TextEntry::make('base_salary')
                                ->label('Salario Base')
                                ->money('PYG', locale: 'es_PY')
                                ->icon('heroicon-o-banknotes'),

                            TextEntry::make('total_perceptions')
                                ->label('Total Percepciones')
                                ->money('PYG', locale: 'es_PY')
                                ->color('success')
                                ->icon('heroicon-o-plus-circle'),
                        ])->columns(2),

                        Group::make([
                            TextEntry::make('gross_salary')
                                ->label('Salario Bruto')
                                ->money('PYG', locale: 'es_PY')
                                ->weight('bold'),

                            TextEntry::make('total_deductions')
                                ->label('Total Deducciones')
                                ->money('PYG', locale: 'es_PY')
                                ->color('danger')
                                ->icon('heroicon-o-minus-circle'),
                        ])->columns(2),

                        TextEntry::make('net_salary')
                            ->label('Salario Neto a Pagar')
                            ->money('PYG', locale: 'es_PY')
                            ->size('lg')
                            ->weight('bold')
                            ->color('success')
                            ->icon('heroicon-o-currency-dollar'),
                    ]),

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
            RelationManagers\ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayrolls::route('/'),
            'create' => Pages\CreatePayroll::route('/create'),
            'view' => Pages\ViewPayroll::route('/{record}'),
            'edit' => Pages\EditPayroll::route('/{record}/edit'),
        ];
    }
}
