<?php

namespace App\Services;

use App\Models\G002M007Item;
use App\Models\G002M015ItemInstance;
use App\Models\G003M006Room;
use App\Models\G004M008Activity;
use App\Models\G008M017Vehicle;
use App\Models\G005M009ItemReservation;
use App\Models\G005M010RoomReservation;
use App\Models\G005M019VehicleReservation;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketCategory;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TicketService
{
    private const ASSETS = [
        'item' => G002M007Item::class,
        'item_instance' => G002M015ItemInstance::class,
        'room' => G003M006Room::class,
        'vehicle' => G008M017Vehicle::class,
    ];

    private const TRANSITIONS = [
        'open' => ['triaged', 'in_progress', 'cancelled'],
        'triaged' => ['in_progress', 'waiting_requester', 'waiting_parts', 'cancelled'],
        'in_progress' => ['waiting_requester', 'waiting_parts', 'resolved', 'cancelled'],
        'waiting_requester' => ['in_progress', 'cancelled'],
        'waiting_parts' => ['in_progress', 'cancelled'],
        'resolved' => ['closed', 'reopened'],
        'closed' => ['reopened'],
        'reopened' => ['triaged', 'in_progress', 'cancelled'],
        'cancelled' => [],
    ];

    public function __construct(private readonly TicketVisibility $visibility) {}

    public function canCreate(?User $user): bool
    {
        return (bool) ($user && ($user->isSarpras() || $user->isFacility() || $user->isAssetManager()));
    }

    public function assetOptions(string $type, ?User $user = null): array
    {
        $user ??= auth()->user();
        $class = self::ASSETS[$type] ?? null;
        if (! $class || ! $user) {
            return [];
        }

        $query = $class::query();
        if (! $user->isFacility()) {
            $unitId = $user->isSarpras() ? $user->g001_m001_unit_id : null;
            $managementIds = $user->itemManagements()->pluck('g002_m003_item_management.id');
            $query->where(function ($visible) use ($unitId, $managementIds, $type): void {
                if ($type === 'item_instance') {
                    if ($unitId) {
                        $visible->orWhereHas('item', fn ($q) => $q->where('g001_m001_unit_id', $unitId));
                        $visible->orWhere('g001_m001_unit_id', $unitId);
                    }
                    if ($managementIds->isNotEmpty()) {
                        $visible->orWhereHas('item', fn ($q) => $q->whereIn('g002_m003_item_management_id', $managementIds));
                    }
                } else {
                    if ($unitId) { $visible->orWhere('g001_m001_unit_id', $unitId); }
                    if ($managementIds->isNotEmpty()) {
                        $visible->orWhereIn('g002_m003_item_management_id', $managementIds);
                    }
                }
                if (! $unitId && $managementIds->isEmpty()) {
                    $visible->whereRaw('1=0');
                }
            });
        }

        return $query->orderBy('name')->limit(250)->get()
            ->mapWithKeys(fn ($asset): array => [
                $asset->id => $asset->name.(filled($asset->code) ? ' · '.$asset->code : ''),
            ])->all();
    }

    private function resolveAsset(?string $type, $id, ?User $user): ?array
    {
        if (blank($type) && blank($id)) {
            return null;
        }
        if (! isset(self::ASSETS[$type]) || ! is_numeric($id) || (int) $id < 1) {
            throw ValidationException::withMessages(['asset_id' => 'Jenis atau identitas aset tidak valid.']);
        }

        $asset = self::ASSETS[$type]::query()->find((int) $id);
        if (! $asset) {
            throw ValidationException::withMessages(['asset_id' => 'Aset tidak ditemukan.']);
        }

        $parent = $type === 'item_instance' ? $asset->item : $asset;
        $managementId = $parent?->g002_m003_item_management_id;
        $unitId = $parent?->g001_m001_unit_id
            ?? ($type === 'item_instance' ? $asset->g001_m001_unit_id : null);

        if ($user && ! $user->isFacility()
            && ! ($user->belongsToUnit($unitId) || ($managementId
                && $user->itemManagements()->whereKey($managementId)->exists()))) {
            throw ValidationException::withMessages(['asset_id' => 'Aset di luar unit atau kewenangan pelapor.']);
        }

        return ['type' => $type, 'id' => (int) $id,
            'name' => $asset->name, 'management' => $managementId, 'unit' => $unitId];
    }

    public function open(User $actor, array $data): Ticket
    {
        if (! $this->canCreate($actor)) {
            throw ValidationException::withMessages(['ticket' => 'Akun tidak berwenang membuat tiket.']);
        }
        $category = TicketCategory::query()->where('is_active', true)->find($data['ticket_category_id'] ?? null);
        if (! $category) {
            throw ValidationException::withMessages(['ticket_category_id' => 'Kategori tiket tidak aktif atau tidak valid.']);
        }
        $title = trim((string) ($data['title'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        if (mb_strlen($title) < 5 || mb_strlen($title) > 255 || mb_strlen($description) < 10 || mb_strlen($description) > 10000) {
            throw ValidationException::withMessages(['title' => 'Judul 5–255 karakter dan deskripsi 10–10.000 karakter wajib diisi.']);
        }

        $asset = $this->resolveAsset($data['asset_type'] ?? null, $data['asset_id'] ?? null, $actor);
        $unitId = $actor->g001_m001_unit_id ?: ($asset['unit'] ?? null);
        if (! $unitId && $actor->isFacility()) {
            $unitId = $data['g001_m001_unit_id'] ?? null;
        }
        if ($unitId && ! DB::table('g001_m001_units')->where('id', $unitId)->exists()) {
            throw ValidationException::withMessages(['g001_m001_unit_id' => 'Unit tidak valid.']);
        }
        $activity = null;
        if (filled($data['g004_m008_activity_id'] ?? null)) {
            $activity = G004M008Activity::query()->find($data['g004_m008_activity_id']);
            if (! $activity || ! ($actor->isFacility() || $actor->belongsToUnit($activity->g001_m001_unit_id) || $actor->managesActivity($activity))) {
                throw ValidationException::withMessages(['g004_m008_activity_id' => 'Pengajuan di luar kewenangan.']);
            }
        }

        $priority = $actor->isFacility() && in_array(($data['priority'] ?? ''), ['low','normal','high','critical'], true)
            ? $data['priority'] : 'normal';

        $ticket = DB::transaction(function () use ($actor, $category, $asset, $activity, $title, $description, $priority, $unitId): Ticket {
            $ticket = Ticket::query()->create([
                'number' => 'TKT-'.now()->format('Y').'-'.Str::upper(Str::random(12)),
                'ticket_category_id' => $category->id,
                'reporter_id' => $actor->id,
                'g001_m001_unit_id' => $unitId,
                'g002_m003_item_management_id' => $asset['management'] ?? null,
                'g004_m008_activity_id' => $activity?->id,
                'title' => $title,
                'description' => $description,
                'priority' => $priority,
                'status' => 'open',
            ]);
            if ($asset) {
                $ticket->assets()->create(['asset_type' => $asset['type'], 'asset_id' => $asset['id']]);
            }
            $this->event($ticket, $actor->id, 'created', null, 'open');
            return $ticket;
        }, 3);

        $this->notifyTeam($ticket);

        return $ticket;
    }

    public function transition(Ticket $ticket, User $actor, string $to, ?string $notes = null): Ticket
    {
        return DB::transaction(function () use ($ticket, $actor, $to, $notes): Ticket {
            $record = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            if (! $this->visibility->canView($actor, $record)
                || ! in_array($to, self::TRANSITIONS[$record->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => 'Perubahan status tiket tidak diizinkan.']);
            }

            $isManager = $this->visibility->canManage($actor, $record);
            $isReporter = $record->reporter_id === $actor->id;
            $allowed = $isManager
                ? in_array($to, ['triaged','in_progress','waiting_requester','waiting_parts','resolved','closed','cancelled','reopened'], true)
                : $isReporter && in_array($to, ['closed','reopened'], true);
            if (! $allowed) {
                throw ValidationException::withMessages(['status' => 'Hanya pengelola yang dapat memproses atau pelapor yang dapat memverifikasi penyelesaian.']);
            }

            $from = $record->status;
            $record->forceFill([
                'status' => $to,
                'resolved_at' => $to === 'resolved' ? now() : ($to === 'reopened' ? null : $record->resolved_at),
                'closed_at' => $to === 'closed' ? now() : ($to === 'reopened' ? null : $record->closed_at),
            ])->save();
            $this->event($record, $actor->id, 'status', $from, $to, $notes);

            return $record;
        }, 3);
    }

    public function availableTransitions(Ticket $ticket, User $actor): array
    {
        if (! $this->visibility->canView($actor, $ticket)) {
            return [];
        }
        $manager = $this->visibility->canManage($actor, $ticket);
        $reporter = $ticket->reporter_id === $actor->id;
        return collect(self::TRANSITIONS[$ticket->status] ?? [])
            ->filter(fn (string $to): bool => $manager
                ? in_array($to, ['triaged','in_progress','waiting_requester','waiting_parts','resolved','closed','cancelled','reopened'], true)
                : $reporter && in_array($to, ['closed','reopened'], true))
            ->mapWithKeys(fn (string $to): array => [$to => match ($to) {
                'triaged' => 'Ditinjau', 'in_progress' => 'Dikerjakan',
                'waiting_requester' => 'Menunggu Pelapor', 'waiting_parts' => 'Menunggu Suku Cadang',
                'resolved' => 'Selesai Dikerjakan', 'closed' => 'Ditutup',
                'reopened' => 'Dibuka Kembali', 'cancelled' => 'Dibatalkan',
                default => $to,
            }])->all();
    }

    public function assign(Ticket $ticket, User $actor, ?int $userId, ?string $priority = null): Ticket
    {
        return DB::transaction(function () use ($ticket, $actor, $userId, $priority): Ticket {
            $record = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            if (! $this->visibility->canManage($actor, $record) || in_array($record->status, ['closed','cancelled'], true)) {
                throw ValidationException::withMessages(['assigned_to' => 'Penugasan tidak diizinkan.']);
            }
            if ($userId) {
                $assignee = User::query()->find($userId);
                if (! $assignee || (! $assignee->isFacility()
                    && (! $record->g002_m003_item_management_id
                        || ! $assignee->itemManagements()->whereKey($record->g002_m003_item_management_id)->exists()))) {
                    throw ValidationException::withMessages(['assigned_to' => 'Petugas harus berada dalam pengelola aset terkait.']);
                }
            }
            $old = $record->assigned_to;
            $record->assigned_to = $userId;
            if ($priority !== null && $actor->isFacility() && in_array($priority, ['low','normal','high','critical'], true)) {
                $record->priority = $priority;
            }
            $record->save();
            $this->event($record, $actor->id, 'assigned', (string) $old, (string) $userId, $priority);
            return $record;
        }, 3);
    }

    public function comment(Ticket $ticket, User $actor, string $body, bool $internal = false): TicketComment
    {
        $text = trim($body);
        if (mb_strlen($text) < 2 || mb_strlen($text) > 10000
            || ! $this->visibility->canView($actor, $ticket)
            || ($internal && ! $this->visibility->canManage($actor, $ticket))) {
            throw ValidationException::withMessages(['body' => 'Komentar tidak valid atau akses ditolak.']);
        }

        return DB::transaction(function () use ($ticket, $actor, $text, $internal): TicketComment {
            $comment = $ticket->comments()->create(['user_id' => $actor->id, 'body' => $text, 'is_internal' => $internal]);
            $this->event($ticket, $actor->id, $internal ? 'internal_note' : 'comment', null, null);
            return $comment;
        });
    }

    /** Files are stored on the private local disk, never on /storage public links. */
    public function attach(Ticket $ticket, User $actor, array $paths, ?TicketComment $comment = null): void
    {
        if (! $this->visibility->canView($actor, $ticket) || count($paths) > 3
            || ($comment && ($comment->ticket_id !== $ticket->id
                || ($comment->is_internal && ! $this->visibility->canManage($actor, $ticket))))) {
            throw ValidationException::withMessages(['files' => 'Tidak diizinkan atau maksimal tiga lampiran.']);
        }

        $disk = Storage::disk('local');
        $validated = [];
        foreach ($paths as $path) {
            if (! is_string($path) || ! str_starts_with($path, 'tickets/attachments/')
                || str_contains($path, '..') || str_contains($path, '\\')
                || ! preg_match('#^tickets/attachments/[A-Za-z0-9_.\\-/]+$#', $path)
                || ! $disk->exists($path)) {
                throw ValidationException::withMessages(['files' => 'Lampiran tidak valid.']);
            }
            $mime = $disk->mimeType($path);
            $size = $disk->size($path);
            if (! in_array($mime, ['application/pdf','image/png','image/jpeg'], true)
                || $size > 5242880) {
                throw ValidationException::withMessages(['files' => 'Hanya PDF/JPG/PNG maksimal 5 MB.']);
            }
            $validated[] = compact('path','mime','size');
        }

        DB::transaction(function () use ($ticket, $actor, $validated, $comment): void {
            foreach ($validated as $file) {
                $ticket->attachments()->create([
                    'uploaded_by' => $actor->id,
                    'ticket_comment_id' => $comment?->id,
                    'disk' => 'local',
                    'path' => $file['path'],
                    'original_name' => basename($file['path']),
                    'mime_type' => $file['mime'],
                    'size' => $file['size'],
                ]);
            }
            if ($validated !== []) {
                $this->event($ticket, $actor->id, 'attachment', null, (string) count($validated));
            }
        });
    }

    private function event(Ticket $ticket, ?int $actorId, string $action, ?string $from, ?string $to, ?string $notes = null): void
    {
        $ticket->events()->create(['actor_id' => $actorId, 'action' => $action, 'from_value' => $from, 'to_value' => $to, 'notes' => $notes]);
    }

    /**
     * One ticket per specific damaged asset + return transaction.
     * Called only after the existing return checklist is recorded.
     */
    public function damagedReturn(string $type, Model $reservation): void
    {
        $assetType = match ($type) { 'item' => 'item_instance', 'room' => 'room', 'vehicle' => 'vehicle', default => null };
        if (! $assetType) { return; }
        $checks = $reservation->returnChecklists()->where('is_ok', false)->get();
        foreach ($checks as $check) {
            $assetId = match ($type) {
                'item' => $check->g002_m015_item_instance_id,
                'room' => $reservation->g003_m006_room_id,
                'vehicle' => $reservation->g008_m017_vehicle_id,
            };
            if (! $assetId) { continue; }

            $sourceKey = "return:{$type}:{$reservation->id}:{$assetId}";
            if (Ticket::query()->where('source_key', $sourceKey)->exists()) { continue; }

            $asset = $this->resolveAsset($assetType, $assetId, null);
            $categorySlug = match ($type) { 'item' => 'damage_item', 'room' => 'damage_room', 'vehicle' => 'vehicle_service' };
            $category = TicketCategory::query()->where('slug', $categorySlug)->first();
            if (! $category) { continue; }

            $ticket = Ticket::query()->create([
                'number' => 'TKT-'.now()->format('Y').'-'.Str::upper(Str::random(12)),
                'ticket_category_id' => $category->id,
                'reporter_id' => $reservation->activity?->user_id,
                'g001_m001_unit_id' => $reservation->activity?->g001_m001_unit_id,
                'g002_m003_item_management_id' => $asset['management'],
                'g004_m008_activity_id' => $reservation->g004_m008_activity_id,
                'source_key' => $sourceKey,
                'title' => 'Kondisi tidak baik: '.$asset['name'],
                'description' => trim($check->notes ?: 'Ditemukan kondisi tidak baik saat pengembalian aset.'),
                'priority' => 'high',
                'status' => 'open',
            ]);
            $ticket->assets()->create(['asset_type' => $assetType, 'asset_id' => $assetId]);
            $this->event($ticket, auth()->id(), 'created_from_return', null, 'open', $sourceKey);
            DB::afterCommit(fn () => $this->notifyTeam($ticket));
        }
    }

    private function notifyTeam(Ticket $ticket): void
    {
        $ticket->loadMissing('management');
        $users = $ticket->management?->users ?? collect();
        if ($users->isNotEmpty()) {
            Notification::make()->title('Tiket baru: '.$ticket->number)
                ->body($ticket->title)->warning()->sendToDatabase($users);
        }
    }
}
