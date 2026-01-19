<div class="space-y-4">
    @if ($installments->isEmpty())
        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
            <p>No hay cuotas vencidas</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Empleado</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">CI</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Tipo</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Cuota</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-600 dark:text-gray-300">Monto</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Vencimiento</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Atraso</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($installments as $installment)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
                                {{ $installment->loan->employee->full_name }}
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                {{ $installment->loan->employee->ci }}
                            </td>
                            <td class="px-4 py-3">
                                <span @class([
                                    'inline-flex items-center px-2 py-1 text-xs font-medium rounded-full',
                                    'bg-primary-100 text-primary-700 dark:bg-primary-500/20 dark:text-primary-400' =>
                                        $installment->loan->type === 'loan',
                                    'bg-warning-100 text-warning-700 dark:bg-warning-500/20 dark:text-warning-400' =>
                                        $installment->loan->type === 'advance',
                                ])>
                                    {{ $installment->loan->type === 'loan' ? 'Prestamo' : 'Adelanto' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                {{ $installment->installment_number }}/{{ $installment->loan->installments_count }}
                            </td>
                            <td class="px-4 py-3 text-right font-medium text-gray-900 dark:text-gray-100">
                                {{ number_format($installment->amount, 0, ',', '.') }} Gs.
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                {{ $installment->due_date->format('d/m/Y') }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-danger-100 text-danger-700 dark:bg-danger-500/20 dark:text-danger-400">
                                    {{ $installment->due_date->diffForHumans() }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <td colspan="4" class="px-4 py-3 text-right font-medium text-gray-600 dark:text-gray-300">
                            Total ({{ $installments->count() }} cuotas):
                        </td>
                        <td class="px-4 py-3 text-right font-bold text-gray-900 dark:text-gray-100">
                            {{ number_format($installments->sum('amount'), 0, ',', '.') }} Gs.
                        </td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
