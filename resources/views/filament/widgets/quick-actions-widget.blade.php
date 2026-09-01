<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Tindakan Cepat
        </x-slot>

        <x-slot name="description">
            Reservasi yang saat ini memerlukan tindakan Anda sesuai peran dan jenis pengelolaan.
        </x-slot>

        <div wire:poll.30s wire:loading.class.delay="opacity-70" class="transition-opacity">
            @if ($queue->isEmpty())
                <div class="flex flex-col items-center justify-center gap-2 px-6 py-10 text-center">
                    <x-filament::icon icon="heroicon-o-check-circle" class="h-10 w-10 text-success-500" />
                    <p class="text-sm font-medium text-gray-950 dark:text-white">Tidak ada tindakan yang menunggu</p>
                    <p class="max-w-lg text-sm text-gray-500 dark:text-gray-400">
                        Semua reservasi dalam kewenangan Anda sudah diproses.
                    </p>
                </div>
            @else
                <div class="hidden overflow-x-auto md:block">
                    <table class="w-full table-auto divide-y divide-gray-200 text-start dark:divide-white/5">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-white/5">
                                <th class="px-4 py-3 text-start text-sm font-semibold text-gray-950 dark:text-white">Aset</th>
                                <th class="px-4 py-3 text-start text-sm font-semibold text-gray-950 dark:text-white">Kegiatan</th>
                                <th class="px-4 py-3 text-start text-sm font-semibold text-gray-950 dark:text-white">Jadwal</th>
                                <th class="px-4 py-3 text-start text-sm font-semibold text-gray-950 dark:text-white">Status</th>
                                <th class="px-4 py-3 text-end text-sm font-semibold text-gray-950 dark:text-white">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 whitespace-nowrap dark:divide-white/5">
                            @foreach ($queue as $row)
                                <tr wire:key="quick-action-desktop-{{ $row['key'] }}" class="hover:bg-gray-50 dark:hover:bg-white/5">
                                    <td class="px-4 py-3">
                                        <div class="flex items-start gap-3">
                                            <x-filament::icon :icon="$row['type_icon']" class="mt-0.5 h-5 w-5 text-primary-500" />
                                            <div>
                                                <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $row['asset_name'] }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $row['type_label'] }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="max-w-xs truncate text-sm text-gray-950 dark:text-white">{{ $row['activity_name'] }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $row['unit_name'] }} · {{ $row['requester_name'] }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $row['schedule'] }}</td>
                                    <td class="px-4 py-3">
                                        <x-filament::badge :color="$row['status_color']">{{ $row['status_label'] }}</x-filament::badge>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-2">
                                            @foreach ($row['actions'] as $action)
                                                @php($presentation = $this->actionPresentation($action))
                                                <x-filament::button
                                                    size="xs"
                                                    :color="$presentation['color']"
                                                    :icon="$presentation['icon']"
                                                    wire:click="mountAction('{{ $action }}', @js(['type' => $row['type'], 'reservation_id' => $row['reservation_id']]))"
                                                    wire:loading.attr="disabled"
                                                >
                                                    {{ $presentation['label'] }}
                                                </x-filament::button>
                                            @endforeach
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="divide-y divide-gray-200 md:hidden dark:divide-white/5">
                    @foreach ($queue as $row)
                        <article wire:key="quick-action-mobile-{{ $row['key'] }}" class="space-y-3 py-4 first:pt-0 last:pb-0">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-start gap-3">
                                    <x-filament::icon :icon="$row['type_icon']" class="mt-0.5 h-5 w-5 text-primary-500" />
                                    <div>
                                        <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $row['asset_name'] }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $row['type_label'] }} · {{ $row['unit_name'] }}</p>
                                    </div>
                                </div>
                                <x-filament::badge :color="$row['status_color']">{{ $row['status_label'] }}</x-filament::badge>
                            </div>
                            <div class="space-y-1 text-sm">
                                <p class="font-medium text-gray-900 dark:text-gray-100">{{ $row['activity_name'] }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $row['schedule'] }}</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($row['actions'] as $action)
                                    @php($presentation = $this->actionPresentation($action))
                                    <x-filament::button
                                        size="xs"
                                        :color="$presentation['color']"
                                        :icon="$presentation['icon']"
                                        wire:click="mountAction('{{ $action }}', @js(['type' => $row['type'], 'reservation_id' => $row['reservation_id']]))"
                                        wire:loading.attr="disabled"
                                    >
                                        {{ $presentation['label'] }}
                                    </x-filament::button>
                                @endforeach
                            </div>
                        </article>
                    @endforeach
                </div>

                @if ($queuePageCount > 1)
                    <div class="mt-4 flex items-center justify-between border-t border-gray-200 pt-4 dark:border-white/10">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Halaman {{ $currentQueuePage }} dari {{ $queuePageCount }} · {{ $queueCount }} tindakan
                        </p>
                        <div class="flex gap-2">
                            <x-filament::button size="xs" color="gray" wire:click="previousPage" :disabled="$currentQueuePage <= 1">
                                Sebelumnya
                            </x-filament::button>
                            <x-filament::button size="xs" color="gray" wire:click="nextPage" :disabled="$currentQueuePage >= $queuePageCount">
                                Berikutnya
                            </x-filament::button>
                        </div>
                    </div>
                @endif
            @endif
        </div>

        <x-filament-actions::modals />
    </x-filament::section>
</x-filament-widgets::widget>
