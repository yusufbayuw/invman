<?php

namespace App\Filament\Resources\RelationManagers;

use App\Filament\Resources\RecurringAssetScheduleResource;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RecurringSchedulesRelationManager extends RelationManager
{
    protected static string $relationship = 'recurringSchedules';

    protected static ?string $title = 'Jadwal Rutin (Blokir Booking)';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return RecurringAssetScheduleResource::moduleVisible()
            && parent::canViewForRecord($ownerRecord, $pageClass);
    }

    public function form(Form $form): Form
    {
        return $form->schema(RecurringAssetScheduleResource::scheduleFields())->columns(2);
    }

    public function table(Table $table): Table
    {
        return RecurringAssetScheduleResource::table($table)->headerActions([
            Tables\Actions\CreateAction::make()->visible(fn () => $this->canCreate()),
        ])->actions([
            Tables\Actions\EditAction::make()->visible(fn () => $this->canManage()),
            Tables\Actions\DeleteAction::make()->visible(fn (Model $record) => $this->canDelete($record)),
        ]);
    }

    protected function canCreate(): bool
    {
        return RecurringAssetScheduleResource::moduleEnabled() && $this->canManage();
    }

    protected function canEdit(Model $record): bool
    {
        return $this->canManage();
    }

    protected function canDelete(Model $record): bool
    {
        return RecurringAssetScheduleResource::moduleEnabled() && $this->canManage();
    }

    private function canManage(): bool
    {
        $resource = RecurringAssetScheduleResource::assetResources()[$this->getOwnerRecord()::class];

        return $resource::canEdit($this->getOwnerRecord());
    }
}
