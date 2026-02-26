<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompanyResource\Pages;
use App\Filament\Resources\CompanyResource\RelationManagers;
use App\Models\Company;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\Section as InfoSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
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

    protected static ?string $recordTitleAttribute = 'name';

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

                Section::make('Constitución Legal')
                    ->description('Tipo societario y representante legal')
                    ->schema([
                        Select::make('legal_type')
                            ->label('Tipo Societario')
                            ->options(Company::$legalTypes)
                            ->native(false)
                            ->placeholder('Seleccionar tipo...')
                            ->columnSpan(1),

                        DatePicker::make('founded_at')
                            ->label('Fecha de Constitución')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->columnSpan(1),

                        TextInput::make('legal_rep_name')
                            ->label('Representante Legal')
                            ->maxLength(255)
                            ->columnSpan(1),

                        TextInput::make('legal_rep_ci')
                            ->label('CI del Representante')
                            ->maxLength(20)
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

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            InfoSection::make('Estadísticas')
                ->columns(4)
                ->schema([
                    TextEntry::make('branches_count')
                        ->label('Sucursales')
                        ->getStateUsing(fn (Company $record) => $record->branches()->count())
                        ->badge()
                        ->color('info')
                        ->icon('heroicon-o-building-office-2'),

                    TextEntry::make('active_employees')
                        ->label('Empleados Activos / Total')
                        ->getStateUsing(fn (Company $record) => $record->employees()->where('status', 'active')->count().
                            ' / '.
                            $record->employees()->count()
                        )
                        ->badge()
                        ->color('success')
                        ->icon('heroicon-o-users'),

                    TextEntry::make('active_contracts')
                        ->label('Contratos Activos')
                        ->getStateUsing(fn (Company $record) => $record->activeContractsCount())
                        ->badge()
                        ->color('primary')
                        ->icon('heroicon-o-document-text'),

                    TextEntry::make('expiring_contracts')
                        ->label('Por Vencer (30 días)')
                        ->getStateUsing(fn (Company $record) => $record->expiringSoonContractsCount(30))
                        ->badge()
                        ->color(fn (Company $record) => $record->expiringSoonContractsCount(30) > 0 ? 'warning' : 'gray')
                        ->icon('heroicon-o-clock'),
                ]),

            InfoSection::make('Información Legal')
                ->columns(2)
                ->schema([
                    TextEntry::make('name')
                        ->label('Razón Social')
                        ->weight('bold')
                        ->size(TextEntry\TextEntrySize::Large),

                    TextEntry::make('trade_name')
                        ->label('Nombre Comercial')
                        ->placeholder('Sin nombre comercial'),

                    TextEntry::make('ruc')
                        ->label('RUC')
                        ->copyable()
                        ->copyMessage('RUC copiado'),

                    TextEntry::make('employer_number')
                        ->label('Nro. Patronal IPS')
                        ->copyable()
                        ->copyMessage('Número patronal copiado'),
                ]),

            InfoSection::make('Constitución Legal')
                ->columns(2)
                ->schema([
                    TextEntry::make('legal_type')
                        ->label('Tipo Societario')
                        ->getStateUsing(fn (Company $record) => $record->legal_type_label)
                        ->badge()
                        ->color('info')
                        ->placeholder('No especificado'),

                    TextEntry::make('founded_at')
                        ->label('Fecha de Constitución')
                        ->date('d/m/Y')
                        ->placeholder('No especificada'),

                    TextEntry::make('legal_rep_name')
                        ->label('Representante Legal')
                        ->placeholder('No especificado'),

                    TextEntry::make('legal_rep_ci')
                        ->label('CI del Representante')
                        ->placeholder('No especificado'),
                ]),

            InfoSection::make('Contacto')
                ->columns(2)
                ->schema([
                    TextEntry::make('address')
                        ->label('Dirección')
                        ->placeholder('Sin dirección'),

                    TextEntry::make('city')
                        ->label('Ciudad')
                        ->badge()
                        ->color('gray')
                        ->placeholder('Sin ciudad'),

                    TextEntry::make('phone')
                        ->label('Teléfono')
                        ->icon('heroicon-o-phone')
                        ->copyable()
                        ->placeholder('Sin teléfono'),

                    TextEntry::make('email')
                        ->label('Correo Electrónico')
                        ->icon('heroicon-o-envelope')
                        ->copyable()
                        ->placeholder('Sin correo'),
                ]),

            InfoSection::make('Configuración')
                ->columns(2)
                ->schema([
                    ImageEntry::make('logo')
                        ->label('Logo')
                        ->circular()
                        ->size(80)
                        ->placeholder('Sin logo'),

                    TextEntry::make('is_active')
                        ->label('Estado')
                        ->formatStateUsing(fn (bool $state) => $state ? 'Activa' : 'Inactiva')
                        ->badge()
                        ->color(fn (bool $state) => $state ? 'success' : 'danger'),
                ]),
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
                    ->description(fn (Company $record) => $record->trade_name),

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
                ViewAction::make(),
                Action::make('orgChart')
                    ->label('Organigrama')
                    ->icon('heroicon-o-rectangle-group')
                    ->color('info')
                    ->url(fn (Company $record) => route('org-chart.show', $record))
                    ->openUrlInNewTab(),
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
            'view' => Pages\ViewCompany::route('/{record}'),
            'edit' => Pages\EditCompany::route('/{record}/edit'),
        ];
    }
}
