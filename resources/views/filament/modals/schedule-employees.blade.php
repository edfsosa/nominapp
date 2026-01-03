<div x-data="{
    removedEmployees: [],
    employeeCount: {{ $employees->count() }},
    scheduleId: {{ $schedule->id }},
    removeEmployee(employeeId, employeeName) {
        if (!confirm(`¿Está seguro de que desea remover a ${employeeName} de este horario?`)) {
            return;
        }
        fetch(`/admin/schedules/${this.scheduleId}/remove-employee/${employeeId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=\'csrf-token\']').getAttribute('content'),
                'Accept': 'application/json',
            },
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.removedEmployees.push(employeeId);
                this.employeeCount--;
                new FilamentNotification()
                    .title('Empleado removido')
                    .success()
                    .body(data.message)
                    .send();
            } else {
                new FilamentNotification()
                    .title('Error')
                    .danger()
                    .body(data.message || 'No se pudo remover el empleado del horario.')
                    .send();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            new FilamentNotification()
                .title('Error')
                .danger()
                .body('Ocurrió un error al remover el empleado del horario.')
                .send();
        });
    }
}">
    @if ($employees->isEmpty())
        <div class="flex flex-col items-center justify-center py-12 text-center">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                No hay empleados asignados
            </h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Este horario aún no tiene empleados asignados.
            </p>
        </div>
    @else
        <div class="space-y-2">
            <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                Total de empleados: <span class="font-semibold" x-text="employeeCount">{{ $employees->count() }}</span>
            </div>

            <div class="space-y-2">
                @foreach ($employees as $employee)
                    <div
                        class="flex items-center justify-between p-4 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition"
                        x-show="!removedEmployees.includes({{ $employee->id }})"
                        x-transition:leave="transition ease-in duration-200"
                        x-transition:leave-start="opacity-100 scale-100"
                        x-transition:leave-end="opacity-0 scale-95"
                        style="display: block;">
                        <div class="flex items-center gap-4">
                            @if ($employee->photo)
                                <img src="{{ asset('storage/' . $employee->photo) }}" alt="{{ $employee->full_name }}"
                                    class="h-10 w-10 rounded-full object-cover" />
                            @else
                                <div
                                    class="h-10 w-10 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center">
                                    <span class="text-sm font-medium text-primary-700 dark:text-primary-300">
                                        {{ substr($employee->first_name, 0, 1) }}{{ substr($employee->last_name, 0, 1) }}
                                    </span>
                                </div>
                            @endif

                            <div>
                                <div class="font-medium text-gray-900 dark:text-gray-100">
                                    {{ $employee->full_name }}
                                </div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    CI: {{ $employee->ci }}
                                    @if ($employee->position)
                                        <span class="mx-1">•</span>
                                        {{ $employee->position->name }}
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            @if ($employee->branch)
                                <span
                                    class="inline-flex items-center rounded-md bg-blue-50 dark:bg-blue-900/30 px-2 py-1 text-xs font-medium text-blue-700 dark:text-blue-300 ring-1 ring-inset ring-blue-700/10 dark:ring-blue-300/20">
                                    {{ $employee->branch->name }}
                                </span>
                            @endif

                            <span
                                class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset
                                @if ($employee->status === 'active') text-green-700 dark:text-green-300 ring-green-700/10 dark:ring-green-300/20
                                @elseif($employee->status === 'inactive')
                                @else
                                    bg-yellow-50 dark:bg-yellow-900/30 @endif
                            ">
                                @if ($employee->status === 'active')
                                    Activo
                                @elseif($employee->status === 'inactive')
                                    Inactivo
                                @else
                                    Suspendido
                                @endif
                            </span>

                            <button
                                @click="removeEmployee({{ $employee->id }}, '{{ $employee->full_name }}')"
                                type="button"
                                class="inline-flex items-center justify-center rounded-md p-1.5 text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20 transition-colors"
                                title="Remover horario">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
