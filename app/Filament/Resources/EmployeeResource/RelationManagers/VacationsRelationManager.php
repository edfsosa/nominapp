<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use App\Models\Vacation;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class VacationsRelationManager extends RelationManager
{
    protected static string $relationship = 'vacations';
    protected static ?string $title = 'Vacaciones';
    protected static ?string $modelLabel = 'Vacación';
    protected static ?string $pluralModelLabel = 'Vacaciones';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                DatePicker::make('start_date')
                    ->label('Fecha de inicio')
                    ->displayFormat('d/m/Y')
                    ->closeOnDateSelection()
                    ->native(false)
                    ->required()
                    ->maxDate(fn(Get $get) => $get('end_date'))
                    ->helperText('Selecciona la fecha de inicio de las vacaciones')
                    ->reactive(),

                DatePicker::make('end_date')
                    ->label('Fecha de fin')
                    ->displayFormat('d/m/Y')
                    ->closeOnDateSelection()
                    ->native(false)
                    ->required()
                    ->minDate(fn(Get $get) => $get('start_date') ?: now())
                    ->helperText('Selecciona la fecha de fin de las vacaciones')
                    ->reactive(),

                Select::make('type')
                    ->label('Tipo de vacación')
                    ->options(Vacation::getTypeOptions())
                    ->default('paid')
                    ->native(false)
                    ->required(),

                Select::make('status')
                    ->label('Estado')
                    ->options(Vacation::getStatusOptions())
                    ->default('pending')
                    ->native(false)
                    ->required()
                    ->disabled()
                    ->dehydrated()
                    ->helperText('El estado solo puede cambiarse usando los botones Aprobar/Rechazar')
                    ->hiddenOn('create'),

                Textarea::make('reason')
                    ->label('Motivo o comentarios')
                    ->placeholder('Opcional: Agrega notas sobre esta solicitud de vacaciones...')
                    ->rows(3)
                    ->maxLength(500)
                    ->nullable()
                    ->columnSpanFull(),
            ])
            ->columns(3);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('period')
                    ->label('Período')
                    ->state(fn(Vacation $record): string => $record->period_formatted)
                    ->description(fn(Vacation $record): string => $record->days_description)
                    ->icon('heroicon-o-calendar-days')
                    ->sortable(['start_date', 'end_date'])
                    ->searchable(['start_date', 'end_date']),

                TextColumn::make('type')
                    ->label('Tipo')
                    ->formatStateUsing(fn(Vacation $record): string => $record->type_label)
                    ->badge()
                    ->color(fn(Vacation $record): string => $record->type_color)
                    ->icon(fn(Vacation $record): string => $record->type_icon)
                    ->sortable()
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn(Vacation $record): string => $record->status_color)
                    ->icon(fn(Vacation $record): string => $record->status_icon)
                    ->formatStateUsing(fn(Vacation $record): string => $record->status_label)
                    ->sortable()
                    ->searchable(),

                TextColumn::make('created_at_description')
                    ->label('Solicitado el')
                    ->description(fn(Vacation $record): string => $record->created_at_since)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at_description')
                    ->label('Última actualización')
                    ->description(fn(Vacation $record): string => $record->updated_at_since)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(Vacation::getTypeOptions())
                    ->native(false),

                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(Vacation::getStatusOptions())
                    ->multiple()
                    ->native(false),

                Filter::make('upcoming')
                    ->label('Próximas vacaciones')
                    ->query(fn($query) => $query->where('start_date', '>=', now())),

                Filter::make('past')
                    ->label('Vacaciones pasadas')
                    ->query(fn($query) => $query->where('end_date', '<', now())),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Solicitar vacaciones')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Solicitar nuevas vacaciones')
                    ->modalSubmitActionLabel('Solicitar')
                    ->modalWidth('2xl'),
            ])
            ->actions([
                Action::make('approve')
                    ->label('Aprobar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn(Vacation $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Aprobar solicitud de vacaciones')
                    ->modalDescription(
                        fn(Vacation $record) =>
                        'Se aprobarán ' . $record->days_description .
                            ' de vacaciones del ' . $record->period_formatted
                    )
                    ->action(fn(Vacation $record) => $record->update(['status' => 'approved'])),

                Action::make('reject')
                    ->label('Rechazar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn(Vacation $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Rechazar solicitud de vacaciones')
                    ->modalDescription('¿Estás seguro de que deseas rechazar esta solicitud de vacaciones?')
                    ->action(fn(Vacation $record) => $record->update(['status' => 'rejected'])),

                EditAction::make()
                    ->modalHeading('Editar vacaciones')
                    ->modalSubmitActionLabel('Guardar cambios')
                    ->modalWidth('2xl'),

                DeleteAction::make()
                    ->modalHeading('Eliminar vacaciones')
                    ->modalDescription('¿Estás seguro de que deseas eliminar esta solicitud de vacaciones? Esta acción no se puede deshacer.'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('approve_selected')
                        ->label('Aprobar')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Aprobar vacaciones')
                        ->modalDescription('Se aprobarán las vacaciones seleccionadas que estén en estado pendiente.')
                        ->action(fn($records) => $records->each->update(['status' => 'approved'])),

                    BulkAction::make('reject_selected')
                        ->label('Rechazar')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Rechazar vacaciones')
                        ->modalDescription('Se rechazarán las vacaciones seleccionadas que estén en estado pendiente.')
                        ->action(fn($records) => $records->each->update(['status' => 'rejected'])),

                    DeleteBulkAction::make()
                        ->modalHeading('Eliminar vacaciones')
                        ->modalDescription('¿Estás seguro de que deseas eliminar estas solicitudes? Esta acción no se puede deshacer.'),
                ]),
            ])
            ->defaultSort('start_date', 'desc')
            ->emptyStateHeading('No hay vacaciones registradas')
            ->emptyStateDescription('Comienza solicitando las vacaciones del empleado')
            ->emptyStateIcon('heroicon-o-sun');
    }
}
