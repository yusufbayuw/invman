<?php

namespace App\Filament\Resources\RelationManagers;

use App\Filament\Resources\G002M003ItemManagementResource;
use App\Models\G002M003ItemManagement;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

abstract class ManagementMembershipRelationManager extends RelationManager
{
    protected static bool $userOwner = false;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $resource = $pageClass::getResource();

        return (bool) auth()->user()?->is_enabled && ($resource::canView($ownerRecord) || $resource::canEdit($ownerRecord));
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

    private function canManage(?Model $record = null): bool
    {
        $resource = $this->getPageClass()::getResource();
        if ($this->isReadOnly() || ! $resource::canEdit($this->getOwnerRecord())) {
            return false;
        }

        return ! static::$userOwner || ! $record || G002M003ItemManagementResource::canEdit($record);
    }

    private function choices(string $search = ''): array
    {
        if (static::$userOwner) {
            return G002M003ItemManagementResource::getEloquentQuery()->where('name', 'like', '%'.$search.'%')->orderBy('name')->limit(50)->get()
                ->filter(fn ($group) => G002M003ItemManagementResource::canEdit($group))->pluck('name', 'id')->all();
        }

        return User::query()->where('is_enabled', true)->where('name', 'like', '%'.$search.'%')->orderBy('name')->limit(50)->pluck('name', 'id')->all();
    }

    private function changeMembership(int $id, bool $detach): void
    {
        abort_unless($this->canManage(), 403);
        $group = static::$userOwner ? G002M003ItemManagement::findOrFail($id) : $this->getOwnerRecord();
        $user = static::$userOwner ? $this->getOwnerRecord() : User::findOrFail($id);
        abort_unless(G002M003ItemManagementResource::canEdit($group), 403);
        abort_unless($detach || $user->is_enabled, 422);
        DB::transaction(function () use ($group, $user, $detach): void {
            // The custom pivot synchronizes the derived asset-manager role.
            if ($detach) {
                $group->users()->detach($user->id);
            } else {
                $group->users()->syncWithoutDetaching([$user->id]);
            }
        });
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label(static::$userOwner ? 'Kelompok Pengelola' : 'Nama Pengguna')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('description')->label('Deskripsi')->wrap()->visible(static::$userOwner),
            Tables\Columns\TextColumn::make('pivot.created_at')->label('Ditambahkan pada')->dateTime()->toggleable(isToggledHiddenByDefault: true),
        ])->headerActions([
            Tables\Actions\Action::make('addMember')->label(static::$userOwner ? 'Tambahkan ke Kelompok' : 'Tambahkan Anggota')
                ->authorize(fn () => $this->canManage())
                ->form([Select::make('member_id')->label(static::$userOwner ? 'Kelompok Pengelola' : 'Pengguna')->required()
                    ->options(fn () => $this->choices())->getSearchResultsUsing(fn (string $search) => $this->choices($search))
                    ->getOptionLabelUsing(fn ($value) => static::$userOwner ? G002M003ItemManagement::find($value)?->name : User::find($value)?->name)])
                ->action(fn (array $data) => $this->changeMembership((int) $data['member_id'], false)),
        ])->actions([
            Tables\Actions\Action::make('removeMember')->label('Lepaskan Keanggotaan')->color('danger')->requiresConfirmation()
                ->authorize(fn (Model $record) => $this->canManage($record))
                ->action(fn (Model $record) => $this->changeMembership((int) $record->getKey(), true)),
        ])->bulkActions([]);
    }
}
