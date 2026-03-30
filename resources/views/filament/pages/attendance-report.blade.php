<x-filament-panels::page>
    <x-filament::tabs>
        <x-filament::tabs.item
            :active="$this->activeTab === 'attendance'"
            icon="heroicon-o-clock"
            wire:click="setTab('attendance')"
        >
            Asistencias
        </x-filament::tabs.item>

        <x-filament::tabs.item
            :active="$this->activeTab === 'absence'"
            icon="heroicon-o-x-circle"
            wire:click="setTab('absence')"
        >
            Ausencias
        </x-filament::tabs.item>
    </x-filament::tabs>

    {{ $this->table }}
</x-filament-panels::page>
