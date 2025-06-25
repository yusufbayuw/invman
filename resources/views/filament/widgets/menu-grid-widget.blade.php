<x-filament-widgets::widget>
    <x-filament::section>
        <div x-data="{
            isMobile: window.innerWidth <= 640,
            checkSize() {
                this.isMobile = window.innerWidth <= 640;
                $wire.set('isMobile', this.isMobile);
            }
        }" x-init="checkSize();
        window.addEventListener('resize', () => checkSize())">

        </div>

        @if (count($pinnedMenus))
            <div class="mb-4">
                <h3 class="font-bold text-sm mb-2">Menu Favorit</h3>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    @foreach ($pinnedMenus as $item)
                    <div class="relative">
                        <a href="{{ $item['url'] }}"
                            class="flex flex-col items-center p-3 bg-gray-50 rounded-lg relative">
                            <x-dynamic-component :component="$item['icon'] ?? 'heroicon-o-cube'" class="w-6 h-6 text-primary-600 mb-1" />
                            <span class="text-sm text-center">{{ $item['label'] }}</span>
                        </a>
                        <button wire:click="togglePin('{{ $item['label'] }}', '{{ $item['url'] }}')"
                                class="absolute px-3 top-1 right-1 text-lg text-primary-600 z-10" title="Unpin menu">×</button>
                    </div>
                    @endforeach
                </div>
            </div>
        @endif


        <!-- Search -->
        <div class="mb-4">
            <input type="text" wire:model.live="searchTerm" class="w-full px-4 py-2 border rounded-md"
                placeholder="Cari menu..." />
        </div>

        <!-- Swipe-able Grid -->
        <div class="mb-4" x-data="{ startX: 0, endX: 0 }" x-on:touchstart="startX = $event.touches[0].clientX"
            x-on:touchend="
                endX = $event.changedTouches[0].clientX;
                if (startX - endX > 50) { $wire.nextPage() }
                if (endX - startX > 50) { $wire.previousPage() }
            ">
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 transition">
                @foreach ($this->getPaginatedItems() as $item)
                    <div class="relative">
                        {{-- Bintang di pojok kanan atas --}}
                        <button
                            wire:click.prevent="togglePin('{{ $item['label'] }}', '{{ $item['url'] }}', '{{ $item['icon'] }}')"
                            class="absolute px-3 top-1 right-1 z-10 text-lg text-primary-600 hover:text-primary-500"
                            title="Pin menu">
                            @if (collect($pinnedMenus)->pluck('url')->contains($item['url']))
                                ★
                            @else
                                ☆
                            @endif
                        </button>

                        {{-- Card content --}}
                        <a href="{{ $item['url'] }}"
                            class="flex flex-col items-center p-4 bg-gray-100 rounded-lg hover:bg-primary-100 transition">
                            <x-dynamic-component :component="$item['icon']" class="w-6 h-6 text-primary-500 mb-2" />
                            <span class="text-sm font-medium text-center">{{ $item['label'] }}</span>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Pagination Buttons -->
        <div class="mt-4 flex justify-center items-center gap-2">
            <x-filament::button wire:click="previousPage" :disabled="$this->currentPage === 1">
                ‹
            </x-filament::button>

            <span class="text-sm">Halaman {{ $this->currentPage }} / {{ $this->getTotalPages() }}</span>

            <x-filament::button wire:click="nextPage" :disabled="$this->currentPage === $this->getTotalPages()">
                ›
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
