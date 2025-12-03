<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

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
                    ->closeOnDateSelection()
                    ->native(false)
                    ->required()
                    ->maxDate(fn(Get $get) => $get('end_date')),

                DatePicker::make('end_date')
                    ->label('Fecha de fin')
                    ->closeOnDateSelection()
                    ->native(false)
                    ->required()
                    ->minDate(fn(Get $get) => $get('start_date'))
                    ->afterOrEqual('start_date'),

                Select::make('type')
                    ->label('Tipo de vacación')
                    ->options([
                        'paid' => 'Remunerada',
                        'unpaid' => 'No Remunerada',
                    ])
                    ->default('paid')
                    ->native(false)
                    ->required(),

                Select::make('status')
                    ->label('Estado')
                    ->options([
                        'pending' => 'Pendiente',
                        'approved' => 'Aprobado',
                        'rejected' => 'Rechazado',
                    ])
                    ->default('pending')
                    ->native(false)
                    ->required()
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
                    ->getStateUsing(
                        fn($record) =>
                        $record->start_date->format('d/m/Y') . ' → ' . $record->end_date->format('d/m/Y')
                    )
                    ->description(
                        fn($record) =>
                        $record->start_date->diffInDays($record->end_date) + 1 . ' ' .
                            ($record->start_date->diffInDays($record->end_date) + 1 === 1 ? 'día' : 'días')
                    )
                    ->icon('heroicon-o-calendar-days')
                    ->sortable(['start_date', 'end_date'])
                    ->searchable(['start_date', 'end_date']),

                TextColumn::make('type')
                    ->label('Tipo')
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'paid' => 'Remunerada',
                        'unpaid' => 'No Remunerada',
                        default => $state,
                    })
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'paid' => 'success',
                        'unpaid' => 'warning',
                        default => 'gray',
                    })
                    ->icon(fn(string $state): string => match ($state) {
                        'paid' => 'heroicon-o-currency-dollar',
                        'unpaid' => 'heroicon-o-minus-circle',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->sortable()
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn(string $state): string => match ($state) {
                        'pending' => 'heroicon-o-clock',
                        'approved' => 'heroicon-o-check-circle',
                        'rejected' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'pending' => 'Pendiente',
                        'approved' => 'Aprobado',
                        'rejected' => 'Rechazado',
                        default => $state,
                    })
                    ->sortable()
                    ->searchable(),

                TextColumn::make('reason')
                    ->label('Motivo')
                    ->limit(40)
                    ->tooltip(fn($record) => $record->reason)
                    ->placeholder('Sin motivo especificado')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Solicitado el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->since()
                    ->description(fn($record) => $record->created_at->format('d/m/Y H:i'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Última actualización')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'paid' => 'Remunerada',
                        'unpaid' => 'No Remunerada',
                    ])
                    ->native(false),

                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'pending' => 'Pendiente',
                        'approved' => 'Aprobado',
                        'rejected' => 'Rechazado',
                    ])
                    ->multiple()
                    ->native(false),

                Filter::make('current_year')
                    ->label('Año actual')
                    ->query(fn($query) => $query->whereYear('start_date', now()->year))
                    ->default(),

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
                    ->icon('heroicon-o-plus-circle')
                    ->modalHeading('Solicitar nuevas vacaciones')
                    ->modalSubmitActionLabel('Solicitar')
                    ->modalWidth('2xl'),
            ])
            ->actions([
                Action::make('approve')
                    ->label('Aprobar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn($record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Aprobar solicitud de vacaciones')
                    ->modalDescription(
                        fn($record) =>
                        'Se aprobarán ' . ($record->start_date->diffInDays($record->end_date) + 1) .
                            ' días de vacaciones del ' . $record->start_date->format('d/m/Y') .
                            ' al ' . $record->end_date->format('d/m/Y')
                    )
                    ->action(fn($record) => $record->update(['status' => 'approved'])),

                Action::make('reject')
                    ->label('Rechazar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn($record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Rechazar solicitud de vacaciones')
                    ->modalDescription('¿Estás seguro de que deseas rechazar esta solicitud de vacaciones?')
                    ->action(fn($record) => $record->update(['status' => 'rejected'])),

                EditAction::make()
                    ->label('Editar')
                    ->modalHeading('Editar vacaciones')
                    ->modalSubmitActionLabel('Guardar cambios')
                    ->modalWidth('2xl'),

                DeleteAction::make()
                    ->label('Eliminar')
                    ->modalHeading('Eliminar vacaciones')
                    ->modalDescription('¿Estás seguro de que deseas eliminar esta solicitud de vacaciones? Esta acción no se puede deshacer.'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('approve_selected')
                        ->label('Aprobar seleccionadas')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Aprobar vacaciones seleccionadas')
                        ->modalDescription('¿Estás seguro de que deseas aprobar todas las solicitudes seleccionadas?')
                        ->action(fn($records) => $records->each->update(['status' => 'approved'])),

                    BulkAction::make('reject_selected')
                        ->label('Rechazar seleccionadas')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Rechazar vacaciones seleccionadas')
                        ->modalDescription('¿Estás seguro de que deseas rechazar todas las solicitudes seleccionadas?')
                        ->action(fn($records) => $records->each->update(['status' => 'rejected'])),

                    DeleteBulkAction::make()
                        ->label('Eliminar seleccionadas')
                        ->modalHeading('Eliminar vacaciones')
                        ->modalDescription('¿Estás seguro de que deseas eliminar estas solicitudes? Esta acción no se puede deshacer.'),
                ]),
            ])
            ->defaultSort('start_date', 'desc')
            ->emptyStateHeading('No hay vacaciones registradas')
            ->emptyStateDescription('Comienza solicitando las vacaciones del empleado')
            ->emptyStateIcon('heroicon-o-sun')
            ->emptyStateActions([
                CreateAction::make()
                    ->label('Solicitar primera vacación')
                    ->icon('heroicon-o-plus-circle'),
            ]);
    }
}
