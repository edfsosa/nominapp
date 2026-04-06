<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Siembra los turnos de trabajo (ShiftTemplates) para la empresa demo.
 *
 * Se crean 6 turnos que cubren distintos escenarios:
 *   - Ciclo 3 turnos clásico (Mañana / Tarde / Noche)
 *   - Franco (día libre dentro del ciclo)
 *   - Turno 12h (industria o sanatorio)
 *   - Medio día (para horarios mixtos)
 */
class ShiftTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $companyId = DB::table('companies')->value('id');
        $now       = now();

        $templates = [
            [
                'name'          => 'Turno Mañana',
                'color'         => '#3B82F6',   // azul
                'shift_type'    => 'diurno',
                'is_day_off'    => false,
                'start_time'    => '06:00:00',
                'end_time'      => '14:00:00',
                'break_minutes' => 30,
                'notes'         => null,
            ],
            [
                'name'          => 'Turno Tarde',
                'color'         => '#F59E0B',   // ámbar
                'shift_type'    => 'diurno',
                'is_day_off'    => false,
                'start_time'    => '14:00:00',
                'end_time'      => '22:00:00',
                'break_minutes' => 30,
                'notes'         => null,
            ],
            [
                'name'          => 'Turno Noche',
                'color'         => '#8B5CF6',   // violeta
                'shift_type'    => 'nocturno',
                'is_day_off'    => false,
                'start_time'    => '22:00:00',
                'end_time'      => '06:00:00',  // cruza medianoche
                'break_minutes' => 30,
                'notes'         => 'Cruza medianoche — jornada nocturna Art. 199 CLT',
            ],
            [
                'name'          => 'Franco',
                'color'         => '#6B7280',   // gris
                'shift_type'    => 'diurno',
                'is_day_off'    => true,
                'start_time'    => null,
                'end_time'      => null,
                'break_minutes' => 0,
                'notes'         => 'Descanso semanal remunerado — Art. 206 CLT',
            ],
            [
                'name'          => 'Turno 12h Día',
                'color'         => '#EF4444',   // rojo
                'shift_type'    => 'diurno',
                'is_day_off'    => false,
                'start_time'    => '06:00:00',
                'end_time'      => '18:00:00',
                'break_minutes' => 60,
                'notes'         => 'Jornada extendida — industria 24/7',
            ],
            [
                'name'          => 'Turno 12h Noche',
                'color'         => '#1D4ED8',   // azul oscuro
                'shift_type'    => 'nocturno',
                'is_day_off'    => false,
                'start_time'    => '18:00:00',
                'end_time'      => '06:00:00',  // cruza medianoche
                'break_minutes' => 60,
                'notes'         => 'Jornada extendida nocturna — industria 24/7',
            ],
        ];

        DB::table('shift_templates')->insert(
            collect($templates)->map(fn($t) => array_merge($t, [
                'company_id' => $companyId,
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]))->toArray()
        );

        $this->command->info('Turnos sembrados: ' . count($templates));
    }
}
