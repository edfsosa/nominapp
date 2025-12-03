<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';
    protected static ?string $title = 'Documentos';
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?string $modelLabel = 'Documento';
    protected static ?string $pluralModelLabel = 'Documentos';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Nombre del documento')
                    ->placeholder('Ej: Contrato laboral, CV, Certificado IPS...')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                FileUpload::make('file_path')
                    ->label('Archivo')
                    ->helperText('Formatos permitidos: PDF, imágenes (JPEG, PNG, GIF) y documentos de Office (Word, Excel, PowerPoint). Tamaño máximo: 10 MB')
                    ->disk('public')
                    ->directory('documents')
                    ->acceptedFileTypes([
                        'application/pdf', // PDF
                        'image/jpeg', // Imágenes JPEG
                        'image/jpg', // Imágenes JPG
                        'image/png', // Imágenes PNG
                        'image/gif', // Imágenes GIF
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',  // Word (.docx)
                        'application/msword', // Word (.doc)
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // Excel (.xlsx)
                        'application/vnd.ms-excel', // Excel (.xls)
                        'application/vnd.openxmlformats-officedocument.presentationml.presentation', // PowerPoint (.pptx)
                        'application/vnd.ms-powerpoint', // PowerPoint (.ppt)
                    ])
                    ->maxSize(10240) // 10 MB
                    ->downloadable()
                    ->previewable()
                    ->openable()
                    ->required()
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre del documento')
                    ->sortable()
                    ->searchable()
                    ->wrap()
                    ->weight('medium'),

                TextColumn::make('file_type')
                    ->label('Tipo')
                    ->getStateUsing(function ($record) {
                        $extension = pathinfo($record->file_path, PATHINFO_EXTENSION);
                        return strtoupper($extension);
                    })
                    ->badge()
                    ->color(fn(string $state): string => match (strtolower($state)) {
                        'pdf' => 'danger',
                        'jpg', 'jpeg', 'png', 'gif' => 'success',
                        'docx', 'doc' => 'info',
                        'xlsx', 'xls' => 'warning',
                        'pptx', 'ppt' => 'primary',
                        default => 'gray',
                    })
                    ->icon(fn(string $state): string => match (strtolower($state)) {
                        'pdf' => 'heroicon-o-document-text',
                        'jpg', 'jpeg', 'png', 'gif' => 'heroicon-o-photo',
                        'docx', 'doc' => 'heroicon-o-document',
                        'xlsx', 'xls' => 'heroicon-o-table-cells',
                        'pptx', 'ppt' => 'heroicon-o-presentation-chart-bar',
                        default => 'heroicon-o-document',
                    })
                    ->sortable(),

                TextColumn::make('file_size')
                    ->label('Tamaño')
                    ->getStateUsing(function ($record) {
                        $path = storage_path('app/public/' . $record->file_path);
                        if (file_exists($path)) {
                            $bytes = filesize($path);
                            if ($bytes >= 1048576) {
                                return number_format($bytes / 1048576, 2) . ' MB';
                            } elseif ($bytes >= 1024) {
                                return number_format($bytes / 1024, 2) . ' KB';
                            }
                            return $bytes . ' B';
                        }
                        return 'N/A';
                    })
                    ->alignEnd(),

                TextColumn::make('created_at')
                    ->label('Fecha de carga')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->since()
                    ->description(fn($record): string => $record->created_at->format('d/m/Y H:i')),

                TextColumn::make('updated_at')
                    ->label('Última modificación')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('file_type')
                    ->label('Tipo de archivo')
                    ->options([
                        'pdf' => 'PDF',
                        'jpg,jpeg,png,gif' => 'Imágenes',
                        'docx,doc' => 'Word',
                        'xlsx,xls' => 'Excel',
                        'pptx,ppt' => 'PowerPoint',
                    ])
                    ->query(function ($query, $state) {
                        if (!$state['value']) {
                            return $query;
                        }
                        $extensions = explode(',', $state['value']);
                        return $query->where(function ($q) use ($extensions) {
                            foreach ($extensions as $ext) {
                                $q->orWhere('file_path', 'like', '%.' . $ext);
                            }
                        });
                    })
                    ->native(false)
                    ->placeholder('Selecciona un tipo de archivo'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Subir documento')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->modalHeading('Subir nuevo documento')
                    ->modalSubmitActionLabel('Subir')
                    ->modalWidth('lg'),
            ])
            ->actions([
                Action::make('ver')
                    ->label('Ver')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn($record) => asset('storage/' . $record->file_path))
                    ->openUrlInNewTab(),

                Action::make('descargar')
                    ->label('Descargar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function ($record) {
                        return Storage::disk('public')->download($record->file_path, $record->name . '.' . pathinfo($record->file_path, PATHINFO_EXTENSION));
                    }),

                EditAction::make()
                    ->label('Editar')
                    ->modalHeading('Editar documento')
                    ->modalSubmitActionLabel('Guardar cambios'),

                DeleteAction::make()
                    ->label('Eliminar')
                    ->modalHeading('Eliminar documento')
                    ->modalDescription('¿Estás seguro de que deseas eliminar este documento? Esta acción no se puede deshacer.')
                    ->before(function ($record) {
                        // Eliminar el archivo físico del storage
                        if (Storage::disk('public')->exists($record->file_path)) {
                            Storage::disk('public')->delete($record->file_path);
                        }
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar seleccionados')
                        ->modalHeading('Eliminar documentos')
                        ->modalDescription('¿Estás seguro de que deseas eliminar estos documentos? Esta acción no se puede deshacer.')
                        ->before(function ($records) {
                            // Eliminar los archivos físicos del storage
                            foreach ($records as $record) {
                                if (Storage::disk('public')->exists($record->file_path)) {
                                    Storage::disk('public')->delete($record->file_path);
                                }
                            }
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No hay documentos')
            ->emptyStateDescription('Comienza subiendo documentos del empleado')
            ->emptyStateIcon('heroicon-o-document-arrow-up')
            ->emptyStateActions([
                CreateAction::make()
                    ->label('Subir primer documento')
                    ->icon('heroicon-o-arrow-up-tray'),
            ]);
    }
}
