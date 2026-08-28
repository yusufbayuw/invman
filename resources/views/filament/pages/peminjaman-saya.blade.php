<x-filament-panels::page>
    @php($counts = $this->getTypeCounts())

    <x-filament::tabs>
        <x-filament::tabs.item :active="$activeType === 'all'" :badge="$counts['all']" wire:click="setActiveType('all')">
            Semua
        </x-filament::tabs.item>
        <x-filament::tabs.item :active="$activeType === 'item'" :badge="$counts['item']" badge-color="primary" wire:click="setActiveType('item')">
            Barang
        </x-filament::tabs.item>
        <x-filament::tabs.item :active="$activeType === 'room'" :badge="$counts['room']" badge-color="warning" wire:click="setActiveType('room')">
            Tempat / Ruangan
        </x-filament::tabs.item>
        <x-filament::tabs.item :active="$activeType === 'vehicle'" :badge="$counts['vehicle']" badge-color="info" wire:click="setActiveType('vehicle')">
            Kendaraan
        </x-filament::tabs.item>
    </x-filament::tabs>

    {{ $this->table }}
</x-filament-panels::page>
