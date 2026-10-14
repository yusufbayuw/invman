<x-filament-widgets::widget>
    <x-filament::section>
        <div
            x-data="{
                isMobile: window.innerWidth <= 640,
                checkSize() {
                    const mobile = window.innerWidth <= 640;
                    if (mobile !== this.isMobile) {
                        this.isMobile = mobile;
                        $wire.set('isMobile', mobile);
                    }
                }
            }"
            x-init="$wire.set('isMobile', isMobile)"
            x-on:resize.window.debounce.250ms="checkSize()"
            class="space-y-6"
        >
            @if (count($pinnedMenus))
                <div class="space-y-3">
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Menu Favorit</h3>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                        @foreach ($pinnedMenus as $item)
                            <div wire:key="pinned-menu-{{ md5($item['url']) }}" class="relative min-w-0">
                                <a
                                    href="{{ $item['url'] }}"
                                    class="flex min-h-[104px] flex-col items-center justify-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-4 py-4 pr-9 text-gray-950 transition-colors hover:border-primary-300 hover:bg-primary-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white dark:hover:bg-primary-500/10"
                                >
                                    <x-filament::icon :icon="$item['icon'] ?? 'heroicon-o-cube'" class="h-6 w-6 shrink-0 text-primary-600 dark:text-primary-400" />
                                    <span class="break-words text-center text-sm font-medium leading-5">{{ $item['label'] }}</span>
                                </a>
                                <button
                                    type="button"
                                    x-on:click="$wire.togglePin(@js($item['label']), @js($item['url']))"
                                    class="absolute right-2 top-2 inline-flex h-8 w-8 items-center justify-center rounded-lg text-primary-600 transition-colors hover:bg-primary-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:text-primary-400 dark:hover:bg-primary-500/20"
                                    title="Hapus dari favorit"
                                    aria-label="Hapus {{ $item['label'] }} dari favorit"
                                >
                                    <x-filament::icon icon="heroicon-m-x-mark" class="h-4 w-4" />
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="space-y-3">
                <label for="dashboard-menu-search" class="text-sm font-semibold text-gray-950 dark:text-white">Cari menu</label>
                <input
                    id="dashboard-menu-search"
                    type="search"
                    wire:model.live.debounce.300ms="searchTerm"
                    class="w-full rounded-lg border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-950 shadow-sm placeholder:text-gray-400 focus:border-primary-500 focus:ring-primary-500 dark:border-white/20 dark:bg-gray-900 dark:text-white"
                    placeholder="Cari menu aplikasi..."
                    autocomplete="off"
                />
                <div
                    x-data="{ startX: 0 }"
                    x-on:touchstart.passive="startX = $event.touches[0].clientX"
                    x-on:touchend.passive="
                        const diff = startX - $event.changedTouches[0].clientX;
                        if (diff > 70) { $wire.nextPage() }
                        if (diff < -70) { $wire.previousPage() }
                    "
                    class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4"
                >
                    @forelse ($this->getPaginatedItems() as $item)
                        <div wire:key="dashboard-menu-{{ md5($item['url']) }}" class="relative min-w-0">
                            <a
                                href="{{ $item['url'] }}"
                                class="flex min-h-[112px] flex-col items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-4 pr-9 text-gray-950 transition-colors hover:border-primary-300 hover:bg-primary-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-white/10 dark:bg-gray-900 dark:text-white dark:hover:bg-primary-500/10"
                            >
                                <x-filament::icon :icon="$item['icon']" class="h-6 w-6 shrink-0 text-primary-600 dark:text-primary-400" />
                                <span class="break-words text-center text-sm font-medium leading-5">{{ $item['label'] }}</span>
                            </a>
                            <button
                                type="button"
                                x-on:click.prevent="$wire.togglePin(@js($item['label']), @js($item['url']), @js($item['icon']))"
                                class="absolute right-2 top-2 inline-flex h-8 w-8 items-center justify-center rounded-lg text-primary-600 transition-colors hover:bg-primary-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:text-primary-400 dark:hover:bg-primary-500/20"
                                title="{{ collect($pinnedMenus)->pluck('url')->contains($item['url']) ? 'Hapus dari favorit' : 'Tambahkan ke favorit' }}"
                                aria-label="{{ collect($pinnedMenus)->pluck('url')->contains($item['url']) ? 'Hapus' : 'Tambahkan' }} {{ $item['label'] }} {{ collect($pinnedMenus)->pluck('url')->contains($item['url']) ? 'dari' : 'ke' }} favorit"
                                aria-pressed="{{ collect($pinnedMenus)->pluck('url')->contains($item['url']) ? 'true' : 'false' }}"
                            >
                                <span aria-hidden="true" class="text-xl leading-none">{{ collect($pinnedMenus)->pluck('url')->contains($item['url']) ? '★' : '☆' }}</span>
                            </button>
                        </div>
                    @empty
                        <p class="col-span-full rounded-xl border border-dashed border-gray-200 p-6 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                            Tidak ada menu yang cocok dengan pencarian.
                        </p>
                    @endforelse
                </div>
            </div>

            @if ($this->getTotalPages() > 1)
                <nav class="flex flex-wrap items-center justify-center gap-3 border-t border-gray-200 pt-4 dark:border-white/10" aria-label="Navigasi halaman menu">
                    <x-filament::button size="sm" color="gray" wire:click="previousPage" :disabled="$this->currentPage <= 1">
                        Sebelumnya
                    </x-filament::button>
                    <span class="text-xs font-medium tabular-nums text-gray-600 dark:text-gray-300">
                        Halaman {{ $this->currentPage }} dari {{ $this->getTotalPages() }}
                    </span>
                    <x-filament::button size="sm" color="gray" wire:click="nextPage" :disabled="$this->currentPage >= $this->getTotalPages()">
                        Berikutnya
                    </x-filament::button>
                </nav>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
