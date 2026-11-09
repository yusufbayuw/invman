<?php

namespace App\Services\DataExchange;

use App\Filament\Pages\PeminjamanSaya;
use App\Models as M;
use App\Services\AssetManagerMutationGuard;
use App\Services\AssetManagerRoleSynchronizer;
use App\Services\LoanRequestService;
use App\Services\TicketService;
use App\Services\WorkOrderWorkflowService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResourceDataExchange
{
    public function __construct(private ResourceDataSchema $schema, private XlsxDataFile $xlsx) {}

    public function supportsImport(string $resource): bool
    {
        return $this->schema->rules($resource::getModel()) !== null;
    }

    public function download(string $resource, bool $template = false, ?Builder $query = null): StreamedResponse
    {
        abort_unless(auth()->check() && $resource::canViewAny(), 403);
        abort_if($template && ! $this->supportsImport($resource), 403);
        $model = $resource::getModel();
        $columns = ['id', ...$this->schema->columns($model), ...($template ? [] : $this->schema->readOnlyColumns($model))];
        // Intersect table filters with the resource scope, never trust a supplied query's scope.
        $scoped = $resource::getEloquentQuery();
        if ($query) {
            $scoped->whereIn((new $model)->qualifyColumn('id'), (clone $query)->reorder()->select((new $model)->qualifyColumn('id')));
        }

        return $this->xlsx->download(
            ($template ? 'template-' : 'export-').$resource::getSlug().'.xlsx',
            $columns,
            $template ? [] : $this->exportRows($resource, $scoped, $columns),
        );
    }

    private function exportRows(string $resource, Builder $query, array $columns): \Generator
    {
        foreach ($query->reorder()->lazyById(200) as $record) {
            if (! $resource::canView($record)) {
                continue;
            }

            yield array_map(fn ($column) => $this->exportValue($record, $column), $columns);
        }
    }

    public function downloadNeeds(Builder $query): StreamedResponse
    {
        abort_unless(PeminjamanSaya::canAccess(), 403);
        $columns = ['type', 'need_name', 'activity_id', 'activity_name', 'activity_status', 'unit_name', 'start_time', 'end_time', 'returned_at', 'status', 'receipt_number'];
        $rows = (function () use ($query, $columns): \Generator {
            foreach ((clone $query)->reorder()->cursor() as $record) {
                yield array_map(fn ($column) => $this->exportValue($record, $column), $columns);
            }
        })();

        return $this->xlsx->download('peminjaman.xlsx', $columns, $rows);
    }

    private function exportValue(Model $record, string $column): mixed
    {
        if ($record instanceof M\Ticket && in_array($column, ['asset_type', 'asset_id'], true)) {
            return $record->assets->first()?->$column;
        }
        if ($column === 'password' || $column === 'override_reason') {
            return '';
        }
        if ($column === 'permissions' && $record instanceof Role) {
            return $record->permissions()->pluck('name')->all();
        }
        if ($record instanceof M\G004M008Activity) {
            if ($column === 'borrower_id') {
                return $record->user_id;
            }
            if ($column === 'needs') {
                return array_merge(
                    $record->item_reservation->map(fn ($r) => ['type' => 'item', 'item_id' => $r->g002_m007_item_id, 'quantity' => $r->quantity])->all(),
                    $record->room_reservation->map(fn ($r) => ['type' => 'room', 'room_id' => $r->g003_m006_room_id])->all(),
                    $record->vehicle_reservation->map(fn ($r) => ['type' => 'vehicle', 'vehicle_id' => $r->g008_m017_vehicle_id])->all(),
                );
            }
        }
        if ($column === 'roles' && $record instanceof M\User) {
            return $record->roles()->whereIn('name', [config('role.admin'), config('role.fasilitas'), config('role.sarpras')])->pluck('name')->all();
        }
        if ($column === 'member_ids') {
            return $record->users()->pluck('users.id')->all();
        }
        $value = $record->getAttribute($column);
        if ($value instanceof \DateTimeInterface) {
            return $value->format(in_array($column, ['valid_from', 'valid_until', 'date', 'stnk_date', 'kir_date'], true) ? 'Y-m-d' : 'Y-m-d H:i:s');
        }

        return $value;
    }

    /** All rows commit together. Any invalid row rolls the entire upload back. */
    public function import(string $resource, string $path, string $mode = 'upsert'): array
    {
        abort_unless(auth()->check() && $resource::canViewAny() && $this->supportsImport($resource), 403);
        if (! in_array($mode, ['create', 'update', 'upsert'], true)) {
            throw ValidationException::withMessages(['file' => 'Mode impor tidak valid.']);
        }
        $rows = $this->xlsx->readRows($path, ['id', ...$this->schema->columns($resource::getModel()), ...$this->schema->readOnlyColumns($resource::getModel())]);

        return DB::transaction(function () use ($resource, $rows, $mode): array {
            $counts = ['created' => 0, 'updated' => 0];
            $errors = [];
            $seen = [];
            foreach ($rows as $line => $row) {
                try {
                    $id = $row['id'] ?? null;
                    if ($id !== null && isset($seen[$id])) {
                        throw ValidationException::withMessages(['id' => 'ID yang sama muncul lebih dari sekali.']);
                    }
                    if ($id !== null) {
                        $seen[$id] = true;
                    }
                    if (($mode === 'create' && $id !== null) || ($mode === 'update' && $id === null)) {
                        throw ValidationException::withMessages(['id' => 'Mode tambah membutuhkan ID kosong; mode edit membutuhkan ID terisi.']);
                    }
                    $record = $id === null ? null : $resource::getEloquentQuery()->lockForUpdate()->find($id);
                    if ($id !== null && ! $record) {
                        throw ValidationException::withMessages(['id' => 'Data tidak ditemukan atau di luar cakupan akses Anda.']);
                    }
                    abort_unless($record ? $resource::canEdit($record) : $resource::canCreate(), 403);
                    unset($row['id']);
                    foreach ($this->schema->readOnlyColumns($resource::getModel()) as $column) {
                        unset($row[$column]);
                    }
                    $this->saveRow($resource, $row, $record);
                    $counts[$record ? 'updated' : 'created']++;
                } catch (ValidationException $e) {
                    $errors['file.row_'.$line] = 'Baris '.$line.': '.implode(' ', $e->validator?->errors()->all() ?? array_merge(...array_values($e->errors())));
                } catch (QueryException $e) {
                    // Do not echo SQL, credentials, or another scope's data in a row error.
                    $errors['file.row_'.$line] = 'Baris '.$line.': data duplikat atau relasi/format tidak sesuai. Periksa ID dan kolom wajib.';
                }
                if (count($errors) >= 25) {
                    break;
                }
            }
            if ($errors) {
                throw ValidationException::withMessages($errors);
            }

            return $counts;
        });
    }

    private function saveRow(string $resource, array $row, ?Model $record): void
    {
        $model = $resource::getModel();
        foreach (['weekdays', 'steps', 'roles', 'member_ids', 'permissions', 'needs'] as $key) {
            if (isset($row[$key])) {
                try {
                    $row[$key] = json_decode($row[$key], true, 32, JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    throw ValidationException::withMessages([$key => 'Kolom '.$key.' harus berupa daftar JSON yang valid.']);
                }
            }
        }
        // Empty password means preserve it; a hash is never imported/exported.
        if ($model === M\User::class && empty($row['password'])) {
            unset($row['password']);
        }
        $baseline = $this->schema->defaults($model);
        if ($record) {
            $baseline = [];
            foreach ($this->schema->columns($model) as $column) {
                if (! in_array($column, ['password', 'roles', 'member_ids', 'permissions'], true)) {
                    $baseline[$column] = $this->exportValue($record, $column);
                }
            }
        }
        $data = array_replace($baseline, $row);
        if ($model === M\User::class && $record) {
            unset($data['password']);
        }
        if ($model === M\User::class && ! empty($row['password'])) {
            $data['password'] = $row['password'];
        }
        $rules = $this->schema->rules($model, $record);
        $data = Validator::make($data, $rules)->validate();
        if ($model === M\Ticket::class) {
            // Tickets have no direct edit operation; triage/assignment/status stay in their audited actions.
            app(TicketService::class)->open(auth()->user(), $data);

            return;
        }
        if ($model === M\G004M008Activity::class) {
            $borrower = isset($data['borrower_id']) ? M\User::findOrFail($data['borrower_id']) : ($record?->user ?? auth()->user());
            unset($data['borrower_id']);
            $service = app(LoanRequestService::class);
            if ($borrower->id === auth()->id()) {
                $service->saveDraft($borrower, $data, $record, validateAvailability: true);
            } else {
                $service->saveDraftOnBehalf(auth()->user(), $borrower, $data, (string) ($data['override_reason'] ?? ''), $record, validateAvailability: true);
            }

            return;
        }
        if ($model === Role::class) {
            abort_unless(auth()->user()->isAdmin(), 403);
            $permissions = $data['permissions'] ?? null;
            unset($data['permissions']);
            $target = $record ?? new $model;
            $target->fill($data + ['guard_name' => 'web'])->save();
            if (array_key_exists('permissions', $row)) {
                $target->syncPermissions($permissions ?? []);
            }

            return;
        }
        $members = $data['member_ids'] ?? null;
        $roles = $data['roles'] ?? null;
        unset($data['member_ids'], $data['roles']);
        if ($record && in_array($model, [M\G006M011ItemReview::class, M\G006M012RoomReview::class, M\G006M020VehicleReview::class], true) && $record->g004_m008_activity_id) {
            throw ValidationException::withMessages(['review' => 'Ulasan pengajuan terkait hanya dapat diubah melalui alur Ulasan Peminjaman. Impor ulasan hanya untuk catatan mandiri.']);
        }
        $guard = app(AssetManagerMutationGuard::class);
        if ($model === M\G002M007Item::class) {
            $data = $guard->item($data, auth()->user(), $record);
            foreach (['quantity', 'tracking_mode'] as $key) {
                if ($record && (string) $data[$key] !== (string) $record->$key) {
                    throw ValidationException::withMessages([$key => 'Jumlah/metode pencatatan tidak dapat diubah lewat edit master. Gunakan Barang Satuan atau pergerakan stok.']);
                }
            }
        }
        if ($model === M\G002M015ItemInstance::class) {
            $data = $guard->instance($data, auth()->user(), $record);
        }
        if (in_array($model, [M\G002M007Item::class, M\G003M006Room::class, M\G008M017Vehicle::class], true)
            && ! empty($data['g001_m001_unit_id']) && empty($data['g002_m003_item_management_id'])) {
            throw ValidationException::withMessages(['g002_m003_item_management_id' => 'Aset ber-unit wajib memiliki kelompok pengelola.']);
        }
        if ($model === M\LoanEvent::class && ! $record) {
            $data += ['created_by' => auth()->id(), 'g001_m001_unit_id' => auth()->user()->g001_m001_unit_id];
        }
        if ($model === M\TicketTeamMember::class && $record && (int) $data['user_id'] !== (int) $record->user_id) {
            throw ValidationException::withMessages(['user_id' => 'Identitas anggota tim tidak dapat dipindahkan.']);
        }
        if ($model === M\User::class) {
            if ($record?->id === auth()->id() && ! $data['is_enabled']) {
                throw ValidationException::withMessages(['is_enabled' => 'Akun sendiri tidak boleh dinonaktifkan.']);
            }
            if (in_array(config('role.sarpras'), $roles ?? $record?->roles->pluck('name')->all() ?? [], true) && empty($data['g001_m001_unit_id'])) {
                throw ValidationException::withMessages(['g001_m001_unit_id' => 'Peran Sarpras wajib memiliki unit.']);
            }
        }
        if ($model === M\WorkOrderWorkflow::class) {
            $service = app(WorkOrderWorkflowService::class);
            $scope = $data['g001_m001_unit_id'] ?? null;
            $service->lockScope($scope);
            if ($record && (string) $scope !== (string) $record->g001_m001_unit_id) {
                throw ValidationException::withMessages(['g001_m001_unit_id' => 'Cakupan workflow tidak dapat dipindahkan.']);
            }
            $data['steps'] = $service->validateSteps($data['steps']);
            if (! $record) {
                if ($model::where('g001_m001_unit_id', $scope)->where('status', 'draft')->exists()) {
                    throw ValidationException::withMessages(['steps' => 'Cakupan ini sudah mempunyai draft workflow.']);
                }
                $data += ['version' => (int) $model::where('g001_m001_unit_id', $scope)->max('version') + 1, 'status' => 'draft', 'created_by' => auth()->id()];
            }
        }
        $target = $record ?? new $model;
        $target->fill($data);
        if ($target instanceof M\RecurringAssetSchedule) {
            abort_unless($resource::canEdit($target), 403);
        }
        $target->save();
        if ($model === M\G002M003ItemManagement::class && array_key_exists('member_ids', $row)) {
            $before = $target->users()->pluck('users.id')->all();
            $target->users()->sync($members ?? []);
            app(AssetManagerRoleSynchronizer::class)->syncUserIds(array_merge($before, $members ?? []));
        }
        if ($model === M\User::class && array_key_exists('roles', $row)) {
            $manual = [config('role.admin'), config('role.fasilitas'), config('role.sarpras')];
            $retained = $target->roles()->whereNotIn('name', $manual)->pluck('name')->all();
            $target->syncRoles(array_merge($retained, $roles ?? []));
        }
    }
}
