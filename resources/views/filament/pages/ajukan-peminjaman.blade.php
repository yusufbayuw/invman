<x-filament-panels::page>
    <form wire:submit="submit" wire:poll.15s="refreshAvailability" class="space-y-6">
        {{ $this->form }}

        <div class="flex justify-end">
            <x-filament::button type="submit" size="lg" icon="heroicon-o-paper-airplane">
                Ajukan Peminjaman
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
