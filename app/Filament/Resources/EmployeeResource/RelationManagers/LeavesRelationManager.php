<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class LeavesRelationManager extends RelationManager
{
    protected static string $relationship = 'leaves';
    protected static ?string $title = 'Permisos y Licencias';
    protected static ?string $modelLabel = 'Permiso';
    protected static ?string $pluralModelLabel = 'Permisos';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('type')
                    ->label('Tipo de permiso')
                    ->options([
                        'medical_leave' => 'Reposo Médico',
                        'vacation' => 'Vacaciones',
                        'day_off' => 'Día Libre',
                        'maternity_leave' => 'Permiso por Maternidad',
                        'paternity_leave' => 'Permiso por Paternidad',
                        'unpaid_leave' => 'Permiso Sin Goce de Sueldo',
                        'other' => 'Otro',
                    ])
                    ->native(false)
                    ->searchable()
                    ->required()
                    ->columnSpanFull(),

                DatePicker::make('start_date')
                    ->label('Fecha de inicio')
                    ->default(now())
                    ->native(false)
                    ->required()
                    ->maxDate(fn(Get $get) => $get('end_date')),

                DatePicker::make('end_date')
                    ->label('Fecha de fin')
                    ->default(now())
                    ->native(false)
                    ->required()
                    ->minDate(fn(Get $get) => $get('start_date'))
                    ->afterOrEqual('start_date'),

                Select::make('status')
                    ->label('Estado')
                    ->options([
                        'pending' => 'Pendiente',
                        'approved' => 'Aprobado',
                        'rejected' => 'Rechazado',
                    ])
                    ->native(false)
                    ->default('pending')
                    ->required()
                    ->hiddenOn('create'),

                Textarea::make('reason')
                    ->label('Descripción o motivo')
                    ->placeholder('Describe el motivo del permiso...')
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
                    ->maxSize(10240) // 10 MB
                    ->downloadable()
                    ->previewable()
                    ->openable()
                    ->columnSpanFull(),
            ])
            ->columns(3);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('type')
            ->columns([
                TextColumn::make('type')
                    ->label('Tipo de permiso')
                    ->formatStateUsing(fn($state) => match ($state) {
                        'medical_leave' => 'Reposo Médico',
                        'vacation' => 'Vacaciones',
                        'day_off' => 'Día Libre',
                        'maternity_leave' => 'Permiso por Maternidad',
                        'paternity_leave' => 'Permiso por Paternidad',
                        'unpaid_leave' => 'Sin Goce de Sueldo',
                        'other' => 'Otro',
                        default => $state,
                    })
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'medical_leave' => 'danger',
                        'vacation' => 'success',
                        'day_off' => 'info',
                        'maternity_leave' => 'pink',
                        'paternity_leave' => 'blue',
                        'unpaid_leave' => 'warning',
                        'other' => 'gray',
                        default => 'gray',
                    })
                    ->icon(fn($state) => match ($state) {
                        'medical_leave' => 'heroicon-o-heart',
                        'vacation' => 'heroicon-o-sun',
                        'day_off' => 'heroicon-o-calendar',
                        'maternity_leave' => 'heroicon-o-home',
                        'paternity_leave' => 'heroicon-o-home',
                        'unpaid_leave' => 'heroicon-o-pause-circle',
                        'other' => 'heroicon-o-document-text',
                        default => 'heroicon-o-document-text',
                    })
                    ->sortable()
                    ->searchable()
                    ->wrap(),

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
                    ->sortable(['start_date', 'end_date']),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn($state) => match ($state) {
                        'pending' => 'heroicon-o-clock',
                        'approved' => 'heroicon-o-check-circle',
                        'rejected' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->formatStateUsing(fn($state) => match ($state) {
                        'pending' => 'Pendiente',
                        'approved' => 'Aprobado',
                        'rejected' => 'Rechazado',
                        default => $state,
                    })
                    ->sortable()
                    ->searchable(),

                TextColumn::make('document_path')
                    ->label('Documento')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn($state) => $state ? 'Adjunto' : 'Sin documento')
                    ->icon(fn($state) => $state ? 'heroicon-o-paper-clip' : 'heroicon-o-minus-circle')
                    ->toggleable(),

                TextColumn::make('reason')
                    ->label('Motivo')
                    ->limit(40)
                    ->tooltip(fn($record) => $record->reason)
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->since()
                    ->description(fn($record) => $record->created_at->format('d/m/Y H:i'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo de permiso')
                    ->options([
                        'medical_leave' => 'Reposo Médico',
                        'vacation' => 'Vacaciones',
                        'day_off' => 'Día Libre',
                        'maternity_leave' => 'Permiso por Maternidad',
                        'paternity_leave' => 'Permiso por Paternidad',
                        'unpaid_leave' => 'Sin Goce de Sueldo',
                        'other' => 'Otro',
                    ])
                    ->multiple(),

                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'pending' => 'Pendiente',
                        'approved' => 'Aprobado',
                        'rejected' => 'Rechazado',
                    ])
                    ->multiple(),

                Filter::make('current_year')
                    ->label('Año actual')
                    ->query(fn($query) => $query->whereYear('start_date', now()->year))
                    ->default(),

                Filter::make('with_document')
                    ->label('Con documento')
                    ->query(fn($query) => $query->whereNotNull('document_path')),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Nuevo Permiso')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Registrar nuevo permiso')
                    ->modalSubmitActionLabel('Registrar')
                    ->modalWidth('2xl'),
            ])
            ->actions([
                Action::make('approve')
                    ->label('Aprobar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn($record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Aprobar permiso')
                    ->modalDescription('¿Estás seguro de que deseas aprobar este permiso?')
                    ->action(fn($record) => $record->update(['status' => 'approved'])),

                Action::make('reject')
                    ->label('Rechazar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn($record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Rechazar permiso')
                    ->modalDescription('¿Estás seguro de que deseas rechazar este permiso?')
                    ->action(fn($record) => $record->update(['status' => 'rejected'])),

                Action::make('download')
                    ->label('Descargar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->visible(fn($record) => $record->document_path !== null)
                    ->action(function ($record) {
                        return Storage::disk('public')->download(
                            $record->document_path,
                            'permiso_' . $record->type . '_' . $record->start_date->format('Y-m-d') . '.' . pathinfo($record->document_path, PATHINFO_EXTENSION)
                        );
                    }),

                EditAction::make()
                    ->modalHeading('Editar permiso')
                    ->modalSubmitActionLabel('Guardar cambios')
                    ->modalWidth('2xl'),

                DeleteAction::make()
                    ->modalHeading('Eliminar permiso')
                    ->modalDescription('¿Estás seguro de que deseas eliminar este permiso? Esta acción no se puede deshacer.')
                    ->before(function ($record) {
                        // Eliminar el documento físico si existe
                        if ($record->document_path && Storage::disk('public')->exists($record->document_path)) {
                            Storage::disk('public')->delete($record->document_path);
                        }
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('approve_selected')
                        ->label('Aprobar')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Aprobar permisos')
                        ->modalDescription('Se aprobarán los permisos seleccionados que estén en estado pendiente.')
                        ->action(fn($records) => $records->each->update(['status' => 'approved'])),

                    BulkAction::make('reject_selected')
                        ->label('Rechazar')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Rechazar permisos')
                        ->modalDescription('Se rechazarán los permisos seleccionados que estén en estado pendiente.')
                        ->action(fn($records) => $records->each->update(['status' => 'rejected'])),

                    DeleteBulkAction::make()
                        ->modalHeading('Eliminar permisos')
                        ->modalDescription('¿Estás seguro de que deseas eliminar estos permisos? Esta acción no se puede deshacer.')
                        ->before(function ($records) {
                            // Eliminar los documentos físicos
                            foreach ($records as $record) {
                                if ($record->document_path && Storage::disk('public')->exists($record->document_path)) {
                                    Storage::disk('public')->delete($record->document_path);
                                }
                            }
                        }),
                ]),
            ])
            ->defaultSort('start_date', 'desc')
            ->emptyStateHeading('No hay permisos registrados')
            ->emptyStateDescription('Comienza registrando los permisos y licencias del empleado')
            ->emptyStateIcon('heroicon-o-calendar-days')
            ->emptyStateActions([
                CreateAction::make()
                    ->label('Nuevo Permiso')
                    ->icon('heroicon-o-plus'),
            ]);
    }
}
