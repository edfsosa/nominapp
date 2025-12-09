<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VacationResource\Pages;
use App\Models\Vacation;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class VacationResource extends Resource
{
    protected static ?string $model = Vacation::class;
    protected static ?string $navigationGroup = 'Empleados';
    protected static ?string $navigationLabel = 'Vacaciones';
    protected static ?string $label = 'Vacación';
    protected static ?string $pluralLabel = 'Vacaciones';
    protected static ?string $slug = 'vacaciones';
    protected static ?string $navigationIcon = 'heroicon-o-sun';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Información del Empleado')
                    ->schema([
                        Select::make('employee_id')
                            ->label('Empleado')
                            ->relationship('employee', 'id', function ($query) {
                                $query->where('status', 'active');
                            })
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required()
                            ->getOptionLabelFromRecordUsing(function ($record) {
                                return "{$record->first_name} {$record->last_name} - CI: {$record->ci}";
                            })
                            ->columnSpanFull(),
                    ]),

                Section::make('Período de Vacaciones')
                    ->schema([
                        DatePicker::make('start_date')
                            ->label('Fecha de Inicio')
                            ->displayFormat('d/m/Y')
                            ->native(false)
                            ->closeOnDateSelection()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn($state, callable $set) => $set('end_date', null))
                            ->columnSpan(1),

                        DatePicker::make('end_date')
                            ->label('Fecha de Fin')
                            ->displayFormat('d/m/Y')
                            ->native(false)
                            ->closeOnDateSelection()
                            ->required()
                            ->minDate(fn($get) => $get('start_date'))
                            ->disabled(fn($get) => !$get('start_date'))
                            ->helperText('La fecha de fin debe ser posterior o igual a la fecha de inicio')
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Section::make('Detalles de la Solicitud')
                    ->schema([
                        Select::make('type')
                            ->label('Tipo de Vacaciones')
                            ->options([
                                'paid'   => 'Remuneradas',
                                'unpaid' => 'No Remuneradas',
                            ])
                            ->default('paid')
                            ->native(false)
                            ->required()
                            ->columnSpan(1),

                        Select::make('status')
                            ->label('Estado')
                            ->options([
                                'pending'  => 'Pendiente',
                                'approved' => 'Aprobado',
                                'rejected' => 'Rechazado',
                            ])
                            ->default('pending')
                            ->native(false)
                            ->required()
                            ->columnSpan(1),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee.ci')
                    ->label('CI')
                    ->sortable()
                    ->searchable()
                    ->copyable()
                    ->copyMessage('CI copiado')
                    ->weight('bold'),

                TextColumn::make('employee.full_name')
                    ->label('Empleado')
                    ->sortable(['first_name', 'last_name'])
                    ->searchable(['first_name', 'last_name'])
                    ->wrap(),

                TextColumn::make('start_date')
                    ->label('Inicio')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('Fin')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('days_requested')
                    ->label('Días')
                    ->alignCenter()
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn($state) => $state === 'paid' ? 'success' : 'gray')
                    ->formatStateUsing(fn($state) => $state === 'paid' ? 'Remuneradas' : 'No Remuneradas')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Estado')
                    ->sortable()
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'pending'  => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default    => 'gray',
                    })
                    ->formatStateUsing(fn($state) => match ($state) {
                        'pending'  => 'Pendiente',
                        'approved' => 'Aprobado',
                        'rejected' => 'Rechazado',
                        default    => $state,
                    }),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('start_date', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->placeholder('Todos')
                    ->options([
                        'pending'  => 'Pendiente',
                        'approved' => 'Aprobado',
                        'rejected' => 'Rechazado',
                    ])
                    ->native(false),

                SelectFilter::make('type')
                    ->label('Tipo')
                    ->placeholder('Todos')
                    ->options([
                        'paid'   => 'Remuneradas',
                        'unpaid' => 'No Remuneradas',
                    ])
                    ->native(false),

                Filter::make('current_year')
                    ->label('Año Actual')
                    ->query(fn($query) => $query->whereYear('start_date', now()->year)),
            ])
            ->actions([
                Action::make('approve')
                    ->label('Aprobar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn($record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Aprobar Solicitud de Vacaciones')
                    ->modalDescription(fn($record) => "¿Está seguro de aprobar las vacaciones de {$record->employee->full_name} del {$record->start_date->format('d/m/Y')} al {$record->end_date->format('d/m/Y')}?")
                    ->action(function ($record) {
                        $record->update(['status' => 'approved']);
                        Notification::make()
                            ->title('Vacaciones aprobadas')
                            ->body("Las vacaciones de {$record->employee->full_name} fueron aprobadas exitosamente.")
                            ->success()
                            ->send();
                    }),

                Action::make('reject')
                    ->label('Rechazar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn($record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Rechazar Solicitud de Vacaciones')
                    ->modalDescription(fn($record) => "¿Está seguro de rechazar las vacaciones de {$record->employee->full_name}?")
                    ->action(function ($record) {
                        $record->update(['status' => 'rejected']);
                        Notification::make()
                            ->title('Vacaciones rechazadas')
                            ->body("Las vacaciones de {$record->employee->full_name} fueron rechazadas.")
                            ->warning()
                            ->send();
                    }),

                Action::make('generate_pdf')
                    ->label('Generar PDF')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->action(function ($record) {
                        $pdf = Pdf::loadView('pdf.vacation-form', ['vacation' => $record])
                            ->setPaper('a4', 'portrait');

                        return response()->streamDownload(function () use ($pdf) {
                            echo $pdf->output();
                        }, "vacaciones-{$record->employee->ci}-{$record->id}.pdf");
                    })
                    ->visible(fn($record) => in_array($record->status, ['approved', 'pending'])),


                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ExportBulkAction::make()
                        ->exports([
                            ExcelExport::make()
                                ->fromTable()
                                ->except([
                                    'created_at',
                                    'updated_at',
                                    'employee_id',
                                ])
                                ->withFilename('vacaciones_' . now()->format('d_m_Y_H_i_s')),
                        ])
                        ->label('Exportar seleccionados')
                        ->color('info')
                        ->icon('heroicon-o-arrow-down-tray'),
                ]),
            ])
            ->defaultSort('start_date', 'desc')
            ->emptyStateHeading('No hay solicitudes de vacaciones')
            ->emptyStateDescription('Las solicitudes de vacaciones aparecerán aquí una vez que sean creadas.')
            ->emptyStateIcon('heroicon-o-sun');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageVacations::route('/'),
        ];
    }
}
