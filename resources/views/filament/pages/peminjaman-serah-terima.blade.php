<x-filament-panels::page>
    <div class="mx-auto grid w-full max-w-5xl gap-5 lg:grid-cols-5">
        <div class="space-y-5 lg:col-span-3">
            <x-filament::section>
                <x-slot name="heading">Detail serah-terima</x-slot>
                <div class="space-y-3 text-sm text-gray-700 dark:text-gray-200">
                    <p class="text-lg font-semibold text-gray-950 dark:text-white">{{ $assetName }}</p>
                    <div class="flex flex-wrap items-center gap-2">
                        <x-filament::badge>{{ $statusLabel }}</x-filament::badge>
                        <span>{{ $record->activity?->name ?? '-' }}</span>
                    </div>
                    <dl class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <div><dt class="text-gray-500">Pemohon</dt><dd class="font-medium">{{ $record->activity?->user?->name ?? '-' }}</dd></div>
                        <div><dt class="text-gray-500">Unit</dt><dd class="font-medium">{{ $record->activity?->unit?->name ?? '-' }}</dd></div>
                        <div><dt class="text-gray-500">Mulai</dt><dd class="font-medium">{{ $record->start_time?->format('d M Y H:i') ?? '-' }}</dd></div>
                        <div><dt class="text-gray-500">Selesai</dt><dd class="font-medium">{{ $record->end_time?->format('d M Y H:i') ?? '-' }}</dd></div>
                    </dl>
                    @if($outboundReceipt)
                        <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/5">
                            <p class="font-semibold">Bukti penyerahan awal: {{ $outboundReceipt->receipt_number }}</p>
                            <p class="text-xs text-gray-500">Pengelola: {{ $outboundReceipt->manager_confirmed_at?->format('d M Y H:i') ?? 'Belum konfirmasi' }}</p>
                            <p class="text-xs text-gray-500">Peminjam: {{ $outboundReceipt->borrower_confirmed_at?->format('d M Y H:i') ?? 'Belum konfirmasi' }}</p>
                            @if($outboundReceipt->checkout_odometer !== null)
                                <p class="text-xs text-gray-500">Kilometer awal: {{ number_format($outboundReceipt->checkout_odometer) }} km</p>
                            @endif
                            @if($outboundReceipt->fallback_reason)
                                <p class="text-xs font-medium text-amber-700 dark:text-amber-400">Pengecualian tercatat: {{ $outboundReceipt->fallback_reason }}</p>
                            @endif
                        </div>
                    @endif
                    @if($record->returnReceipt)
                        <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/5">
                            <p class="font-semibold">Bukti pengembalian: {{ $record->returnReceipt->receipt_number }}</p>
                            <p class="text-xs text-gray-500">Pemohon: {{ $record->returnReceipt->borrower_confirmed_at?->format('d M Y H:i') ?? 'Belum konfirmasi' }}</p>
                            <p class="text-xs text-gray-500">Pengelola: {{ $record->returnReceipt->manager_confirmed_at?->format('d M Y H:i') ?? 'Belum konfirmasi' }}</p>
                        </div>
                    @endif
                </div>
            </x-filament::section>

            @if ($canBeginCheckout || $canBeginReturn)
                <form wire:submit="{{ $canBeginCheckout ? 'submitCheckout' : 'submitReturn' }}" class="space-y-4">
                    {{ $this->form }}
                    <div class="flex justify-end">
                        @if($canBeginCheckout)
                            <x-filament::button type="submit" size="lg" icon="heroicon-o-clipboard-document-check" wire:loading.attr="disabled" wire:target="submitCheckout">
                                Catat Kondisi Awal & Serahkan
                            </x-filament::button>
                        @else
                            <x-filament::button type="submit" size="lg" icon="heroicon-o-clipboard-document-check" wire:loading.attr="disabled" wire:target="submitReturn">
                                Catat Pengembalian
                            </x-filament::button>
                        @endif
                    </div>
                </form>
            @else
                <x-filament::section>
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        @if($record->status === 'approved')
                            @if($outboundReceipt && !$outboundReceipt->completed_at)
                                Pengelola sudah mencatat kondisi awal. Peminjam dapat membuka QR dan menekan <strong>Konfirmasi Penerimaan</strong>.
                            @elseif(!$outboundReceipt)
                                Pengelola mencatat kondisi awal sebelum memulai serah-terima. Pengguna tanpa kewenangan tidak dapat menyerahkan aset.
                            @else
                                Penyerahan telah tercatat.
                            @endif
                        @elseif($record->status === 'return_requested')
                            Pengembalian telah diajukan. Pihak kedua yang berwenang dapat menggunakan <strong>Konfirmasi Pengembalian</strong> di bagian atas.
                        @elseif($record->status === 'returned')
                            Pengembalian selesai. Nomor dan histori serah-terima tersimpan.
                        @else
                            Belum ada tindakan serah-terima yang tersedia untuk status ini.
                        @endif
                    </p>
                </x-filament::section>
            @endif
        </div>

        <div class="lg:col-span-2">
            <x-filament::section>
                <x-slot name="heading">QR Reservasi</x-slot>
                <div class="flex flex-col items-center gap-4 text-center">
                    <img src="{{ $qrImage }}" alt="QR untuk membuka reservasi {{ $assetName }}" class="h-64 w-64 max-w-full rounded-lg bg-white p-3 shadow-sm" />
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Pindai dengan kamera HP untuk membuka reservasi ini. Login tetap diperlukan.
                        QR berlaku 30 menit dan tidak otomatis memproses persetujuan atau pengembalian.
                    </p>
                    <x-filament::button tag="a" :href="$scanUrl" target="_blank" color="gray" outlined icon="heroicon-o-arrow-top-right-on-square">
                        Buka Tautan QR
                    </x-filament::button>
                </div>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
