<?php

namespace App\Filament\Resources\WorkOrderWorkflowResource\Pages;

use App\Filament\Resources\WorkOrderWorkflowResource;
use App\Models\WorkOrderWorkflow;
use App\Services\WorkOrderWorkflowService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class CreateWorkflow extends CreateRecord
{
    protected static string $resource = WorkOrderWorkflowResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): WorkOrderWorkflow {
            $unit = $data['g001_m001_unit_id'] ?? null;
            if ($unit) {
                \App\Models\G001M001Unit::query()->lockForUpdate()->findOrFail($unit);
            } else {
                DB::table('users')->where('id', auth()->id())->lockForUpdate()->first();
            }
            $hasDraft = WorkOrderWorkflow::query()
                ->where('g001_m001_unit_id', $unit)->where('status', 'draft')->exists();
            if ($hasDraft) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'workflow' => 'Masih ada draft untuk cakupan ini. Sunting draft tersebut terlebih dahulu.',
                ]);
            }
            $data['steps'] = app(WorkOrderWorkflowService::class)->validateSteps($data['steps'] ?? []);
            $data['version'] = (int) WorkOrderWorkflow::query()->where('g001_m001_unit_id', $unit)->max('version') + 1;
            $data['status'] = 'draft';
            $data['created_by'] = auth()->id();
            return WorkOrderWorkflow::query()->create($data);
        });
    }
}
