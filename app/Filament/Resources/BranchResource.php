<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BranchResource\Pages;
use App\Filament\Resources\BranchResource\RelationManagers\EmployeesRelationManager;
use App\Models\Branch;
use App\Models\Company;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;

class BranchResource extends Resource
{
    protected static ?string $model = Branch::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationLabel = 'Sucursales';
    protected static ?string $label = 'Sucursal';
    protected static ?string $pluralLabel = 'Sucursales';
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?string $slug = 'sucursales';
    protected static ?string $navigationGroup = 'Organización';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Información general')
                    ->description('Datos básicos de la sucursal')
                    ->icon('heroicon-o-information-circle')
                    ->collapsible()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('company_id')
                                    ->label('Empresa')
                                    ->relationship('company', 'name')
                                    ->options(Company::active()->pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->columnSpanFull()
                                    ->helperText('Selecciona la empresa a la que pertenece esta sucursal'),

                                TextInput::make('name')
                                    ->label('Nombre de la sucursal')
                                    ->placeholder('Ej: Sucursal Central, Sucursal Este...')
                                    ->required()
                                    ->maxLength(100)
                                    ->unique(Branch::class, 'name', ignoreRecord: true)
                                    ->autocapitalize('words')
                                    ->columnSpanFull(),

                                TextInput::make('city')
                                    ->label('Ciudad')
                                    ->placeholder('Ej: Asunción, Ciudad del Este...')
                                    ->maxLength(60)
                                    ->required()
                                    ->autocapitalize('words'),

                                TextInput::make('address')
                                    ->label('Dirección')
                                    ->placeholder('Ej: Av. España 1234 c/ Brasil')
                                    ->maxLength(100)
                                    ->required(),
                            ]),
                    ]),

                Section::make('Información de contacto')
                    ->description('Teléfono y correo electrónico')
                    ->icon('heroicon-o-phone')
                    ->collapsible()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('phone')
                                    ->label('Teléfono')
                                    ->tel()
                                    ->prefix('+595')
                                    ->placeholder('971123456')
                                    ->minLength(7)
                                    ->maxLength(30)
                                    ->helperText('Formato: 971123456')
                                    ->nullable(),

                                TextInput::make('email')
                                    ->label('Correo electrónico')
                                    ->email()
                                    ->placeholder('sucursal@empresa.com')
                                    ->maxLength(100)
                                    ->unique(Branch::class, 'email', ignoreRecord: true)
                                    ->nullable(),
                            ]),
                    ]),

                Section::make('Ubicación geográfica')
                    ->description('Coordenadas GPS de la sucursal')
                    ->icon('heroicon-o-map-pin')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('coordinates.lat')
                                    ->label('Latitud')
                                    ->placeholder('-25.303772')
                                    ->numeric()
                                    ->minValue(-90)
                                    ->maxValue(90)
                                    ->step(0.000001)
                                    ->helperText('Coordenada latitud (entre -90 y 90)')
                                    ->nullable(),

                                TextInput::make('coordinates.lng')
                                    ->label('Longitud')
                                    ->placeholder('-57.611112')
                                    ->numeric()
                                    ->minValue(-180)
                                    ->maxValue(180)
                                    ->step(0.000001)
                                    ->helperText('Coordenada longitud (entre -180 y 180)')
                                    ->nullable(),
                            ]),

                        Placeholder::make('coordinates_help')
                            ->label('¿Cómo obtener las coordenadas?')
                            ->content('Puedes obtener las coordenadas desde Google Maps: Click derecho en el mapa → primero aparece la latitud, luego la longitud.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company.name')
                    ->label('Empresa')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('name')
                    ->label('Sucursal')
                    ->description(fn($record) => $record->address)
                    ->icon('heroicon-o-building-office-2')
                    ->sortable()
                    ->searchable()
                    ->weight('medium')
                    ->wrap(),

                TextColumn::make('city')
                    ->label('Ciudad')
                    ->icon('heroicon-o-map-pin')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('contact_info')
                    ->label('Contacto')
                    ->getStateUsing(fn($record) => $record->phone ?: 'Sin datos')
                    ->description(fn($record) => $record->email ?: 'Sin datos')
                    ->icon('heroicon-o-phone')
                    ->placeholder('Sin contacto')
                    ->copyable()
                    ->copyMessage('Contacto copiado')
                    ->tooltip('Haz clic para copiar'),

                TextColumn::make('employees_count')
                    ->label('Empleados')
                    ->counts('employees')
                    ->badge()
                    ->color('success')
                    ->sortable()
                    ->alignCenter(),
            ])
            ->defaultSort('name', 'asc')
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Empresa')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload()
                    ->placeholder('Todas las empresas'),

                SelectFilter::make('city')
                    ->label('Ciudad')
                    ->options(function () {
                        return Branch::query()
                            ->distinct()
                            ->pluck('city', 'city')
                            ->filter()
                            ->sort()
                            ->toArray();
                    })
                    ->placeholder('Todas las ciudades')
                    ->searchable()
                    ->native(false)
                    ->multiple(),

                Filter::make('with_employees')
                    ->label('Con empleados')
                    ->query(fn($query) => $query->has('employees'))
                    ->toggle(),

                Filter::make('without_employees')
                    ->label('Sin empleados')
                    ->query(fn($query) => $query->doesntHave('employees'))
                    ->toggle(),
            ])
            ->actions([
                Action::make('view_map')
                    ->label('Ver en mapa')
                    ->icon('heroicon-o-map')
                    ->color('info')
                    ->url(fn($record) => $record->coordinates ? static::getGoogleMapsUrl($record->coordinates) : null)
                    ->openUrlInNewTab()
                    ->visible(fn($record) => $record->coordinates !== null),

                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar seleccionadas')
                        ->modalHeading('Eliminar sucursales')
                        ->modalDescription('¿Estás seguro de que deseas eliminar estas sucursales? Los empleados asignados quedarán sin sucursal.'),
                ]),
            ])
            ->emptyStateHeading('No hay sucursales registradas')
            ->emptyStateDescription('Comienza agregando la primera sucursal de tu empresa')
            ->emptyStateIcon('heroicon-o-building-office-2');
    }

    // Método auxiliar para generar URL de Google Maps
    protected static function getGoogleMapsUrl($coordinates): ?string
    {
        if (!$coordinates) {
            return null;
        }

        try {
            $coords = is_string($coordinates)
                ? json_decode($coordinates, true)
                : $coordinates;

            if (json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }

            if (isset($coords['lat']) && isset($coords['lng'])) {
                $lat = (float) $coords['lat'];
                $lng = (float) $coords['lng'];

                // Validar que las coordenadas sean válidas
                if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
                    return sprintf(
                        'https://www.google.com/maps?q=%s,%s',
                        $lat,
                        $lng
                    );
                }
            }
        } catch (\Exception $e) {
            Log::warning('Error generando URL de Google Maps para sucursal', [
                'coordinates' => $coordinates,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    public static function getRelations(): array
    {
        return [
            EmployeesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBranches::route('/'),
            'create' => Pages\CreateBranch::route('/create'),
            'edit' => Pages\EditBranch::route('/{record}/edit'),
        ];
    }
}
