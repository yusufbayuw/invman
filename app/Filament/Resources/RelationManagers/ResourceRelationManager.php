<?php

namespace App\Filament\Resources\RelationManagers;

use App\Models\G002M007Item;
use App\Models\G002M015ItemInstance;
use App\Models\G006M011ItemReview;
use App\Models\G006M012RoomReview;
use App\Models\G006M020VehicleReview;
use App\Models\Ticket;
use App\Services\AssetManagerMutationGuard;
use App\Services\TicketService;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

/** Reuse the complete resource UI; enforce both parent and related-record authority. */
abstract class ResourceRelationManager extends RelationManager
{
    protected static string $relatedResource;

    protected static bool $allowMutations = true;

    public static function relatedResource(): string
    {
        return static::$relatedResource;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $ownerResource = $pageClass::getResource();

        return (bool) auth()->user()?->is_enabled
            && ($ownerResource::canView($ownerRecord) || $ownerResource::canEdit($ownerRecord))
            && static::$relatedResource::canViewAny();
    }

    public function mount(): void
    {
        abort_unless(static::canViewForRecord($this->getOwnerRecord(), $this->getPageClass()), 403);
        parent::mount();
    }

    public function hydrate(): void
    {
        abort_unless(static::canViewForRecord($this->getOwnerRecord(), $this->getPageClass()), 403);
    }

    public function form(Form $form): Form
    {
        $form = static::$relatedResource::form($form);
        $foreignKey = $this->getRelationship()->getForeignKeyName();
        foreach ($form->getFlatComponents(withHidden: true) as $component) {
            if (method_exists($component, 'getName') && $component->getName() === $foreignKey) {
                $component->hidden()->dehydrated(false)->saveRelationshipsWhenHidden(false);
            }
        }

        return $form;
    }

    public function table(Table $table): Table
    {
        $table = static::$relatedResource::table($table);

        return $table
            ->description(static::$allowMutations
                ? ($this->isReadOnly() ? 'Mode lihat. Buka halaman Ubah induk untuk mengelola data terkait sesuai izin Anda.' : 'Perubahan mengikuti izin pada induk dan data terkait; hubungan ke induk tidak dapat dipindahkan dari tab ini.')
                : 'Catatan proses. Buka rincian untuk menjalankan alur pengajuan, persetujuan atau serah terima yang sesuai.')
            ->modifyQueryUsing(function (Builder $query): void {
                $column = $query->getModel()->qualifyColumn('id');
                $query->whereIn($column, static::$relatedResource::getEloquentQuery()->select($column));
            })
            ->headerActions([
                Tables\Actions\CreateAction::make()->databaseTransaction()->label('Tambah '.static::$relatedResource::getModelLabel())
                    ->mutateFormDataUsing(fn (array $data): array => $this->guardData($data))
                    ->using(fn (array $data): Model => $this->createRelated($data)),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Lihat'),
                Tables\Actions\Action::make('openRecord')->label('Buka rincian / proses')->icon('heroicon-o-arrow-top-right-on-square')
                    ->visible(fn (Model $record) => isset(static::$relatedResource::getPages()['view']) && static::$relatedResource::canView($record))
                    ->url(fn (Model $record) => isset(static::$relatedResource::getPages()['view']) ? static::$relatedResource::getUrl('view', ['record' => $record]) : null),
                Tables\Actions\EditAction::make()->databaseTransaction()->label('Ubah')
                    ->mutateFormDataUsing(fn (array $data, Model $record): array => $this->guardData($data, $record)),
                Tables\Actions\DeleteAction::make()->databaseTransaction()->label('Hapus')
                    ->before(function (Model $record): void {
                        $this->lockOwnerAndRecord($record);
                        abort_unless($this->canDelete($record), 403);
                    }),
            ])
            ->bulkActions([]);
    }

    protected function ownerCanMutate(): bool
    {
        $resource = $this->getPageClass()::getResource();

        return (bool) auth()->user()?->is_enabled && $resource::canEdit($this->getOwnerRecord());
    }

    protected function canCreate(): bool
    {
        return static::$allowMutations && $this->ownerCanMutate() && static::$relatedResource::canCreate()
            && ! ($this->getRelationship() instanceof HasOne && $this->getRelationship()->exists())
            && ! ($this->getOwnerRecord() instanceof G002M007Item && $this->getOwnerRecord()->isBulk()
                && static::$relatedResource::getModel() === G002M015ItemInstance::class);
    }

    protected function canView(Model $record): bool
    {
        return static::$relatedResource::canView($record);
    }

    protected function canEdit(Model $record): bool
    {
        return static::$allowMutations && $this->ownerCanMutate() && static::$relatedResource::canEdit($record)
            && ! $this->isLinkedReview($record);
    }

    protected function canDelete(Model $record): bool
    {
        return static::$allowMutations && $this->ownerCanMutate() && static::$relatedResource::canDelete($record)
            && ! $this->isLinkedReview($record);
    }

    protected function canDeleteAny(): bool
    {
        return false;
    }

    private function isLinkedReview(Model $record): bool
    {
        return in_array($record::class, [G006M011ItemReview::class, G006M012RoomReview::class, G006M020VehicleReview::class], true)
            && filled($record->g004_m008_activity_id);
    }

    private function lockOwnerAndRecord(?Model $record = null): void
    {
        $owner = $this->getOwnerRecord()->newQuery()->lockForUpdate()->findOrFail($this->getOwnerRecord()->getKey());
        $ownerResource = $this->getPageClass()::getResource();
        abort_unless($ownerResource::canEdit($owner), 403);
        if ($record) {
            $fresh = $this->getRelationship()->whereKey($record->getKey())->lockForUpdate()->first();
            abort_unless($fresh, 403);
            $record->setRawAttributes($fresh->getAttributes(), true);
        }
    }

    protected function guardData(array $data, ?Model $record = null): array
    {
        $this->lockOwnerAndRecord($record);
        abort_unless($record ? $this->canEdit($record) : $this->canCreate(), 403);
        // Lock ownership to the relation, regardless of forged hidden field values.
        $relationship = $this->getRelationship();
        $data[$relationship->getForeignKeyName()] = $relationship->getParentKey();
        $model = static::$relatedResource::getModel();
        if ($model === G002M007Item::class) {
            $data = app(AssetManagerMutationGuard::class)->item($data, auth()->user(), $record);
        }
        if ($model === G002M015ItemInstance::class) {
            $data = app(AssetManagerMutationGuard::class)->instance($data, auth()->user(), $record);
        }
        if (in_array($model, [G006M011ItemReview::class, G006M012RoomReview::class, G006M020VehicleReview::class], true)) {
            foreach (['g004_m008_activity_id', 'g005_m009_item_reservation_id', 'g005_m010_room_reservation_id', 'g005_m019_vehicle_reservation_id'] as $key) {
                if (filled($data[$key] ?? null)) {
                    throw ValidationException::withMessages([$key => 'Ulasan pengajuan harus melalui alur Ulasan Peminjaman.']);
                }
            }
        }

        return $data;
    }

    protected function createRelated(array $data): Model
    {
        $data = $this->guardData($data);
        if (static::$relatedResource::getModel() === Ticket::class) {
            return app(TicketService::class)->open(auth()->user(), $data);
        }

        return $this->getRelationship()->create($data);
    }
}
