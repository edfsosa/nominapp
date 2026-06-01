<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use App\Models\EmployeeAddress;
use App\Models\PyCity;
use App\Models\PyDepartment;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Gestiona las direcciones postales del empleado. */
class AddressesRelationManager extends RelationManager
{
    protected static string $relationship = 'addresses';

    protected static ?string $title = 'Direcciones';

    protected static ?string $modelLabel = 'dirección';

    protected static ?string $pluralModelLabel = 'direcciones';

    public function isReadOnly(): bool
    {
        return false;
    }

    /**
     * Define el formulario para registrar y editar direcciones.
     */
    public function form(Form $form): Form
    {
        return $form
            ->columns(1)
            ->schema([
                Section::make('Tipo')
                    ->compact()
                    ->icon('heroicon-o-tag')
                    ->columns(1)
                    ->schema([
                        Select::make('type')
                            ->label('Tipo de dirección')
                            ->options(EmployeeAddress::getTypeOptions())
                            ->default('principal')
                            ->required()
                            ->native(false),
                    ]),

                Section::make('Ubicación')
                    ->compact()
                    ->icon('heroicon-o-map-pin')
                    ->columns(2)
                    ->schema([
                        Select::make('py_department_id')
                            ->label('Departamento')
                            ->options(PyDepartment::getOptions())
                            ->dehydrated(false)
                            ->live()
                            ->afterStateHydrated(function (Set $set, ?EmployeeAddress $record): void {
                                if ($record?->city) {
                                    $set('py_department_id', $record->city->py_department_id);
                                }
                            })
                            ->afterStateUpdated(fn (Set $set) => $set('py_city_id', null))
                            ->native(false)
                            ->searchable()
                            ->placeholder('Seleccionar departamento'),

                        Select::make('py_city_id')
                            ->label('Ciudad')
                            ->options(fn (Get $get) => PyCity::getOptions($get('py_department_id')))
                            ->required()
                            ->native(false)
                            ->searchable()
                            ->placeholder('Seleccionar ciudad'),

                        TextInput::make('neighborhood')
                            ->label('Barrio / Localidad')
                            ->maxLength(100)
                            ->placeholder('Ej: Barrio Obrero')
                            ->nullable(),

                        TextInput::make('street')
                            ->label('Calle / Número')
                            ->maxLength(200)
                            ->placeholder('Ej: Mcal. López 1234')
                            ->nullable(),
                    ]),

                Textarea::make('notes')
                    ->label('Notas')
                    ->rows(1)
                    ->maxLength(500)
                    ->nullable()
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Define la tabla de direcciones del empleado.
     */
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('street')
            ->columns([
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => EmployeeAddress::getTypeLabels()[$state] ?? $state)
                    ->color(fn (string $state) => EmployeeAddress::getTypeColors()[$state] ?? 'gray')
                    ->icon(fn (string $state) => EmployeeAddress::getTypeIcons()[$state] ?? 'heroicon-o-map-pin'),

                TextColumn::make('city.department.name')
                    ->label('Departamento')
                    ->placeholder('—'),

                TextColumn::make('city.name')
                    ->label('Ciudad')
                    ->searchable(query: fn ($query, string $search) => $query->whereHas(
                        'city',
                        fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ))
                    ->placeholder('—'),

                TextColumn::make('neighborhood')
                    ->label('Barrio')
                    ->placeholder('—'),

                TextColumn::make('street')
                    ->label('Calle / Número')
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['employee_id'] = $this->getOwnerRecord()->id;

                        return $data;
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary'),
                DeleteAction::make()
                    ->label('Eliminar')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->modalHeading('¿Eliminar dirección?')
                    ->modalSubmitActionLabel('Sí, eliminar'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Sin direcciones registradas')
            ->emptyStateDescription('Agregá las direcciones del empleado.')
            ->emptyStateIcon('heroicon-o-map-pin');
    }
}
