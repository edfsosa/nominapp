<?php

namespace App\Filament\Resources;

use App\Models\FaceEnrollment;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use App\Filament\Resources\FaceEnrollmentResource\Pages;

class FaceEnrollmentResource extends Resource
{
    // Configuración general del recurso
    protected static ?string $model = FaceEnrollment::class;
    protected static ?string $navigationLabel = 'Registro Facial';
    protected static ?string $label = 'registro facial';
    protected static ?string $pluralLabel = 'registros faciales';
    protected static ?string $slug = 'registros-faciales';
    protected static ?string $navigationIcon = 'heroicon-o-finger-print';
    protected static ?string $navigationGroup = 'Asistencias';
    protected static ?int $navigationSort = 3;

    /**
     * Define la tabla del recurso, con columnas, filtros y acciones personalizadas para la gestión de registros faciales.
     *
     * @param Table $table
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee.full_name')
                    ->label('Empleado')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['first_name']),

                TextColumn::make('employee.ci')
                    ->label('CI')
                    ->badge()
                    ->color('gray')
                    ->copyable()
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => FaceEnrollment::getStatusLabel($state))
                    ->color(fn(string $state): string => FaceEnrollment::getStatusColor($state))
                    ->icon(fn(string $state): string => FaceEnrollment::getStatusIcon($state)),

                TextColumn::make('expires_at')
                    ->label('Expira')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->description(fn(FaceEnrollment $record): string => $record->isExpired() && $record->isPendingCapture() ? 'Expirado' : ''),

                TextColumn::make('captured_at')
                    ->label('Capturado')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('generatedBy.name')
                    ->label('Generado por')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('reviewedBy.name')
                    ->label('Revisado por')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('reviewed_at')
                    ->label('Revisado')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(FaceEnrollment::getStatusOptions())
                    ->native(false),
            ])
            ->actions([
                // Aprobar registro
                Action::make('approve')
                    ->label('Aprobar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn(FaceEnrollment $record) => $record->isPendingApproval())
                    ->requiresConfirmation()
                    ->modalHeading('Aprobar Registro Facial')
                    ->modalDescription(fn(FaceEnrollment $record) => "¿Aprobar el registro facial de {$record->employee->first_name} {$record->employee->last_name}? El descriptor será asignado al empleado para marcación de asistencia.")
                    ->modalSubmitActionLabel('Aprobar')
                    ->form([
                        Textarea::make('review_notes')
                            ->label('Notas de revisión')
                            ->placeholder('Notas opcionales...')
                            ->rows(2),
                    ])
                    ->action(function (FaceEnrollment $record, array $data) {
                        $result = $record->approve(Auth::id(), $data['review_notes'] ?? null);

                        Notification::make()
                            ->success()
                            ->title('Registro Facial Aprobado')
                            ->body($result['message'])
                            ->send();
                    }),

                // Rechazar registro
                Action::make('reject')
                    ->label('Rechazar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn(FaceEnrollment $record) => $record->isPendingApproval())
                    ->requiresConfirmation()
                    ->modalHeading('Rechazar Registro Facial')
                    ->modalDescription(fn(FaceEnrollment $record) => "¿Rechazar el registro facial de {$record->employee->first_name} {$record->employee->last_name}?")
                    ->modalSubmitActionLabel('Rechazar')
                    ->form([
                        Textarea::make('review_notes')
                            ->label('Motivo del rechazo')
                            ->placeholder('Indique el motivo...')
                            ->rows(3)
                            ->required(),
                    ])
                    ->action(function (FaceEnrollment $record, array $data) {
                        $result = $record->reject(Auth::id(), $data['review_notes']);

                        Notification::make()
                            ->warning()
                            ->title('Registro Facial Rechazado')
                            ->body($result['message'])
                            ->send();
                    }),

                // Copiar enlace
                Action::make('copy_link')
                    ->label('Copiar Enlace')
                    ->icon('heroicon-o-clipboard')
                    ->color('gray')
                    ->visible(fn(FaceEnrollment $record) => $record->isPendingCapture() && !$record->isExpired())
                    ->action(function (FaceEnrollment $record) {
                        $url = route('face-enrollment.show', $record->token);

                        Notification::make()
                            ->info()
                            ->title('Enlace de Registro')
                            ->body($url)
                            ->persistent()
                            ->actions([
                                \Filament\Notifications\Actions\Action::make('open')
                                    ->label('Abrir')
                                    ->url($url)
                                    ->openUrlInNewTab(),
                            ])
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('bulk_approve')
                        ->label('Aprobar seleccionados')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Aprobar Registros Faciales')
                        ->modalDescription('¿Aprobar todos los registros faciales seleccionados?')
                        ->action(function (Collection $records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->isPendingApproval()) {
                                    $record->approve(Auth::id());
                                    $count++;
                                }
                            }

                            Notification::make()
                                ->success()
                                ->title("$count registro(s) aprobado(s)")
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('bulk_reject')
                        ->label('Rechazar seleccionados')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Rechazar Registros Faciales')
                        ->form([
                            Textarea::make('review_notes')
                                ->label('Motivo del rechazo')
                                ->required()
                                ->rows(3),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->isPendingApproval()) {
                                    $record->reject(Auth::id(), $data['review_notes']);
                                    $count++;
                                }
                            }

                            Notification::make()
                                ->warning()
                                ->title("$count registro(s) rechazado(s)")
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    /**
     * Devuelve las páginas del recurso, en este caso solo la página de listado personalizada con pestañas
     *
     * @return array
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFaceEnrollments::route('/'),
        ];
    }

    /**
     * Devuelve el número de registros faciales pendientes de aprobación para mostrar en el badge de navegación
     *
     * @return string|null
     */
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'pending_approval')->count() ?: null;
    }

    /**
     * Devuelve el color del badge de navegación
     *
     * @return string|null
     */
    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /**
     * Devuelve el tooltip para el badge de navegación
     *
     * @return string|null
     */
    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Registros faciales pendientes de aprobación';
    }
}
