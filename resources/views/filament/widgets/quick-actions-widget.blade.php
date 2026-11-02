<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Perlu Tindakan Anda
        </x-slot>

        <x-slot name="description">
            Peminjaman yang perlu Anda tindak lanjuti.
        </x-slot>

        <div wire:poll.30s wire:loading.class.delay="opacity-70" class="space-y-4 transition-opacity">
            <div class="flex flex-wrap items-center gap-2">
                @if ($overdueCount > 0)
                    <x-filament::badge color="danger" icon="heroicon-m-exclamation-triangle">{{ $overdueCount }} reservasi terlambat</x-filament::badge>
                @endif
                @if ($acceptanceCount > 0)
                    <x-filament::badge color="warning">{{ $acceptanceCount }} reservasi menunggu penerimaan</x-filament::badge>
                @endif
                <x-filament::link :href="\App\Filament\Pages\RingkasanPeminjaman::getUrl()" icon="heroicon-m-arrow-top-right-on-square">
                    Lihat pemantauan aset
                </x-filament::link>
            </div>

            @foreach ($acceptances as $receipt)
                <article wire:key="acceptance-{{ $receipt['key'] }}" class="flex flex-col gap-3 rounded-xl bg-warning-50 p-3 ring-1 ring-warning-600/20 sm:flex-row sm:items-center sm:justify-between dark:bg-warning-400/10">
                    <div class="min-w-0 break-words text-sm">
                        <p class="font-semibold text-gray-950 dark:text-white">{{ $receipt['asset'] }}</p>
                        <p class="text-gray-600 dark:text-gray-400">{{ $receipt['activity'] }} · {{ $receipt['type_label'] }}</p>
                    </div>
                    <x-filament::button tag="a" :href="$receipt['url']" color="warning" size="sm" class="invman-quick-action min-h-11 max-w-full sm:shrink-0">
                        Konfirmasi penerimaan
                    </x-filament::button>
                </article>
            @endforeach

            @if ($acceptances->count() >= 12)
                <p class="text-sm text-gray-500 dark:text-gray-400">Menampilkan hingga 12 penerimaan. Buka pemantauan aset untuk memeriksa seluruh status.</p>
            @endif

            @if ($queue->isEmpty() && $acceptances->isEmpty())
                <div class="flex flex-col items-center justify-center gap-2 px-6 py-10 text-center">
                    <x-filament::icon icon="heroicon-o-check-circle" class="h-10 w-10 text-success-500" />
                    <p class="text-sm font-medium text-gray-950 dark:text-white">Tidak ada tindakan yang menunggu</p>
                    <p class="max-w-lg text-sm text-gray-500 dark:text-gray-400">
                        Status dan jadwal peminjaman tetap tersedia pada pemantauan aset.
                    </p>
                </div>
            @elseif ($queue->isNotEmpty())
                <div class="hidden overflow-x-auto rounded-xl border border-gray-200 xl:block dark:border-white/10">
                    <table class="w-full table-fixed divide-y divide-gray-200 text-start dark:divide-white/5">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-white/5">
                                <th class="px-4 py-3 text-start text-xs font-semibold text-gray-700 dark:text-gray-200">Aset</th>
                                <th class="px-4 py-3 text-start text-xs font-semibold text-gray-700 dark:text-gray-200">Kegiatan</th>
                                <th class="px-4 py-3 text-start text-xs font-semibold text-gray-700 dark:text-gray-200">Jadwal</th>
                                <th class="px-4 py-3 text-start text-xs font-semibold text-gray-700 dark:text-gray-200">Status</th>
                                <th class="w-1/4 px-4 py-3 text-end text-xs font-semibold text-gray-700 dark:text-gray-200">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                            @foreach ($queue as $row)
                                <tr wire:key="quick-action-desktop-{{ $row['key'] }}" class="hover:bg-gray-50 dark:hover:bg-white/5">
                                    <td class="px-4 py-3">
                                        <div class="flex items-start gap-3">
                                            <x-filament::icon :icon="$row['type_icon']" class="mt-0.5 h-5 w-5 shrink-0 text-primary-500" />
                                            <div class="min-w-0 break-words">
                                                <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $row['asset_name'] }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $row['type_label'] }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="break-words text-sm text-gray-950 dark:text-white">{{ $row['activity_name'] }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $row['unit_name'] }} · {{ $row['requester_name'] }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $row['schedule'] }}</td>
                                    <td class="px-4 py-3">
                                        <x-filament::badge :color="$row['status_color']" class="invman-action-status max-w-full">{{ $row['status_label'] }}</x-filament::badge>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap justify-end gap-2">
                                            @foreach ($row['actions'] as $action)
                                                @php
                                                    $presentation = $this->actionPresentation($action);
                                                    $mountAction = 'mountAction('
                                                        .\Illuminate\Support\Js::from($action)
                                                        .', '
                                                        .\Illuminate\Support\Js::from([
                                                            'type' => $row['type'],
                                                            'reservation_id' => $row['reservation_id'],
                                                        ])
                                                        .')';
                                                @endphp
                                                <x-filament::button
                                                    class="invman-quick-action min-h-11 max-w-full"
                                                    size="sm"
                                                    :color="$presentation['color']"
                                                    :icon="$presentation['icon']"
                                                    wire:click="{{ $mountAction }}"
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

                <div class="grid gap-3 md:grid-cols-2 xl:hidden">
                    @foreach ($queue as $row)
                        <article wire:key="quick-action-mobile-{{ $row['key'] }}" class="space-y-3 rounded-xl border border-gray-200 p-4 dark:border-white/10">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="flex min-w-0 items-start gap-3">
                                    <x-filament::icon :icon="$row['type_icon']" class="mt-0.5 h-5 w-5 shrink-0 text-primary-500" />
                                    <div class="min-w-0 break-words">
                                        <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $row['asset_name'] }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $row['type_label'] }} · {{ $row['unit_name'] }}</p>
                                    </div>
                                </div>
                                <x-filament::badge :color="$row['status_color']" class="invman-action-status max-w-full">{{ $row['status_label'] }}</x-filament::badge>
                            </div>
                            <div class="space-y-1 break-words text-sm">
                                <p class="font-medium text-gray-900 dark:text-gray-100">{{ $row['activity_name'] }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $row['schedule'] }}</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                @foreach ($row['actions'] as $action)
                                    @php
                                        $presentation = $this->actionPresentation($action);
                                        $mountAction = 'mountAction('
                                            .\Illuminate\Support\Js::from($action)
                                            .', '
                                            .\Illuminate\Support\Js::from([
                                                'type' => $row['type'],
                                                'reservation_id' => $row['reservation_id'],
                                            ])
                                            .')';
                                    @endphp
                                    <x-filament::button
                                        class="invman-quick-action min-h-11 max-w-full"
                                        size="sm"
                                        :color="$presentation['color']"
                                        :icon="$presentation['icon']"
                                        wire:click="{{ $mountAction }}"
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
                    <div class="flex flex-col gap-3 border-t border-gray-200 pt-4 sm:flex-row sm:items-center sm:justify-between dark:border-white/10">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Halaman {{ $currentQueuePage }} dari {{ $queuePageCount }} · {{ $queueCount }} tindakan
                        </p>
                        <div class="flex flex-wrap items-center gap-2">
                            <x-filament::button size="sm" color="gray" wire:click="previousPage" :disabled="$currentQueuePage <= 1">
                                Sebelumnya
                            </x-filament::button>
                            <x-filament::button size="sm" color="gray" wire:click="nextPage" :disabled="$currentQueuePage >= $queuePageCount">
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
