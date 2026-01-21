<div class="space-y-4">
    @if ($balances->isEmpty())
        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
            <p>No hay balances generados para el año {{ $year }}</p>
            <p class="text-sm mt-2">Usa el botón "Generar Balances" para crear los balances de los empleados activos.</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Empleado</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">CI</th>
                        <th class="px-4 py-3 text-center font-medium text-gray-600 dark:text-gray-300">Antigüedad</th>
                        <th class="px-4 py-3 text-center font-medium text-gray-600 dark:text-gray-300">Derecho</th>
                        <th class="px-4 py-3 text-center font-medium text-gray-600 dark:text-gray-300">Usados</th>
                        <th class="px-4 py-3 text-center font-medium text-gray-600 dark:text-gray-300">Pendientes</th>
                        <th class="px-4 py-3 text-center font-medium text-gray-600 dark:text-gray-300">Disponibles</th>
                        <th class="px-4 py-3 text-center font-medium text-gray-600 dark:text-gray-300">Progreso</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($balances as $balance)
                        @php
                            $progressPercent = $balance->entitled_days > 0
                                ? round((($balance->used_days + $balance->pending_days) / $balance->entitled_days) * 100)
                                : 0;
                            $progressColor = $progressPercent >= 100 ? 'bg-danger-500' : ($progressPercent >= 75 ? 'bg-warning-500' : 'bg-success-500');
                        @endphp
                        <tr>
                            <td class="px-4 py-3 text-gray-900 dark:text-gray-100 font-medium">
                                {{ $balance->employee->full_name ?? 'N/A' }}
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                {{ $balance->employee->ci ?? 'N/A' }}
                            </td>
                            <td class="px-4 py-3 text-center text-gray-600 dark:text-gray-400">
                                {{ $balance->years_of_service }} {{ $balance->years_of_service === 1 ? 'año' : 'años' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-primary-100 text-primary-700 dark:bg-primary-500/20 dark:text-primary-400">
                                    {{ $balance->entitled_days }} días
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if ($balance->used_days > 0)
                                    <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-success-100 text-success-700 dark:bg-success-500/20 dark:text-success-400">
                                        {{ $balance->used_days }}
                                    </span>
                                @else
                                    <span class="text-gray-400">0</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if ($balance->pending_days > 0)
                                    <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-warning-100 text-warning-700 dark:bg-warning-500/20 dark:text-warning-400">
                                        {{ $balance->pending_days }}
                                    </span>
                                @else
                                    <span class="text-gray-400">0</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span @class([
                                    'inline-flex items-center px-2 py-1 text-xs font-medium rounded-full',
                                    'bg-success-100 text-success-700 dark:bg-success-500/20 dark:text-success-400' => $balance->available_days > 0,
                                    'bg-danger-100 text-danger-700 dark:bg-danger-500/20 dark:text-danger-400' => $balance->available_days <= 0,
                                ])>
                                    {{ $balance->available_days }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
                                        <div class="{{ $progressColor }} h-full transition-all duration-300" style="width: {{ min($progressPercent, 100) }}%"></div>
                                    </div>
                                    <span class="text-xs text-gray-500 dark:text-gray-400 w-10 text-right">{{ $progressPercent }}%</span>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <td colspan="3" class="px-4 py-3 text-right font-medium text-gray-600 dark:text-gray-300">
                            Totales ({{ $balances->count() }} empleados):
                        </td>
                        <td class="px-4 py-3 text-center font-bold text-gray-900 dark:text-gray-100">
                            {{ $balances->sum('entitled_days') }}
                        </td>
                        <td class="px-4 py-3 text-center font-bold text-success-600 dark:text-success-400">
                            {{ $balances->sum('used_days') }}
                        </td>
                        <td class="px-4 py-3 text-center font-bold text-warning-600 dark:text-warning-400">
                            {{ $balances->sum('pending_days') }}
                        </td>
                        <td class="px-4 py-3 text-center font-bold text-primary-600 dark:text-primary-400">
                            {{ $balances->sum('available_days') }}
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="mt-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
            <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">Leyenda - Días según antigüedad (Ley Paraguaya)</h4>
            <div class="grid grid-cols-3 gap-4 text-sm text-gray-600 dark:text-gray-400">
                <div>
                    <span class="font-medium">Hasta 5 años:</span> 12 días hábiles
                </div>
                <div>
                    <span class="font-medium">5 a 10 años:</span> 18 días hábiles
                </div>
                <div>
                    <span class="font-medium">Más de 10 años:</span> 30 días hábiles
                </div>
            </div>
        </div>
    @endif
</div>
