<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use App\Models\ShiftOverride;
use App\Models\ShiftTemplate;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * RelationManager para gestionar los overrides puntuales de turno de un empleado.
 */
class ShiftOverridesRelationManager extends RelationManager
{
    protected static string $relationship = 'shiftOverrides';

    protected static ?string $title = 'Cambios de Turno';

    protected static ?string $modelLabel = 'Cambio';

    protected static ?string $pluralModelLabel = 'Cambios de Turno';

    public function isReadOnly(): bool
    {
        return false;
    }

    /**
     * Define el formulario de creación de un override de turno.
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->columns(2)
                    ->schema([
                        DatePicker::make('override_date')
                            ->label('Fecha')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->required()
                            ->closeOnDateSelection(),

                        Select::make('shift_id')
                            ->label('Turno asignado')
                            ->options(function () {
                                $companyId = $this->getOwnerRecord()->branch?->company_id;

                                return ShiftTemplate::query()
                                    ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                                    ->where('is_active', true)
                                    ->orderByRaw('is_day_off ASC, name ASC')
                                    ->get()
                                    ->mapWithKeys(fn ($s) => [
                                        $s->id => $s->is_day_off
                                            ? "🌙 {$s->name}"
                                            : "⏰ {$s->name} ({$s->start_time} – {$s->end_time})",
                                    ]);
                            })
                            ->native(false)
                            ->searchable()
                            ->required(),

                        Select::make('reason_type')
                            ->label('Motivo')
                            ->options(ShiftOverride::getReasonTypeLabels())
                            ->native(false)
                            ->required(),

                        Textarea::make('notes')
                            ->label('Notas')
                            ->placeholder('Ej: Cubre a García por permiso médico')
                            ->rows(2)
                            ->maxLength(150)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Define la tabla de overrides de turno del empleado.
     */
    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('shift')->latest('override_date'))
            ->columns([
                TextColumn::make('override_date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable()
                    ->weight('medium'),

                ColorColumn::make('shift.color')
                    ->label('')
                    ->width('40px'),

                TextColumn::make('shift.name')
                    ->label('Turno')
                    ->weight('medium'),

                TextColumn::make('shift.start_time')
                    ->label('Entrada')
                    ->time('H:i')
                    ->placeholder('Franco'),

                TextColumn::make('shift.end_time')
                    ->label('Salida')
                    ->time('H:i')
                    ->placeholder('—'),

                TextColumn::make('reason_type')
                    ->label('Motivo')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ShiftOverride::getReasonTypeLabels()[$state] ?? $state)
                    ->color(fn ($state) => ShiftOverride::getReasonTypeColors()[$state] ?? 'gray'),

                TextColumn::make('notes')
                    ->label('Notas')
                    ->placeholder('—')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('reason_type')
                    ->label('Motivo')
                    ->options(ShiftOverride::getReasonTypeLabels())
                    ->native(false),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Registrar cambio')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Registrar cambio de turno')
                    ->mutateFormDataUsing(fn (array $data) => array_merge($data, [
                        'created_by_id' => auth()->id(),
                    ]))
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Cambio registrado')
                            ->body('El cambio de turno fue registrado correctamente.')
                    ),
            ])
            ->actions([
                EditAction::make()
                    ->modalHeading('Editar cambio de turno')
                    ->successNotificationTitle('Cambio actualizado'),

                DeleteAction::make()
                    ->modalHeading('Eliminar cambio')
                    ->modalDescription('Se eliminará el override y el empleado volverá al turno del patrón rotativo para esa fecha.')
                    ->modalSubmitActionLabel('Sí, eliminar')
                    ->successNotificationTitle('Cambio eliminado'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalHeading('Eliminar cambios')
                        ->modalDescription('¿Eliminar los cambios seleccionados?')
                        ->modalSubmitActionLabel('Sí, eliminar'),
                ]),
            ])
            ->defaultSort('override_date', 'desc')
            ->emptyStateHeading('Sin cambios de turno')
            ->emptyStateDescription('Los cambios puntuales de turno del empleado aparecerán aquí.')
            ->emptyStateIcon('heroicon-o-arrow-path-rounded-square');
    }
}
