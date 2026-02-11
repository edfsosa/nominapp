<?php

namespace App\Filament\Resources\HolidayResource\Pages;

use App\Filament\Resources\HolidayResource;
use App\Models\Holiday;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\HtmlString;

class ManageHolidays extends ManageRecords
{
    protected static string $resource = HolidayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import_default')
                ->label('Cargar Feriados Nacionales')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Cargar Feriados Nacionales de Paraguay')
                ->modalDescription(new HtmlString('
                <div>
                    <p class="mb-3">Esto agregará los feriados nacionales de Paraguay para el año <strong>' . now()->year . '</strong>.</p>
                    <p class="mb-2 font-semibold">Feriados a importar:</p>
                    <ul class="list-disc list-inside space-y-1 text-sm">
                        <li>Año Nuevo (1 de enero)</li>
                        <li>Día de los Héroes (1 de marzo)</li>
                        <li>Día del Trabajador (1 de mayo)</li>
                        <li>Día de la Independencia Nacional (15 de mayo)</li>
                        <li>Día de la Paz del Chaco (12 de junio)</li>
                        <li>Fundación de Asunción (15 de agosto)</li>
                        <li>Día de la Victoria de Boquerón (29 de septiembre)</li>
                        <li>Día de la Virgen de Caacupé (8 de diciembre)</li>
                        <li>Navidad (25 de diciembre)</li>
                    </ul>
                    <p class="mt-3 text-xs text-gray-500">Los feriados ya existentes no se duplicarán.</p>
                </div>
            '))
                ->action(function () {
                    $year = now()->year;
                    $holidays = [
                        ['date' => "$year-01-01", 'name' => 'Año Nuevo'],
                        ['date' => "$year-03-01", 'name' => 'Día de los Héroes'],
                        ['date' => "$year-05-01", 'name' => 'Día del Trabajador'],
                        ['date' => "$year-05-15", 'name' => 'Día de la Independencia Nacional'],
                        ['date' => "$year-06-12", 'name' => 'Paz del Chaco'],
                        ['date' => "$year-08-15", 'name' => 'Fundación de Asunción'],
                        ['date' => "$year-09-29", 'name' => 'Día de la Victoria de Boquerón'],
                        ['date' => "$year-12-08", 'name' => 'Día de la Virgen de Caacupé'],
                        ['date' => "$year-12-25", 'name' => 'Navidad'],
                    ];

                    $imported = 0;
                    foreach ($holidays as $holiday) {
                        if (!Holiday::where('date', $holiday['date'])->exists()) {
                            Holiday::create($holiday);
                            $imported++;
                        }
                    }

                    Notification::make()
                        ->title($imported > 0 ? "Se agregaron $imported feriados" : 'Todos los feriados ya existen')
                        ->success()
                        ->send();
                }),

            Action::make('delete_past')
                ->label('Eliminar Feriados Pasados')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Eliminar Feriados Pasados')
                ->modalDescription('Esto eliminará todos los feriados anteriores al año actual. Esta acción no se puede deshacer.')
                ->action(function () {
                    $deleted = Holiday::where('date', '<', now()->startOfYear())->delete();

                    Notification::make()
                        ->title($deleted > 0 ? "Se eliminaron $deleted feriados" : 'No hay feriados pasados para eliminar')
                        ->success()
                        ->send();
                }),

            Action::make('duplicate_next_year')
                ->label('Copiar al Próximo Año')
                ->icon('heroicon-o-document-duplicate')
                ->requiresConfirmation()
                ->modalHeading('Copiar Feriados al Próximo Año')
                ->modalDescription('Esto copiará todos los feriados del año actual al próximo año. Los feriados que ya existan en el próximo año no se duplicarán.')
                ->action(function () {
                    $currentYear = now()->year;
                    $nextYear = $currentYear + 1;

                    $holidays = Holiday::whereYear('date', $currentYear)->get();
                    $created = 0;

                    foreach ($holidays as $holiday) {
                        $newDate = \Carbon\Carbon::parse($holiday->date)->addYear();
                        if (!Holiday::where('date', $newDate)->exists()) {
                            Holiday::create([
                                'date' => $newDate,
                                'name' => $holiday->name,
                            ]);
                            $created++;
                        }
                    }

                    Notification::make()
                        ->title("Se copiaron $created feriados al año $nextYear")
                        ->success()
                        ->send();
                }),

            CreateAction::make()
                ->label('Nuevo Feriado')
                ->icon('heroicon-o-plus')
                ->successNotificationTitle('Feriado agregado exitosamente'),
        ];
    }
}
