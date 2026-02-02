<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompanyResource\Pages;
use App\Filament\Resources\CompanyResource\RelationManagers;
use App\Models\Company;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Organización';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Empresa';

    protected static ?string $pluralModelLabel = 'Empresas';

    protected static ?string $slug = 'empresas';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Informacion Legal')
                    ->description('Datos legales de la empresa')
                    ->schema([
                        TextInput::make('name')
                            ->label('Razon Social')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(1),

                        TextInput::make('trade_name')
                            ->label('Nombre Comercial')
                            ->maxLength(255)
                            ->columnSpan(1),

                        TextInput::make('ruc')
                            ->label('RUC')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(20)
                            ->columnSpan(1),

                        TextInput::make('employer_number')
                            ->label('Numero Patronal IPS')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(20)
                            ->helperText('Codigo asignado por el IPS')
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Section::make('Contacto')
                    ->schema([
                        TextInput::make('address')
                            ->label('Direccion')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('city')
                            ->label('Ciudad')
                            ->maxLength(100)
                            ->columnSpan(1),

                        TextInput::make('phone')
                            ->label('Telefono')
                            ->tel()
                            ->maxLength(50)
                            ->columnSpan(1),

                        TextInput::make('email')
                            ->label('Correo Electronico')
                            ->email()
                            ->maxLength(255)
                            ->columnSpan(1),
                    ])
                    ->columns(3),

                Section::make('Configuracion')
                    ->schema([
                        FileUpload::make('logo')
                            ->label('Logo')
                            ->image()
                            ->directory('companies/logos')
                            ->maxSize(2048)
                            ->columnSpan(1),

                        Toggle::make('is_active')
                            ->label('Activa')
                            ->default(true)
                            ->helperText('Las empresas inactivas no apareceran en los selectores')
                            ->columnSpan(1),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo')
                    ->label('')
                    ->circular()
                    ->size(40),

                TextColumn::make('name')
                    ->label('Razon Social')
                    ->searchable()
                    ->sortable()
                    ->description(fn(Company $record) => $record->trade_name),

                TextColumn::make('ruc')
                    ->label('RUC')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('employer_number')
                    ->label('Nro. Patronal')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('city')
                    ->label('Ciudad')
                    ->sortable(),

                TextColumn::make('branches_count')
                    ->label('Sucursales')
                    ->counts('branches')
                    ->sortable(),

                TextColumn::make('employees_count')
                    ->label('Empleados')
                    ->counts('employees')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->placeholder('Todas')
                    ->trueLabel('Activas')
                    ->falseLabel('Inactivas'),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\BranchesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompanies::route('/'),
            'create' => Pages\CreateCompany::route('/create'),
            'edit' => Pages\EditCompany::route('/{record}/edit'),
        ];
    }
}
