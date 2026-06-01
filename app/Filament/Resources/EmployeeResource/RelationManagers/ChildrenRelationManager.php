<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use App\Models\EmployeeChild;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

/** Gestiona los hijos a cargo del empleado para el cálculo de bonificación familiar. */
class ChildrenRelationManager extends RelationManager
{
    protected static string $relationship = 'children';

    protected static ?string $title = 'Hijos';

    protected static ?string $modelLabel = 'hijo';

    protected static ?string $pluralModelLabel = 'hijos';

    public function isReadOnly(): bool
    {
        return false;
    }

    /**
     * Define el formulario para registrar y editar hijos a cargo.
     */
    public function form(Form $form): Form
    {
        return $form
            ->columns(1)
            ->schema([
                Section::make('Datos del hijo')
                    ->compact()
                    ->icon('heroicon-o-user')
                    ->columns(3)
                    ->schema([
                        TextInput::make('first_name')
                            ->label('Nombre(s)')
                            ->required()
                            ->maxLength(60)
                            ->placeholder('Ej: María José'),

                        TextInput::make('last_name')
                            ->label('Apellido(s)')
                            ->required()
                            ->maxLength(60)
                            ->placeholder('Ej: González López'),

                        TextInput::make('ci')
                            ->label('CI del menor')
                            ->nullable()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(99999999)
                            ->placeholder('Ej: 7654321')
                            ->helperText('Opcional. Número sin puntos ni guiones.'),

                        DatePicker::make('birth_date')
                            ->label('Fecha de nacimiento')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->maxDate(now())
                            ->closeOnDateSelection()
                            ->required()
                            ->placeholder('Seleccionar fecha')
                            ->helperText('Determina si el hijo califica para la bonificación familiar (menores de 18 años).'),
                    ]),

                Section::make('Certificado de Nacimiento')
                    ->compact()
                    ->icon('heroicon-o-paper-clip')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        FileUpload::make('birth_certificate_path')
                            ->label('Certificado (PDF)')
                            ->disk('public')
                            ->directory('employees/children/certificates')
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(5120)
                            ->downloadable()
                            ->previewable()
                            ->helperText('Opcional. Formato PDF, tamaño máximo 5 MB.')
                            ->getUploadedFileNameForStorageUsing(function ($file): string {
                                $employeeId = $this->getOwnerRecord()->id;
                                $ext = $file->getClientOriginalExtension();

                                return "cert_hijo_{$employeeId}_".now()->format('Y-m-d_H-i-s').".{$ext}";
                            })
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Define la tabla de hijos a cargo del empleado.
     */
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('first_name')
            ->defaultSort('birth_date', 'asc')
            ->columns([
                TextColumn::make('full_name')
                    ->label('Nombre')
                    ->getStateUsing(fn (EmployeeChild $record) => $record->full_name)
                    ->description(fn (EmployeeChild $record) => $record->ci ? 'CI: '.$record->ci : null)
                    ->searchable(query: fn (Builder $query, string $search) => $query
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                    ),

                TextColumn::make('birth_date')
                    ->label('Fecha de nacimiento')
                    ->date('d/m/Y')
                    ->description(fn (EmployeeChild $record) => $record->age.' años')
                    ->sortable(),

                TextColumn::make('is_eligible')
                    ->label('Elegibilidad')
                    ->badge()
                    ->getStateUsing(fn (EmployeeChild $record) => $record->is_eligible ? 'Menor de edad' : 'Mayor de edad')
                    ->color(fn (EmployeeChild $record) => $record->is_eligible ? 'success' : 'gray'),

                IconColumn::make('birth_certificate_path')
                    ->label('Certificado')
                    ->boolean()
                    ->getStateUsing(fn (EmployeeChild $record) => filled($record->birth_certificate_path))
                    ->trueIcon('heroicon-o-paper-clip')
                    ->falseIcon('heroicon-o-x-mark')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->tooltip(fn (EmployeeChild $record) => filled($record->birth_certificate_path) ? 'Certificado adjunto' : 'Sin certificado'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['employee_id'] = $this->getOwnerRecord()->id;

                        return $data;
                    }),
            ])
            ->actions([
                Action::make('download_certificate')
                    ->label('Descargar certificado')
                    ->icon('heroicon-o-paper-clip')
                    ->color('success')
                    ->visible(fn (EmployeeChild $record) => filled($record->birth_certificate_path))
                    ->url(fn (EmployeeChild $record) => $record->birth_certificate_url)
                    ->openUrlInNewTab(),

                EditAction::make()
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary'),

                DeleteAction::make()
                    ->label('Eliminar')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->modalHeading('¿Eliminar hijo?')
                    ->modalSubmitActionLabel('Sí, eliminar')
                    ->before(function (EmployeeChild $record) {
                        if ($record->birth_certificate_path) {
                            Storage::disk('public')->delete($record->birth_certificate_path);
                        }
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function ($records) {
                            foreach ($records as $record) {
                                if ($record->birth_certificate_path) {
                                    Storage::disk('public')->delete($record->birth_certificate_path);
                                }
                            }
                        }),
                ]),
            ])
            ->emptyStateHeading('Sin hijos registrados')
            ->emptyStateDescription('Registrá los hijos a cargo para gestionar la bonificación familiar.')
            ->emptyStateIcon('heroicon-o-user-group');
    }
}
