<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use App\Models\EmployeeLeave;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

/** Gestiona las licencias del empleado desde su vista de detalle. */
class LeavesRelationManager extends RelationManager
{
    protected static string $relationship = 'leaves';

    protected static ?string $title = 'Licencias';

    protected static ?string $modelLabel = 'Licencia';

    protected static ?string $pluralModelLabel = 'Licencias';

    public function isReadOnly(): bool
    {
        return false;
    }

    /**
     * Define el formulario para registrar y editar licencias.
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('type')
                    ->label('Tipo de licencia')
                    ->options(EmployeeLeave::getTypeOptions())
                    ->native(false)
                    ->searchable()
                    ->required()
                    ->columnSpanFull(),

                DatePicker::make('start_date')
                    ->label('Fecha de inicio')
                    ->default(now())
                    ->native(false)
                    ->required()
                    ->maxDate(fn (Get $get) => $get('end_date')),

                DatePicker::make('end_date')
                    ->label('Fecha de fin')
                    ->default(now())
                    ->native(false)
                    ->required()
                    ->minDate(fn (Get $get) => $get('start_date'))
                    ->afterOrEqual('start_date'),

                Textarea::make('reason')
                    ->label('Descripción o motivo')
                    ->placeholder('Describe el motivo de la licencia...')
                    ->rows(3)
                    ->maxLength(500)
                    ->nullable()
                    ->columnSpanFull(),

                FileUpload::make('document_path')
                    ->label('Documento comprobante')
                    ->helperText('Sube un certificado médico, solicitud u otro documento. Formatos: PDF o imágenes. Máximo 10 MB')
                    ->disk('public')
                    ->directory('employee_leaves')
                    ->visibility('public')
                    ->nullable()
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'image/gif'])
                    ->maxSize(10240)
                    ->downloadable()
                    ->previewable()
                    ->openable()
                    ->columnSpanFull(),
            ])
            ->columns(3);
    }

    /**
     * Define la tabla de licencias con columnas, filtros y acciones.
     */
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('type')
            ->columns([
                TextColumn::make('type')
                    ->label('Tipo de licencia')
                    ->formatStateUsing(fn ($state) => EmployeeLeave::getTypeOptions()[$state] ?? $state)
                    ->badge()
                    ->color(fn ($state) => EmployeeLeave::getTypeColors()[$state] ?? 'gray')
                    ->icon(fn ($state) => EmployeeLeave::getTypeIcons()[$state] ?? 'heroicon-o-document-text')
                    ->sortable()
                    ->searchable()
                    ->wrap(),

                TextColumn::make('period')
                    ->label('Período')
                    ->getStateUsing(
                        fn ($record) => $record->start_date->format('d/m/Y').' → '.$record->end_date->format('d/m/Y')
                    )
                    ->description(function ($record) {
                        $days = $record->start_date->diffInDays($record->end_date) + 1;

                        return $days.' '.($days === 1 ? 'día' : 'días');
                    })
                    ->sortable(['start_date', 'end_date']),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn ($state) => EmployeeLeave::getStatusColors()[$state] ?? 'gray')
                    ->icon(fn ($state) => EmployeeLeave::getStatusIcons()[$state] ?? 'heroicon-o-question-mark-circle')
                    ->formatStateUsing(fn ($state) => EmployeeLeave::getStatusOptions()[$state] ?? $state)
                    ->sortable()
                    ->searchable(),

                TextColumn::make('document_path')
                    ->label('Documento')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn ($state) => $state ? 'Adjunto' : 'Sin documento')
                    ->icon(fn ($state) => $state ? 'heroicon-o-paper-clip' : 'heroicon-o-minus-circle')
                    ->toggleable(),

                TextColumn::make('reason')
                    ->label('Motivo')
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->reason)
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->since()
                    ->description(fn ($record) => $record->created_at->format('d/m/Y H:i'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo de licencia')
                    ->options(EmployeeLeave::getTypeOptions())
                    ->multiple(),

                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(EmployeeLeave::getStatusOptions())
                    ->multiple(),

                Filter::make('current_year')
                    ->label('Año actual')
                    ->query(fn ($query) => $query->whereYear('start_date', now()->year))
                    ->default(),

                Filter::make('with_document')
                    ->label('Con documento')
                    ->query(fn ($query) => $query->whereNotNull('document_path')),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Nueva Licencia')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Registrar nueva licencia')
                    ->modalSubmitActionLabel('Registrar')
                    ->modalWidth('2xl'),
            ])
            ->actions([
                Action::make('approve')
                    ->label('Aprobar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Aprobar licencia')
                    ->modalDescription('¿Estás seguro de que deseas aprobar esta licencia?')
                    ->modalSubmitActionLabel('Sí, aprobar')
                    ->action(fn ($record) => $record->update(['status' => 'approved'])),

                Action::make('reject')
                    ->label('Rechazar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Rechazar licencia')
                    ->modalDescription('¿Estás seguro de que deseas rechazar esta licencia?')
                    ->modalSubmitActionLabel('Sí, rechazar')
                    ->action(fn ($record) => $record->update(['status' => 'rejected'])),

                Action::make('download')
                    ->label('Descargar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->visible(fn ($record) => $record->document_path !== null)
                    ->action(function ($record) {
                        return response()->download(
                            Storage::disk('public')->path($record->document_path),
                            'licencia_'.$record->type.'_'.$record->start_date->format('Y-m-d').'.'.pathinfo($record->document_path, PATHINFO_EXTENSION)
                        );
                    }),

                ActionGroup::make([
                    EditAction::make()
                        ->label('Editar')
                        ->icon('heroicon-o-pencil-square')
                        ->color('primary')
                        ->modalHeading('Editar licencia')
                        ->modalSubmitActionLabel('Guardar cambios')
                        ->modalWidth('2xl'),

                    DeleteAction::make()
                        ->label('Eliminar')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->modalHeading('¿Eliminar licencia?')
                        ->modalDescription('¿Estás seguro de que deseas eliminar esta licencia? Esta acción no se puede deshacer.')
                        ->modalSubmitActionLabel('Sí, eliminar')
                        ->before(function ($record) {
                            if ($record->document_path && Storage::disk('public')->exists($record->document_path)) {
                                Storage::disk('public')->delete($record->document_path);
                            }
                        }),
                ])
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->color('gray')
                    ->tooltip('Más acciones'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('approve_selected')
                        ->label('Aprobar')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Aprobar licencias')
                        ->modalDescription('Se aprobarán las licencias seleccionadas que estén en estado pendiente.')
                        ->modalSubmitActionLabel('Sí, aprobar')
                        ->action(fn ($records) => $records->each(
                            fn ($record) => $record->status === 'pending' && $record->update(['status' => 'approved'])
                        )),

                    BulkAction::make('reject_selected')
                        ->label('Rechazar')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Rechazar licencias')
                        ->modalDescription('Se rechazarán las licencias seleccionadas que estén en estado pendiente.')
                        ->modalSubmitActionLabel('Sí, rechazar')
                        ->action(fn ($records) => $records->each(
                            fn ($record) => $record->status === 'pending' && $record->update(['status' => 'rejected'])
                        )),

                    DeleteBulkAction::make()
                        ->modalHeading('Eliminar licencias')
                        ->modalDescription('¿Estás seguro de que deseas eliminar estas licencias? Esta acción no se puede deshacer.')
                        ->before(function ($records) {
                            foreach ($records as $record) {
                                if ($record->document_path && Storage::disk('public')->exists($record->document_path)) {
                                    Storage::disk('public')->delete($record->document_path);
                                }
                            }
                        }),
                ]),
            ])
            ->defaultSort('start_date', 'desc')
            ->emptyStateHeading('No hay licencias registradas')
            ->emptyStateDescription('Comienza registrando las licencias del empleado')
            ->emptyStateIcon('heroicon-o-calendar-days')
            ->emptyStateActions([
                CreateAction::make()
                    ->label('Nueva Licencia')
                    ->icon('heroicon-o-plus'),
            ]);
    }
}
