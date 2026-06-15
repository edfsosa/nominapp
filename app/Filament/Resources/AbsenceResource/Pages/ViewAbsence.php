<?php

namespace App\Filament\Resources\AbsenceResource\Pages;

use App\Filament\Resources\AbsenceResource;
use App\Models\EmployeeLeave;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewAbsence extends ViewRecord
{
    protected static string $resource = AbsenceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('justify')
                ->label('Justificar')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->tooltip('Marcar esta ausencia como justificada')
                ->visible(fn () => ! $this->record->isJustified())
                ->modalHeading(fn () => $this->record->isUnjustified()
                    ? 'Cambiar a Justificada'
                    : 'Justificar Ausencia')
                ->modalDescription(fn () => $this->record->isUnjustified()
                    ? '⚠️ Esta ausencia tiene una deducción generada. Al justificarla se eliminará la deducción.'
                    : null)
                ->modalSubmitActionLabel('Justificar')
                ->mountUsing(function (Form $form) {
                    $hasLeaves = EmployeeLeave::where('employee_id', $this->record->employee_id)
                        ->where('status', 'approved')
                        ->whereDate('start_date', '<=', $this->record->attendanceDay->date)
                        ->whereDate('end_date', '>=', $this->record->attendanceDay->date)
                        ->exists();

                    $form->fill([
                        'has_approved_leaves' => $hasLeaves,
                        'justify_mode' => $hasLeaves ? 'existing' : 'quick',
                        'quick_leave_end_date' => $this->record->attendanceDay->date->format('Y-m-d'),
                    ]);
                })
                ->form([
                    Placeholder::make('employee_info')
                        ->label('Empleado')
                        ->content(fn () => $this->record->employee->full_name
                            .' — '
                            .$this->record->attendanceDay->date->translatedFormat('l d/m/Y')),

                    Hidden::make('has_approved_leaves'),

                    Radio::make('justify_mode')
                        ->label('¿Cómo justificar?')
                        ->options([
                            'existing' => 'Vincular a permiso ya existente',
                            'quick' => 'Crear permiso nuevo ahora',
                        ])
                        ->live()
                        ->required(),

                    // ── Modo: permiso existente ──────────────────────────────

                    Placeholder::make('no_leaves_notice')
                        ->label('Sin permisos disponibles')
                        ->content('No hay permisos aprobados que cubran esta fecha. Seleccioná "Crear permiso nuevo ahora" para generarlo en el acto.')
                        ->visible(fn (Get $get) => $get('justify_mode') === 'existing' && ! $get('has_approved_leaves')),

                    Select::make('employee_leave_id')
                        ->label('Permiso / Licencia')
                        ->options(fn () => EmployeeLeave::where('employee_id', $this->record->employee_id)
                            ->where('status', 'approved')
                            ->whereDate('start_date', '<=', $this->record->attendanceDay->date)
                            ->whereDate('end_date', '>=', $this->record->attendanceDay->date)
                            ->get()
                            ->mapWithKeys(fn ($leave) => [
                                $leave->id => (EmployeeLeave::getTypeOptions()[$leave->type] ?? $leave->type)
                                    .' ('.$leave->start_date->format('d/m/Y').' al '.$leave->end_date->format('d/m/Y').')',
                            ]))
                        ->native(false)
                        ->required(fn (Get $get) => $get('justify_mode') === 'existing' && (bool) $get('has_approved_leaves'))
                        ->helperText('Solo se muestran permisos aprobados que cubren este día')
                        ->visible(fn (Get $get) => $get('justify_mode') === 'existing' && (bool) $get('has_approved_leaves')),

                    Textarea::make('review_notes')
                        ->label('Notas adicionales')
                        ->placeholder('Observaciones opcionales...')
                        ->rows(2)
                        ->visible(fn (Get $get) => $get('justify_mode') === 'existing' && (bool) $get('has_approved_leaves')),

                    // ── Modo: permiso rápido ─────────────────────────────────

                    Select::make('quick_leave_type')
                        ->label('Tipo de permiso')
                        ->options(EmployeeLeave::getTypeOptions())
                        ->native(false)
                        ->required(fn (Get $get) => $get('justify_mode') === 'quick')
                        ->visible(fn (Get $get) => $get('justify_mode') === 'quick'),

                    DatePicker::make('quick_leave_end_date')
                        ->label('Fecha de fin del permiso')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->closeOnDateSelection()
                        ->minDate(fn () => $this->record->attendanceDay->date)
                        ->helperText(fn () => 'Inicio: '.$this->record->attendanceDay->date->format('d/m/Y').'. Ajustá si el permiso cubre más de un día.')
                        ->required(fn (Get $get) => $get('justify_mode') === 'quick')
                        ->visible(fn (Get $get) => $get('justify_mode') === 'quick'),

                    Textarea::make('quick_leave_reason')
                        ->label('Motivo del permiso')
                        ->placeholder('Descripción opcional...')
                        ->rows(2)
                        ->visible(fn (Get $get) => $get('justify_mode') === 'quick'),
                ])
                ->action(function (array $data, Action $action) {
                    if ($data['justify_mode'] === 'quick') {
                        $leave = EmployeeLeave::create([
                            'employee_id' => $this->record->employee_id,
                            'type' => $data['quick_leave_type'],
                            'start_date' => $this->record->attendanceDay->date,
                            'end_date' => $data['quick_leave_end_date'] ?? $this->record->attendanceDay->date,
                            'reason' => $data['quick_leave_reason'] ?? null,
                            'status' => 'pending',
                        ]);

                        $result = $leave->approve(Auth::id());
                        $count = $result['justified_count'];
                        $dates = $result['justified_dates'] ?? [];
                        $typeLabel = EmployeeLeave::getTypeOptions()[$data['quick_leave_type']] ?? $data['quick_leave_type'];
                        $prefix = "Se creó y aprobó el permiso «{$typeLabel}». ";

                        if ($count <= 1) {
                            $body = $prefix.'La ausencia fue justificada.';
                        } elseif ($count <= 5) {
                            $dateList = collect($dates)->sortBy(fn ($d) => $d->timestamp)->map(fn ($d) => $d->translatedFormat('d/m'))->join(', ');
                            $body = $prefix."Se justificaron {$count} ausencias: {$dateList}.";
                        } else {
                            $body = $prefix."Se justificaron {$count} ausencias del período automáticamente.";
                        }

                        Notification::make()->success()->title('Ausencia justificada')->body($body)->send();
                        $this->record->refresh();

                        return;
                    }

                    if (empty($data['employee_leave_id'])) {
                        Notification::make()
                            ->warning()
                            ->title('Sin permiso seleccionado')
                            ->body('No hay permisos aprobados para vincular. Seleccioná "Crear permiso nuevo ahora".')
                            ->send();
                        $action->halt();

                        return;
                    }

                    $result = $this->record->justify(
                        Auth::id(),
                        $data['review_notes'] ?? null,
                        (int) $data['employee_leave_id']
                    );

                    Notification::make()->success()->title('Ausencia justificada')->body($result['message'])->send();
                    $this->record->refresh();
                }),

            Action::make('mark_unjustified')
                ->label('Marcar Injustificada')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->tooltip('Marcar como injustificada y generar deducción salarial')
                ->visible(fn () => ! $this->record->isUnjustified())
                ->modalHeading(fn () => $this->record->isJustified()
                    ? 'Cambiar a Injustificada'
                    : 'Marcar como Injustificada')
                ->modalSubmitActionLabel('Sí, marcar injustificada')
                ->mountUsing(function (Form $form) {
                    $form->fill([
                        'deduction_preview' => $this->record->employee->getAbsenceDeductionAmount(),
                        'has_deduction' => $this->record->hasDeduction(),
                    ]);
                })
                ->form([
                    Hidden::make('deduction_preview'),
                    Hidden::make('has_deduction'),

                    Placeholder::make('deduction_amount_info')
                        ->label('Deducción a generar')
                        ->content(fn (Get $get): string => $get('has_deduction')
                            ? 'Ya existe una deducción para esta ausencia — no se creará una nueva.'
                            : 'Gs. '.number_format((float) $get('deduction_preview'), 0, ',', '.')),

                    Textarea::make('review_notes')
                        ->label('Notas de revisión')
                        ->placeholder('Motivo por el cual se marca como injustificada...')
                        ->rows(3)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $result = $this->record->markAsUnjustified(Auth::id(), $data['review_notes']);

                    Notification::make()
                        ->success()
                        ->title('Ausencia Marcada como Injustificada')
                        ->body($result['message'])
                        ->send();

                    $this->record->refresh();
                }),

            EditAction::make()
                ->label('Editar')
                ->icon('heroicon-o-pencil-square')
                ->color('primary')
                ->tooltip('Editar datos de la ausencia'),

            DeleteAction::make()
                ->successRedirectUrl($this->getResource()::getUrl('index')),
        ];
    }
}
