<?php

namespace App\Filament\Resources\CompanyResource\RelationManagers;

use App\Models\Branch;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;

class BranchesRelationManager extends RelationManager
{
    protected static string $relationship = 'branches';

    protected static ?string $title = 'Sucursales';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Información general')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nombre de la sucursal')
                                    ->required()
                                    ->maxLength(100)
                                    ->autocapitalize('words')
                                    ->unique(
                                        table: Branch::class,
                                        column: 'name',
                                        ignoreRecord: true,
                                        modifyRuleUsing: fn(\Illuminate\Validation\Rules\Unique $rule) =>
                                            $rule->where('company_id', $this->ownerRecord->id)
                                    )
                                    ->columnSpanFull(),

                                TextInput::make('city')
                                    ->label('Ciudad')
                                    ->required()
                                    ->maxLength(60)
                                    ->placeholder('Ej: Asunción, Ciudad del Este...'),

                                TextInput::make('address')
                                    ->label('Dirección')
                                    ->required()
                                    ->maxLength(100)
                                    ->placeholder('Ej: Av. España 1234 c/ Brasil'),
                            ]),
                    ]),

                Section::make('Contacto')
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
                                    ->helperText('Formato: 971123456'),

                                TextInput::make('email')
                                    ->label('Correo electrónico')
                                    ->email()
                                    ->maxLength(100)
                                    ->unique(Branch::class, 'email', ignoreRecord: true),
                            ]),
                    ]),

                Section::make('Ubicación')
                    ->collapsed()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('coordinates.lat')
                                    ->label('Latitud')
                                    ->numeric()
                                    ->minValue(-90)
                                    ->maxValue(90)
                                    ->step(0.000001)
                                    ->placeholder('-25.303772'),

                                TextInput::make('coordinates.lng')
                                    ->label('Longitud')
                                    ->numeric()
                                    ->minValue(-180)
                                    ->maxValue(180)
                                    ->step(0.000001)
                                    ->placeholder('-57.611112'),
                            ]),

                        Placeholder::make('help')
                            ->label('')
                            ->content('Coordenadas opcionales para ubicar la sucursal en el mapa. Puedes obtenerlas desde Google Maps: Click derecho → primero aparece la latitud, luego la longitud.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->description(fn($record) => $record->address)
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('city')
                    ->label('Ciudad')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('contact_info')
                    ->label('Contacto')
                    ->getStateUsing(fn($record) => $record->phone ?: 'Sin teléfono')
                    ->description(fn($record) => $record->email ?: null)
                    ->icon('heroicon-o-phone')
                    ->placeholder('Sin contacto'),

                TextColumn::make('active_employees_count')
                    ->label('Empleados Activos')
                    ->counts('activeEmployees')
                    ->badge()
                    ->color('success')
                    ->alignCenter(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Nueva Sucursal')
                    ->icon('heroicon-o-plus'),
            ])
            ->actions([
                Action::make('view_map')
                    ->label('Ver en mapa')
                    ->icon('heroicon-o-map')
                    ->color('info')
                    ->url(fn($record) => $record->coordinates ? $this->getGoogleMapsUrl($record->coordinates) : null)
                    ->openUrlInNewTab()
                    ->visible(fn($record) => $record->coordinates !== null),

                EditAction::make(),

                DeleteAction::make()
                    ->modalHeading('Eliminar sucursal')
                    ->modalDescription(fn($record) => "¿Estás seguro de que deseas eliminar la sucursal \"{$record->name}\"? Los empleados asignados quedarán sin sucursal."),
            ])
            ->bulkActions([
                DeleteBulkAction::make()
                    ->modalHeading('Eliminar sucursales')
                    ->modalDescription('¿Estás seguro de que deseas eliminar las sucursales seleccionadas? Los empleados asignados quedarán sin sucursal.'),
            ])
            ->defaultSort('name')
            ->emptyStateHeading('No hay sucursales registradas')
            ->emptyStateDescription('Agrega la primera sucursal de esta empresa.')
            ->emptyStateIcon('heroicon-o-building-office-2');
    }

    protected function getGoogleMapsUrl($coordinates): ?string
    {
        if (!$coordinates) {
            return null;
        }

        try {
            $coords = is_string($coordinates) ? json_decode($coordinates, true) : $coordinates;

            if (isset($coords['lat']) && isset($coords['lng'])) {
                $lat = (float) $coords['lat'];
                $lng = (float) $coords['lng'];

                if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
                    return sprintf('https://www.google.com/maps?q=%s,%s', $lat, $lng);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Error generando URL de Google Maps para sucursal', [
                'coordinates' => $coordinates,
                'error'       => $e->getMessage(),
            ]);
        }

        return null;
    }
}
