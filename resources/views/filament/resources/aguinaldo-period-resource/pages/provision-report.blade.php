<x-filament-panels::page>
    {{-- Encabezado del período --}}
    <div class="mb-4 text-sm text-gray-500 dark:text-gray-400">
        {{ $record->company->name }} &mdash; Año {{ $record->year }}
    </div>

    {{ $this->table }}
</x-filament-panels::page>
