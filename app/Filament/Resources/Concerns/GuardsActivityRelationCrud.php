<?php

namespace App\Filament\Resources\Concerns;

use App\Filament\Resources\G004M008ActivityResource;
use App\Services\LoanAvailabilityService;
use App\Services\RecurringAssetScheduleService;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

trait GuardsActivityRelationCrud
{
    abstract protected static function reservationResource(): string;

    abstract protected static function reservationType(): string;

    protected function draftCanMutate(): bool
    {
        return auth()->user()?->is_enabled && $this->getOwnerRecord()->fresh()?->status === 'draft'
            && G004M008ActivityResource::canEdit($this->getOwnerRecord());
    }

    protected function canCreate(): bool
    {
        return $this->draftCanMutate() && static::reservationResource()::canCreate();
    }

    protected function canEdit(Model $record): bool
    {
        return $this->draftCanMutate() && $record->status === 'draft'
            && $record->g004_m008_activity_id === $this->getOwnerRecord()->id
            && static::reservationResource()::canEdit($record);
    }

    protected function canDelete(Model $record): bool
    {
        return $this->draftCanMutate() && $record->status === 'draft'
            && $record->g004_m008_activity_id === $this->getOwnerRecord()->id
            && static::reservationResource()::canDelete($record);
    }

    protected function canDeleteAny(): bool
    {
        return false;
    }

    protected function configureCreateAction(CreateAction $action): void
    {
        parent::configureCreateAction($action);
        $action->databaseTransaction()->mutateFormDataUsing(fn (array $data): array => $this->guardDraftData($data));
    }

    protected function configureEditAction(EditAction $action): void
    {
        parent::configureEditAction($action);
        $action->databaseTransaction()->mutateFormDataUsing(fn (array $data, Model $record): array => $this->guardDraftData($data, $record));
    }

    protected function configureDeleteAction(DeleteAction $action): void
    {
        parent::configureDeleteAction($action);
        $action->databaseTransaction()->before(function (Model $record): void {
            $owner = $this->getOwnerRecord()->newQuery()->lockForUpdate()->findOrFail($this->getOwnerRecord()->id);
            abort_unless(G004M008ActivityResource::canEdit($owner), 403);
            abort_unless($this->canDelete($record), 403);
        });
    }

    private function guardDraftData(array $data, ?Model $record = null): array
    {
        $owner = $this->getOwnerRecord()->newQuery()->lockForUpdate()->findOrFail($this->getOwnerRecord()->id);
        abort_unless(G004M008ActivityResource::canEdit($owner), 403);
        abort_unless($record ? $this->canEdit($record) : $this->canCreate(), 403);
        $data['g004_m008_activity_id'] = $owner->id;
        $data['status'] = 'draft';
        $data['start_time'] = $owner->start_time;
        $data['end_time'] = $owner->end_time;
        $type = static::reservationType();
        $key = match ($type) {
            'item' => 'g002_m007_item_id', 'room' => 'g003_m006_room_id', 'vehicle' => 'g008_m017_vehicle_id'
        };
        $assetId = (int) ($data[$key] ?? $record?->$key);
        $duplicates = $this->getRelationship()->where($key, $assetId);
        if ($record) {
            $duplicates->whereKeyNot($record->getKey());
        }
        if ($duplicates->lockForUpdate()->exists()) {
            throw ValidationException::withMessages([$key => 'Aset yang sama sudah ada di pengajuan ini. Ubah kebutuhan yang sudah ada.']);
        }
        $model = RecurringAssetScheduleService::assetModels()[$type];
        $asset = $model::query()->lockForUpdate()->find($assetId);
        $availability = app(LoanAvailabilityService::class);
        $available = $asset?->is_borrowable && match ($type) {
            'item' => $availability->availableItemQuantity($assetId, $owner->start_time, $owner->end_time, locking: true) >= max(1, (int) ($data['quantity'] ?? $record?->quantity)),
            'room' => $availability->roomIsAvailable($assetId, $owner->start_time, $owner->end_time, locking: true),
            'vehicle' => $availability->vehicleIsAvailable($assetId, $owner->start_time, $owner->end_time, locking: true),
        };
        if (! $available) {
            throw ValidationException::withMessages([$key => 'Aset tidak tersedia pada jadwal pengajuan, termasuk jadwal rutin atau perbaikan.']);
        }

        return $data;
    }
}
