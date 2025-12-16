<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeductionResource\Pages;
use App\Models\Deduction;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class DeductionResource extends Resource
{
    protected static ?string $model = Deduction::class;
    protected static ?string $navigationGroup = 'Nóminas';
    protected static ?string $navigationLabel = 'Deducciones';
    protected static ?string $label = 'Deducción';
    protected static ?string $pluralLabel = 'Deducciones';
    protected static ?string $slug = 'deducciones';
    protected static ?string $navigationIcon = 'heroicon-o-minus-circle';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Información General')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->placeholder('Ejemplo: Deducción por Préstamo')
                            ->required()
                            ->maxLength(60)
                            ->columnSpan(1),

                        TextInput::make('code')
                            ->label('Código')
                            ->placeholder('Ejemplo: DEDU001')
                            ->required()
                            ->maxLength(10)
                            ->unique(ignoreRecord: true)
                            ->columnSpan(1),

                        Textarea::make('description')
                            ->label('Descripción')
                            ->placeholder('Descripción detallada de la deducción')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Configuración de Cálculo')
                    ->schema([
                        Select::make('calculation')
                            ->label('Tipo de Cálculo')
                            ->options([
                                'fixed'      => 'Monto Fijo',
                                'percentage' => 'Porcentaje del Salario',
                            ])
                            ->default('fixed')
                            ->native(false)
                            ->reactive()
                            ->required()
                            ->columnSpan(1),

                        TextInput::make('amount')
                            ->label('Monto Fijo')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(999999999.99)
                            ->step(0.01)
                            ->prefix('₲')
                            ->visible(fn(Forms\Get $get) => $get('calculation') === 'fixed')
                            ->required(fn(Forms\Get $get) => $get('calculation') === 'fixed')
                            ->default(0.00)
                            ->helperText('Monto que se descontará del salario')
                            ->columnSpan(1),

                        TextInput::make('percent')
                            ->label('Porcentaje')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->suffix('%')
                            ->visible(fn(Forms\Get $get) => $get('calculation') === 'percentage')
                            ->required(fn(Forms\Get $get) => $get('calculation') === 'percentage')
                            ->default(0.00)
                            ->helperText('Porcentaje del salario base que se descontará')
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Section::make('Configuración Adicional')
                    ->schema([
                        Toggle::make('is_mandatory')
                            ->label('Deducción Obligatoria')
                            ->helperText('Se aplicará automáticamente a todos los empleados')
                            ->default(false)
                            ->inline(false)
                            ->columnSpan(1),

                        Toggle::make('affects_ips')
                            ->label('Afecta IPS')
                            ->helperText('Esta deducción afecta el cálculo del IPS')
                            ->default(false)
                            ->inline(false)
                            ->columnSpan(1),

                        Toggle::make('affects_irp')
                            ->label('Afecta IRP')
                            ->helperText('Esta deducción afecta el cálculo del IRP')
                            ->default(false)
                            ->inline(false)
                            ->columnSpan(1),

                        Toggle::make('is_active')
                            ->label('Activo')
                            ->helperText('Habilitar o deshabilitar esta deducción')
                            ->default(true)
                            ->inline(false)
                            ->columnSpan(1),
                    ])
                    ->columns(4),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Código copiado')
                    ->weight('bold'),

                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('calculation')
                    ->label('Tipo')
                    ->formatStateUsing(fn($state) => $state === 'fixed' ? 'Fijo' : 'Porcentaje')
                    ->badge()
                    ->color(fn($state) => $state === 'fixed' ? 'success' : 'warning')
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Monto')
                    ->money('PYG', locale: 'es_PY')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('percent')
                    ->label('Porcentaje')
                    ->formatStateUsing(fn($state) => $state ? number_format($state, 2) . '%' : '-')
                    ->sortable()
                    ->toggleable(),

                IconColumn::make('is_mandatory')
                    ->label('Obligatorio')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->sortable()
                    ->toggleable(),

                IconColumn::make('affects_ips')
                    ->label('IPS')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->sortable()
                    ->toggleable(),

                IconColumn::make('affects_irp')
                    ->label('IRP')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->sortable()
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Estado')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),

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
                SelectFilter::make('calculation')
                    ->label('Tipo de Cálculo')
                    ->options([
                        'fixed'      => 'Monto Fijo',
                        'percentage' => 'Porcentaje',
                    ])
                    ->native(false),

                TernaryFilter::make('is_mandatory')
                    ->label('Obligatorio')
                    ->placeholder('Todos')
                    ->trueLabel('Obligatorios')
                    ->falseLabel('No Obligatorios')
                    ->native(false),

                TernaryFilter::make('affects_ips')
                    ->label('Afecta IPS')
                    ->placeholder('Todos')
                    ->trueLabel('Afecta IPS')
                    ->falseLabel('No Afecta IPS')
                    ->native(false),

                TernaryFilter::make('affects_irp')
                    ->label('Afecta IRP')
                    ->placeholder('Todos')
                    ->trueLabel('Afecta IRP')
                    ->falseLabel('No Afecta IRP')
                    ->native(false),

                TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->placeholder('Todos')
                    ->trueLabel('Activos')
                    ->falseLabel('Inactivos')
                    ->native(false),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No hay deducciones registradas')
            ->emptyStateDescription('Comienza a agregar deducciones para gestionar los descuentos en los salarios de los empleados.')
            ->emptyStateIcon('heroicon-o-minus-circle');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageDeductions::route('/'),
        ];
    }
}
