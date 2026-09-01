<div class="space-y-4">
    @forelse ($corrections as $correction)
        <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="font-medium text-gray-950 dark:text-white">
                    {{ $correction->old_value ?: '-' }} → {{ $correction->new_value ?: '-' }}
                </span>
                <span class="text-sm text-gray-500">
                    {{ $correction->created_at?->translatedFormat('d M Y, H:i') }}
                </span>
            </div>
            <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ $correction->reason }}</p>
            <p class="mt-2 text-xs text-gray-500">Dikoreksi oleh {{ $correction->correctedBy?->name ?? 'Admin' }}</p>
        </div>
    @empty
        <p class="text-sm text-gray-500">Belum ada koreksi administratif.</p>
    @endforelse
</div>
