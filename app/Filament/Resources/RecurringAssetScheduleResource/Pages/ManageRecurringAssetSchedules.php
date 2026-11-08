<?php

namespace App\Filament\Resources\RecurringAssetScheduleResource\Pages;

use App\Filament\Resources\RecurringAssetScheduleResource;
use App\Models\RecurringAssetSchedule;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageRecurringAssetSchedules extends ManageRecords
{
    protected static string $resource = RecurringAssetScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->using(function (array $data) {
            $schedule = new RecurringAssetSchedule($data);
            abort_unless(RecurringAssetScheduleResource::canEdit($schedule), 403);
            $schedule->save();

            return $schedule;
        })];
    }
}
