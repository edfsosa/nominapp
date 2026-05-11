<x-filament-panels::page>
    <x-filament::tabs>
        <x-filament::tabs.item
            :active="$this->activeTab === 'contratos'"
            icon="heroicon-o-calendar-days"
            wire:click="$set('activeTab', 'contratos')"
        >
            Contratos por Vencer
        </x-filament::tabs.item>

        <x-filament::tabs.item
            :active="$this->activeTab === 'prueba'"
            icon="heroicon-o-clock"
            wire:click="$set('activeTab', 'prueba')"
        >
            Períodos de Prueba
        </x-filament::tabs.item>
    </x-filament::tabs>

    {{ $this->table }}
</x-filament-panels::page>
