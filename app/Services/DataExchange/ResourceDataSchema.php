<?php

namespace App\Services\DataExchange;

use App\Models as M;
use App\Services\RecurringAssetScheduleService;
use App\Support\AccountPasswordPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/** Explicit write schemas: workflow status, counters and credentials are never inferred from fillable. */
class ResourceDataSchema
{
    public function rules(string $model, ?Model $record = null): ?array
    {
        $name = ['required', 'string', 'max:255'];
        $text = ['nullable', 'string', 'max:10000'];
        $bool = ['required', 'boolean'];
        $fk = fn (string $class, bool $required = false): array => [$required ? 'required' : 'nullable', 'integer', Rule::exists((new $class)->getTable(), 'id')];
        $unique = fn (string $column): array => ['nullable', 'string', 'max:255', Rule::unique((new $model)->getTable(), $column)->ignore($record?->getKey())];
        $owner = ['g001_m001_unit_id' => $fk(M\G001M001Unit::class), 'g002_m003_item_management_id' => $fk(M\G002M003ItemManagement::class)];

        return match ($model) {
            M\Ticket::class => ['ticket_category_id' => $fk(M\TicketCategory::class, true),
                'title' => ['required', 'string', 'min:5', 'max:255'], 'description' => ['required', 'string', 'min:10', 'max:10000'],
                'location' => ['nullable', 'string', 'max:255'], 'reported_urgency' => ['required', Rule::in(['normal', 'urgent', 'emergency'])],
                'asset_type' => ['nullable', Rule::in(['item', 'item_instance', 'room', 'vehicle'])], 'asset_id' => ['nullable', 'integer']],
            Role::class => ['name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($record?->id)],
                'permissions' => ['nullable', 'array'], 'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', 'web')]],
            M\G004M008Activity::class => ['name' => $name, 'description' => ['required', 'string', 'max:10000'],
                'start_time' => ['required', 'date'], 'end_time' => ['required', 'date', 'after:start_time'],
                'notes' => $text, 'loan_event_id' => ['nullable', 'uuid'], 'related_activity_id' => ['nullable', 'uuid'],
                'borrower_id' => $fk(M\User::class), 'override_reason' => $text,
                'needs' => ['required', 'array', 'min:1', 'max:100'], 'needs.*.type' => ['required', Rule::in(['item', 'room', 'vehicle'])],
                'needs.*.item_id' => ['required_if:needs.*.type,item', 'integer'],
                'needs.*.room_id' => ['required_if:needs.*.type,room', 'integer'],
                'needs.*.vehicle_id' => ['required_if:needs.*.type,vehicle', 'integer'],
                'needs.*.quantity' => ['required_if:needs.*.type,item', 'integer', 'min:1']],
            M\G001M001Unit::class => ['name' => $name],
            M\G002M002ItemType::class, M\G002M003ItemManagement::class, M\WorkOrderJobTitle::class => ['name' => $name, 'description' => $text]
                + ($model === M\WorkOrderJobTitle::class ? ['is_active' => $bool] : [])
                + ($model === M\G002M003ItemManagement::class ? ['member_ids' => ['nullable', 'array'], 'member_ids.*' => $fk(M\User::class, true)] : []),
            M\G003M004Building::class => ['name' => $name, 'location' => $text],
            M\G003M005Floor::class => ['name' => $name, 'g003_m004_building_id' => $fk(M\G003M004Building::class, true)],
            M\G002M007Item::class => $owner + [
                'name' => $name, 'code' => $unique('code'), 'g002_m002_item_type_id' => $fk(M\G002M002ItemType::class),
                'g003_m006_room_id' => $fk(M\G003M006Room::class), 'tracking_mode' => ['required', Rule::in(['individual', 'bulk'])],
                'quantity' => ['required', 'integer', 'min:0', 'max:10000'], 'is_borrowable' => $bool,
            ],
            M\G002M015ItemInstance::class => [
                'g002_m007_item_id' => $fk(M\G002M007Item::class, true), 'g003_m006_room_id' => $fk(M\G003M006Room::class),
                'g001_m001_unit_id' => $fk(M\G001M001Unit::class), 'name' => $name, 'code' => $unique('code'), 'is_borrowable' => $bool,
            ],
            M\G003M006Room::class => $owner + [
                'name' => $name, 'g003_m005_floor_id' => $fk(M\G003M005Floor::class), 'capacity' => ['nullable', 'integer', 'min:0'],
                'status' => ['required', Rule::in(['Tersedia', 'Tidak Tersedia', 'Dalam Perbaikan'])], 'is_borrowable' => $bool,
            ],
            M\G008M017Vehicle::class => $owner + [
                'name' => $name, 'license_plate' => $unique('license_plate'), 'default_driver_id' => $fk(M\G008M018Driver::class),
                'stnk_date' => ['nullable', 'date_format:Y-m-d'], 'kir_date' => ['nullable', 'date_format:Y-m-d'],
                'capacity' => ['nullable', 'integer', 'min:0'], 'is_borrowable' => $bool, 'requires_assistant' => $bool,
                'status' => ['nullable', 'string', 'max:100'],
            ],
            M\G008M018Driver::class => ['user_id' => $fk(M\User::class, true), 'sim_number' => ['required', 'string', 'max:50'],
                'sim_type' => ['required', Rule::in(['A', 'A Umum', 'B1', 'B1 Umum', 'B2', 'B2 Umum'])]],
            M\VehicleAssistant::class => ['name' => $name, 'phone' => ['nullable', 'string', 'max:50'], 'notes' => $text, 'is_active' => $bool],
            M\TicketCategory::class => ['name' => $name, 'slug' => ['required', 'string', 'max:255', Rule::unique('ticket_categories', 'slug')->ignore($record?->id)], 'is_active' => $bool, 'reporting_enabled' => $bool],
            M\WorkOrderAssignment::class => $owner + ['user_id' => $fk(M\User::class, true), 'work_order_job_title_id' => $fk(M\WorkOrderJobTitle::class, true), 'supervisor_user_id' => $fk(M\User::class), 'is_active' => $bool],
            M\TicketTeamMember::class => ['user_id' => [...$fk(M\User::class, true), Rule::unique('ticket_team_members', 'user_id')->ignore($record?->id)],
                'supervisor_user_id' => $fk(M\User::class), 'g002_m003_item_management_id' => $fk(M\G002M003ItemManagement::class),
                'team_name' => ['required', 'string', 'max:100'], 'team_type' => ['required', Rule::in(['field', 'support'])],
                'position' => ['required', Rule::in(['lead', 'technician'])], 'is_active' => $bool],
            M\WorkOrderWorkflow::class => ['g001_m001_unit_id' => $fk(M\G001M001Unit::class),
                'assign_job_title_id' => $fk(M\WorkOrderJobTitle::class), 'close_job_title_id' => $fk(M\WorkOrderJobTitle::class),
                'dispatch_job_title_id' => $fk(M\WorkOrderJobTitle::class), 'steps' => ['required', 'array']],
            M\User::class => ['name' => $name, 'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($record?->id)],
                'username' => $unique('username'), 'password' => [$record ? 'nullable' : 'required', AccountPasswordPolicy::rule()],
                'g001_m001_unit_id' => $fk(M\G001M001Unit::class), 'is_enabled' => $bool,
                'roles' => ['nullable', 'array'], 'roles.*' => [Rule::in([config('role.admin'), config('role.fasilitas'), config('role.sarpras')])]],
            M\LoanEvent::class => ['name' => $name, 'description' => $text, 'start_time' => ['nullable', 'date'], 'end_time' => ['nullable', 'date', 'after:start_time']],
            M\RecurringAssetSchedule::class => ['asset_type' => ['required', Rule::in(RecurringAssetScheduleService::assetModels())],
                'asset_id' => ['required', 'integer'], 'name' => $name, 'weekdays' => ['required', 'array', 'min:1'], 'weekdays.*' => ['integer', 'between:1,7', 'distinct'],
                'start_time' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'], 'end_time' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
                'valid_from' => ['required', 'date_format:Y-m-d'], 'valid_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:valid_from'], 'is_active' => $bool],
            M\G007M013ItemHistory::class => ['g002_m015_item_instance_id' => $fk(M\G002M015ItemInstance::class, true), 'user_id' => $fk(M\User::class, true), 'action' => $name, 'notes' => $text],
            M\G007M014RoomHistory::class => ['g003_m006_room_id' => $fk(M\G003M006Room::class, true), 'user_id' => $fk(M\User::class, true), 'action' => $name, 'notes' => $text],
            M\G007M021VehicleHistory::class => ['g008_m017_vehicle_id' => $fk(M\G008M017Vehicle::class, true), 'user_id' => $fk(M\User::class, true), 'action' => $name, 'notes' => $text],
            M\G006M011ItemReview::class => ['g002_m015_item_instance_id' => $fk(M\G002M015ItemInstance::class, true), 'user_id' => $fk(M\User::class, true), 'rating' => ['required', 'integer', 'between:1,5'], 'review' => $text],
            M\G006M012RoomReview::class => ['g003_m006_room_id' => $fk(M\G003M006Room::class, true), 'user_id' => $fk(M\User::class, true), 'rating' => ['required', 'integer', 'between:1,5'], 'review' => $text],
            M\G006M020VehicleReview::class => ['g008_m017_vehicle_id' => $fk(M\G008M017Vehicle::class, true), 'user_id' => $fk(M\User::class, true), 'rating' => ['required', 'integer', 'between:1,5'], 'review' => $text],
            default => $this->inspectionRules($model, $fk, $text),
        };
    }

    private function inspectionRules(string $model, \Closure $fk, array $text): ?array
    {
        $asset = match ($model) {
            M\G009M022ItemInstanceChecklist::class => ['g002_m015_item_instance_id', M\G002M015ItemInstance::class],
            M\G009M023RoomChecklist::class => ['g003_m006_room_id', M\G003M006Room::class],
            M\G009M024VehicleChecklist::class => ['g008_m017_vehicle_id', M\G008M017Vehicle::class],
            default => null,
        };
        if (! $asset) {
            return null;
        }

        return [$asset[0] => $fk($asset[1], true), 'user_id' => $fk(M\User::class, true),
            'date' => ['required', 'date_format:Y-m-d'], 'checklist_date' => ['required', 'date'], 'is_ok' => ['required', 'boolean'], 'notes' => $text];
    }

    public function columns(string $model): array
    {
        $rules = $this->rules($model);
        if ($rules !== null) {
            return array_values(array_filter(array_keys($rules), fn ($key) => ! str_contains($key, '.')));
        }

        // Export-only operational data: never expose authentication or upload internals.
        return array_values(array_diff((new $model)->getFillable(), (new $model)->getHidden(), [
            'password', 'remember_token', 'files', 'photo', 'avatar', 'qrcode', 'qr_uuid',
        ]));
    }

    public function readOnlyColumns(string $model): array
    {
        return match ($model) {
            M\G004M008Activity::class => ['status', 'g001_m001_unit_id', 'user_id'],
            M\Ticket::class => ['number', 'status', 'reporter_id', 'g001_m001_unit_id'],
            M\G002M007Item::class => ['available_quantity'],
            M\WorkOrderWorkflow::class => ['status', 'version'],
            default => [],
        };
    }

    public function defaults(string $model): array
    {
        return match ($model) {
            M\Ticket::class => ['reported_urgency' => 'normal'],
            M\G002M007Item::class => ['tracking_mode' => 'individual', 'quantity' => 0, 'is_borrowable' => false],
            M\G003M006Room::class => ['status' => 'Tersedia', 'is_borrowable' => false],
            M\G008M017Vehicle::class => ['requires_assistant' => false, 'is_borrowable' => false],
            M\User::class => ['is_enabled' => true],
            M\TicketCategory::class => ['is_active' => true, 'reporting_enabled' => true],
            M\WorkOrderJobTitle::class, M\WorkOrderAssignment::class, M\TicketTeamMember::class, M\VehicleAssistant::class, M\RecurringAssetSchedule::class => ['is_active' => true],
            M\G002M015ItemInstance::class => ['is_borrowable' => false],
            default => [],
        };
    }
}
