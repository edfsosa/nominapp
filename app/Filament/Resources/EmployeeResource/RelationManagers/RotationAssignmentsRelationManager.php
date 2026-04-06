<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use App\Models\RotationAssignment;
use App\Models\RotationPattern;
use App\Models\ShiftTemplate;
use App\Services\RotationService;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

/**
 * RelationManager para gestionar el historial de asignaciones de patrón rotativo de un empleado.
 */
class RotationAssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'rotationAssignments';
    protected static ?string $title = 'Rotación de Turnos';
    protected static ?string $modelLabel = 'Asignación';
    protected static ?string $pluralModelLabel = 'Asignaciones de Rotación';

    /**
     * Define el formulario de asignación de patrón rotativo.
     *
     * @param  Form  $form
     * @return Form
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->columns(2)
                    ->schema([
                        Select::make('pattern_id')
                            ->label('Patrón de rotación')
                            ->options(function () {
                                $companyId = $this->getOwnerRecord()->branch?->company_id;

                                return RotationPattern::query()
                                    ->when($companyId, fn($q) => $q->where('company_id', $companyId))
                                    ->where('is_active', true)
                                    ->orderBy('name')
                                    ->pluck('name', 'id');
                            })
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required()
                            ->live()
                            ->columnSpanFull(),

                        TextInput::make('start_index')
                            ->label('Posición inicial en el ciclo')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required()
                            ->helperText(function (Get $get) {
                                $patternId = $get('pattern_id');
                                if (! $patternId) {
                                    return 'Seleccioná primero el patrón para ver el rango válido.';
                                }

                                $pattern = RotationPattern::find($patternId);
                                $length  = $pattern?->cycle_length ?? 0;

                                return "0 = primer día del ciclo · Máximo: {$length} días → índice máximo " . ($length - 1);
                            }),

                        DatePicker::make('valid_from')
                            ->label('Vigente desde')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->default(today())
                            ->required()
                            ->closeOnDateSelection(),

                        DatePicker::make('valid_until')
                            ->label('Vigente hasta')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->closeOnDateSelection()
                            ->after('valid_from')
                            ->helperText('Dejar vacío si la rotación es indefinida.'),

                        Textarea::make('notes')
                            ->label('Notas')
                            ->placeholder('Ej: Cambio por necesidad operativa')
                            ->rows(2)
                            ->maxLength(200)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Define la tabla de historial de rotación del empleado.
     *
     * @param  Table  $table
     * @return Table
     */
    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn($query) => $query->with('pattern')->latest('valid_from'))
            ->columns([
                TextColumn::make('pattern.name')
                    ->label('Patrón')
                    ->weight('medium')
                    ->icon('heroicon-o-arrow-path'),

                TextColumn::make('pattern.cycle_length')
                    ->label('Ciclo')
                    ->state(fn(RotationAssignment $record) => $record->pattern?->cycle_length)
                    ->suffix(' días')
                    ->badge()
                    ->color('info'),

                TextColumn::make('start_index')
                    ->label('Inicio ciclo')
                    ->formatStateUsing(fn($state) => "Día " . ($state + 1))
                    ->badge()
                    ->color('gray'),

                TextColumn::make('valid_from')
                    ->label('Desde')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('valid_until')
                    ->label('Hasta')
                    ->date('d/m/Y')
                    ->placeholder('Vigente')
                    ->color(fn($state) => $state === null ? 'success' : null),

                TextColumn::make('notes')
                    ->label('Notas')
                    ->placeholder('—')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Asignar Rotación')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Asignar patrón de rotación')
                    ->using(function (array $data): RotationAssignment {
                        try {
                            return RotationService::assign(
                                employee:    $this->getOwnerRecord(),
                                pattern:     RotationPattern::findOrFail($data['pattern_id']),
                                validFrom:   Carbon::parse($data['valid_from']),
                                startIndex:  (int) ($data['start_index'] ?? 0),
                                validUntil:  isset($data['valid_until']) ? Carbon::parse($data['valid_until']) : null,
                                notes:       $data['notes'] ?? null,
                            );
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->danger()
                                ->title('No se pudo asignar la rotación')
                                ->body(collect($e->errors())->flatten()->first())
                                ->persistent()
                                ->send();

                            throw $e;
                        }
                    })
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Rotación asignada')
                            ->body('El patrón de rotación fue asignado correctamente.')
                    ),
            ])
            ->actions([
                EditAction::make()
                    ->modalHeading('Editar asignación de rotación')
                    ->successNotificationTitle('Asignación actualizada'),

                Action::make('close')
                    ->label('Cerrar')
                    ->icon('heroicon-o-x-circle')
                    ->color('warning')
                    ->tooltip('Cerrar esta rotación con fecha de hoy')
                    ->visible(fn(RotationAssignment $record) => $record->valid_until === null)
                    ->requiresConfirmation()
                    ->modalHeading('Cerrar rotación')
                    ->modalDescription('Se cerrará este patrón con fecha de hoy. El empleado quedará sin rotación activa.')
                    ->modalSubmitActionLabel('Sí, cerrar')
                    ->action(function (RotationAssignment $record) {
                        RotationService::closeActive($record->employee, Carbon::today());

                        Notification::make()
                            ->success()
                            ->title('Rotación cerrada')
                            ->body('La asignación fue cerrada con fecha de hoy.')
                            ->send();
                    }),

                DeleteAction::make()
                    ->modalHeading('Eliminar asignación')
                    ->modalDescription('¿Estás seguro de que deseas eliminar esta asignación del historial?')
                    ->modalSubmitActionLabel('Sí, eliminar')
                    ->successNotificationTitle('Asignación eliminada'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalHeading('Eliminar asignaciones')
                        ->modalDescription('¿Estás seguro de que deseas eliminar las asignaciones seleccionadas?')
                        ->modalSubmitActionLabel('Sí, eliminar'),
                ]),
            ])
            ->defaultSort('valid_from', 'desc')
            ->emptyStateHeading('Sin rotación asignada')
            ->emptyStateDescription('Asigná un patrón de rotación al empleado para comenzar.')
            ->emptyStateIcon('heroicon-o-arrow-path');
    }
}
