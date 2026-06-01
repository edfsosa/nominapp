<x-filament-panels::page>
    <x-filament::tabs>
        <x-filament::tabs.item
            :active="$this->activeTab === 'vencer'"
            icon="heroicon-o-calendar-days"
            wire:click="$set('activeTab', 'vencer')"
        >
            Por Vencer
        </x-filament::tabs.item>

        <x-filament::tabs.item
            :active="$this->activeTab === 'prueba'"
            icon="heroicon-o-clock"
            wire:click="$set('activeTab', 'prueba')"
        >
            Período de Prueba
        </x-filament::tabs.item>

        <x-filament::tabs.item
            :active="$this->activeTab === 'sin_contrato'"
            icon="heroicon-o-user-minus"
            wire:click="$set('activeTab', 'sin_contrato')"
        >
            Sin Contrato
        </x-filament::tabs.item>

        <x-filament::tabs.item
            :active="$this->activeTab === 'antiguedad'"
            icon="heroicon-o-trophy"
            wire:click="$set('activeTab', 'antiguedad')"
        >
            Por Antigüedad
        </x-filament::tabs.item>

        <x-filament::tabs.item
            :active="$this->activeTab === 'suspendidos'"
            icon="heroicon-o-pause-circle"
            wire:click="$set('activeTab', 'suspendidos')"
        >
            Suspendidos
        </x-filament::tabs.item>

        <x-filament::tabs.item
            :active="$this->activeTab === 'activos'"
            icon="heroicon-o-check-circle"
            wire:click="$set('activeTab', 'activos')"
        >
            Todos Activos
        </x-filament::tabs.item>

        <x-filament::tabs.item
            :active="$this->activeTab === 'rescindidos'"
            icon="heroicon-o-x-circle"
            wire:click="$set('activeTab', 'rescindidos')"
        >
            Rescindidos
        </x-filament::tabs.item>
    </x-filament::tabs>

    {{ $this->table }}
</x-filament-panels::page>
