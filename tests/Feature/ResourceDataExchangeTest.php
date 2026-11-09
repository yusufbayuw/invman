<?php

namespace Tests\Feature;

use App\Filament\Resources as R;
use App\Models as M;
use App\Services\DataExchange\ResourceDataExchange;
use BezhanSalleh\FilamentShield\Resources\RoleResource;
use BezhanSalleh\FilamentShield\Resources\RoleResource\Pages\ListRoles;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ResourceDataExchangeTest extends TestCase
{
    use RefreshDatabase;

    private array $paths = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $admin = M\User::factory()->create();
        $admin->assignRole(config('role.admin'));
        $this->actingAs($admin);
    }

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            @unlink($path);
        }
        parent::tearDown();
    }

    private function xlsx(array $rows): string
    {
        return $this->tempXlsx($this->xlsxBytes(array_keys($rows[0]), array_map(fn (array $row) => array_values($row), $rows)));
    }

    private function xlsxBytes(array $columns, array $rows): string
    {
        $response = app(\App\Services\DataExchange\XlsxDataFile::class)->download('fixture.xlsx', $columns, $rows);
        ob_start();
        $response->sendContent();

        return (string) ob_get_clean();
    }

    private function tempXlsx(string $bytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'invman-xlsx-test-');
        $this->paths[] = $path;
        file_put_contents($path, $bytes);

        return $path;
    }

    private function runImport(string $resource, array $rows, string $mode = 'upsert'): array
    {
        return app(ResourceDataExchange::class)->import($resource, $this->xlsx($rows), $mode);
    }

    private function download(string $resource, bool $template = false, $query = null): string
    {
        $response = app(ResourceDataExchange::class)->download($resource, $template, $query);
        $this->assertSame(\App\Services\DataExchange\XlsxDataFile::MIME_TYPE, $response->headers->get('Content-Type'));
        $this->assertStringContainsString('.xlsx', $response->headers->get('Content-Disposition'));
        ob_start();
        $response->sendContent();

        return (string) ob_get_clean();
    }

    public function test_create_edit_and_export_round_trip_with_filtered_rows_and_formula_protection(): void
    {
        $this->assertSame(['created' => 2, 'updated' => 0], $this->runImport(R\G001M001UnitResource::class, [
            ['id' => '', 'name' => '=1+2'], ['id' => '', 'name' => 'Unit kedua'],
        ]));
        $first = M\G001M001Unit::where('name', '=1+2')->firstOrFail();
        $xlsx = $this->download(R\G001M001UnitResource::class, query: M\G001M001Unit::whereKey($first->id));
        $this->assertSame("PK\x03\x04", substr($xlsx, 0, 4));
        $path = $this->tempXlsx($xlsx);
        $rows = app(\App\Services\DataExchange\XlsxDataFile::class)->readRows($path, ['id', 'name']);
        $this->assertCount(1, $rows);
        $this->assertSame('=1+2', array_values($rows)[0]['name']);
        $this->assertSame(['created' => 0, 'updated' => 1], app(ResourceDataExchange::class)->import(R\G001M001UnitResource::class, $path, 'update'));
        $this->assertSame('=1+2', $first->fresh()->name);
        $this->assertSame(['created' => 0, 'updated' => 1], $this->runImport(R\G001M001UnitResource::class, [['id' => $first->id, 'name' => 'Diperbarui']], 'update'));
        $this->assertSame('Diperbarui', $first->fresh()->name);
    }

    public function test_invalid_later_row_rolls_back_prior_create_and_reports_row_number(): void
    {
        try {
            $this->runImport(R\G001M001UnitResource::class, [['name' => 'Tidak boleh tersimpan'], ['name' => str_repeat('x', 256)]]);
            $this->fail('Expected invalid row');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Baris 3', implode(' ', array_merge(...array_values($exception->errors()))));
        }
        $this->assertDatabaseMissing('g001_m001_units', ['name' => 'Tidak boleh tersimpan']);
    }

    public function test_real_table_upload_action_and_template_download_are_available(): void
    {
        $upload = UploadedFile::fake()->createWithContent('units.xlsx', $this->xlsxBytes(['id', 'name'], [['', 'Unit via unggah']]));
        Livewire::test(R\G001M001UnitResource\Pages\ListG001M001Units::class)
            ->assertTableActionExists('downloadTemplate')
            ->callTableAction('importData', data: ['file' => $upload, 'mode' => 'create'])
            ->assertHasNoTableActionErrors()->assertNotified('Impor selesai');
        $this->assertDatabaseHas('g001_m001_units', ['name' => 'Unit via unggah']);
        $template = $this->download(R\G001M001UnitResource::class, true);
        $this->assertSame("PK\x03\x04", substr($template, 0, 4));
        $reader = new \OpenSpout\Reader\XLSX\Reader;
        $reader->open($this->tempXlsx($template));
        try {
            $header = [];
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $header = array_map(fn ($cell) => $cell->getValue(), $row->getCells());
                    break 2;
                }
            }
            $this->assertSame(['id', 'name'], $header);
        } finally {
            $reader->close();
        }
    }

    public function test_inventory_import_preserves_observers_and_forbids_counter_edits(): void
    {
        $unit = M\G001M001Unit::create(['name' => 'Unit']);
        $management = M\G002M003ItemManagement::create(['name' => 'Pengelola']);
        $this->runImport(R\G002M007ItemResource::class, [[
            'name' => 'Barang impor', 'code' => 'IMP-001', 'g001_m001_unit_id' => $unit->id,
            'g002_m003_item_management_id' => $management->id, 'quantity' => 2, 'is_borrowable' => 1,
        ]]);
        $item = M\G002M007Item::where('code', 'IMP-001')->firstOrFail();
        $this->assertSame(2, $item->item_instance()->count());
        $this->assertSame(2, (int) $item->available_quantity);
        $this->runImport(R\G002M007ItemResource::class, [['id' => $item->id, 'name' => 'Nama baru']], 'update');
        $this->assertSame('Nama baru', $item->fresh()->name);
        try {
            $this->runImport(R\G002M007ItemResource::class, [['id' => $item->id, 'quantity' => 9]], 'update');
            $this->fail();
        } catch (ValidationException) {
            $this->assertSame(2, (int) $item->fresh()->quantity);
        }
        $this->assertSame(2, $item->item_instance()->count());
    }

    public function test_user_passwords_and_derived_management_roles_are_preserved(): void
    {
        $password = 'Synthetic!Import2026';
        $this->runImport(R\UserResource::class, [['name' => 'Pegawai Impor', 'email' => 'import@example.test', 'password' => $password,
            'roles' => json_encode([config('role.fasilitas')])]]);
        $user = M\User::where('email', 'import@example.test')->firstOrFail();
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertTrue($user->hasRole(config('role.fasilitas')));
        $this->runImport(R\G002M003ItemManagementResource::class, [['name' => 'Pengelola impor', 'member_ids' => json_encode([$user->id])]]);
        $this->assertTrue($user->fresh()->hasRole(config('role.pengelola_barang')));
        $before = $user->password;
        $this->runImport(R\UserResource::class, [['id' => $user->id, 'password' => '', 'name' => 'Nama baru', 'roles' => '[]']], 'update');
        $user->refresh();
        $this->assertSame($before, $user->password);
        $this->assertTrue($user->hasRole(config('role.pengelola_barang')));
        $this->assertFalse($user->hasRole(config('role.fasilitas')));
        $export = $this->download(R\UserResource::class);
        $this->assertStringNotContainsString($before, $export);
        $this->assertStringNotContainsString($password, $export);
    }

    public function test_scoped_actor_cannot_export_or_update_another_units_item(): void
    {
        $own = M\G001M001Unit::create(['name' => 'Own']);
        $other = M\G001M001Unit::create(['name' => 'Other']);
        $item = M\G002M007Item::create(['name' => 'Secret foreign item', 'quantity' => 0, 'g001_m001_unit_id' => $other->id]);
        $user = M\User::factory()->create(['g001_m001_unit_id' => $own->id]);
        $user->assignRole(config('role.sarpras'));
        $this->actingAs($user);
        $this->assertStringNotContainsString('Secret foreign item', $this->download(R\G002M007ItemResource::class));
        $this->expectException(ValidationException::class);
        $this->runImport(R\G002M007ItemResource::class, [['id' => $item->id, 'name' => 'Hacked']], 'update');
    }

    public function test_all_master_configuration_and_inspection_schemas_can_create_and_round_trip(): void
    {
        $unit = M\G001M001Unit::create(['name' => 'Unit']);
        auth()->user()->update(['g001_m001_unit_id' => $unit->id]);
        $management = M\G002M003ItemManagement::create(['name' => 'Pengelola']);
        $building = M\G003M004Building::create(['name' => 'Gedung']);
        $floor = M\G003M005Floor::create(['name' => 'Lantai', 'g003_m004_building_id' => $building->id]);
        $item = M\G002M007Item::create(['name' => 'Barang induk', 'quantity' => 1]);
        $instance = $item->item_instance()->firstOrFail();
        $room = M\G003M006Room::create(['name' => 'Ruangan']);
        $vehicle = M\G008M017Vehicle::create(['name' => 'Kendaraan']);
        $title = M\WorkOrderJobTitle::create(['name' => 'Jabatan']);
        $user = M\User::factory()->create();
        $cases = [
            [R\G002M002ItemTypeResource::class, ['name' => 'Jenis baru', 'description' => 'Teks']],
            [R\G003M004BuildingResource::class, ['name' => 'Gedung baru', 'location' => 'Lokasi']],
            [R\G003M005FloorResource::class, ['name' => 'Lantai baru', 'g003_m004_building_id' => $building->id]],
            [R\G003M006RoomResource::class, ['name' => 'Ruangan baru', 'g003_m005_floor_id' => $floor->id]],
            [R\G002M015ItemInstanceResource::class, ['name' => 'Satuan baru', 'g002_m007_item_id' => $item->id, 'code' => 'NEW-INSTANCE']],
            [R\G008M018DriverResource::class, ['user_id' => $user->id, 'sim_number' => '12345', 'sim_type' => 'A']],
            [R\G008M017VehicleResource::class, ['name' => 'Kendaraan baru', 'license_plate' => 'B 1234 XYZ', 'stnk_date' => '2028-01-01']],
            [R\VehicleAssistantResource::class, ['name' => 'Pendamping baru', 'phone' => '0812345']],
            [R\TicketCategoryResource::class, ['name' => 'Kategori baru', 'slug' => 'kategori-baru']],
            [R\WorkOrderJobTitleResource::class, ['name' => 'Jabatan baru']],
            [R\WorkOrderAssignmentResource::class, ['user_id' => $user->id, 'work_order_job_title_id' => $title->id]],
            [R\WorkOrderWorkflowResource::class, ['steps' => json_encode([['key' => 'supervisor', 'label' => 'Atasan', 'checkpoint' => 'start', 'resolver' => 'reporter_supervisor']])]],
            [R\TicketTeamMemberResource::class, ['user_id' => $user->id, 'team_name' => 'Tim baru', 'team_type' => 'field', 'position' => 'lead']],
            [R\LoanEventResource::class, ['name' => 'Kegiatan baru', 'description' => 'Deskripsi']],
            [R\RecurringAssetScheduleResource::class, ['asset_type' => M\G003M006Room::class, 'asset_id' => $room->id, 'name' => 'Rutin baru', 'weekdays' => '[1,3]', 'start_time' => '09:00', 'end_time' => '10:00', 'valid_from' => '2026-11-01']],
        ];
        foreach ([['G009M022ItemInstanceChecklist', 'g002_m015_item_instance_id', $instance->id], ['G009M023RoomChecklist', 'g003_m006_room_id', $room->id], ['G009M024VehicleChecklist', 'g008_m017_vehicle_id', $vehicle->id]] as [$model, $key, $id]) {
            $cases[] = ['App\\Filament\\Resources\\'.$model.'Resource', [$key => $id, 'user_id' => $user->id, 'date' => '2026-11-08', 'checklist_date' => '2026-11-08 10:00:00', 'is_ok' => 1, 'notes' => 'Baik']];
        }
        foreach ([['G007M013ItemHistory', 'g002_m015_item_instance_id', $instance->id], ['G007M014RoomHistory', 'g003_m006_room_id', $room->id], ['G007M021VehicleHistory', 'g008_m017_vehicle_id', $vehicle->id]] as [$model, $key, $id]) {
            $cases[] = ['App\\Filament\\Resources\\'.$model.'Resource', [$key => $id, 'user_id' => $user->id, 'action' => 'Pemeriksaan mandiri', 'notes' => 'Baik']];
        }
        foreach ([['G006M011ItemReview', 'g002_m015_item_instance_id', $instance->id], ['G006M012RoomReview', 'g003_m006_room_id', $room->id], ['G006M020VehicleReview', 'g008_m017_vehicle_id', $vehicle->id]] as [$model, $key, $id]) {
            $cases[] = ['App\\Filament\\Resources\\'.$model.'Resource', [$key => $id, 'user_id' => $user->id, 'rating' => 5, 'review' => 'Baik']];
        }
        foreach ($cases as [$resource, $row]) {
            $this->assertSame(['created' => 1, 'updated' => 0], $this->runImport($resource, [$row], 'create'), $resource);
            $model = $resource::getModel();
            $record = $model::latest('id')->firstOrFail();
            // UUID models cannot be sorted by creation order; event has no older fixture.
            $xlsx = $this->download($resource, query: $model::whereKey($record->id));
            $path = tempnam(sys_get_temp_dir(), 'invman-master-roundtrip-');
            $this->paths[] = $path;
            file_put_contents($path, $xlsx);
            $this->assertSame(['created' => 0, 'updated' => 1], app(ResourceDataExchange::class)->import($resource, $path, 'update'), $resource);
        }
    }

    public function test_role_actions_keep_shield_resource_and_permission_validation(): void
    {
        Permission::findOrCreate('import-test-permission', 'web');
        $resource = RoleResource::class;
        $this->runImport($resource, [['name' => 'role-import', 'permissions' => '["import-test-permission"]']], 'create');
        $role = Role::findByName('role-import');
        $this->assertTrue($role->hasPermissionTo('import-test-permission'));
        $this->runImport($resource, [['id' => $role->id, 'permissions' => '[]']], 'update');
        $this->assertSame(0, $role->fresh()->permissions()->count());
        Livewire::test(ListRoles::class)
            ->assertTableActionExists('exportData')->assertTableActionExists('importData')->assertTableActionExists('downloadTemplate');
    }

    public function test_loan_import_creates_and_edits_complete_drafts_and_checks_routine_conflicts(): void
    {
        $unit = M\G001M001Unit::create(['name' => 'Unit pinjam']);
        $user = M\User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        $user->assignRole(config('role.sarpras'));
        $item = M\G002M007Item::create(['name' => 'Proyektor', 'is_borrowable' => true, 'quantity' => 3]);
        $room = M\G003M006Room::create(['name' => 'Ruang', 'is_borrowable' => true]);
        $this->actingAs($user);
        $day = now()->addDays(14)->next('Monday')->startOfDay();
        $row = ['name' => 'Pengajuan impor', 'description' => 'Kebutuhan dari impor CSV',
            'start_time' => $day->copy()->setTime(10, 0)->toDateTimeString(), 'end_time' => $day->copy()->setTime(12, 0)->toDateTimeString(),
            'needs' => json_encode([['type' => 'item', 'item_id' => $item->id, 'quantity' => 2], ['type' => 'room', 'room_id' => $room->id]]),
            'status' => 'approved'];
        $this->runImport(R\G004M008ActivityResource::class, [$row], 'create');
        $draft = M\G004M008Activity::where('name', 'Pengajuan impor')->firstOrFail();
        $this->assertSame('draft', $draft->status);
        $this->assertSame(2, (int) $draft->item_reservation()->first()->quantity);
        $this->assertSame(1, $draft->room_reservation()->count());
        $this->runImport(R\G004M008ActivityResource::class, [['id' => $draft->id, 'needs' => json_encode([['type' => 'item', 'item_id' => $item->id, 'quantity' => 1]])]], 'update');
        $this->assertSame(0, $draft->room_reservation()->count());
        $this->assertSame(1, (int) $draft->item_reservation()->first()->quantity);
        $item->recurringSchedules()->create(['name' => 'Rutinitas', 'weekdays' => [1], 'start_time' => '09:00', 'end_time' => '11:00', 'valid_from' => today()->toDateString()]);
        try {
            $this->runImport(R\G004M008ActivityResource::class, [$row], 'create');
            $this->fail('Routine must reject imported booking');
        } catch (ValidationException) {
            $this->assertSame(1, M\G004M008Activity::count());
        }
    }

    public function test_ticket_import_initializes_workflow_and_does_not_allow_status_overwrite(): void
    {
        $category = M\TicketCategory::create(['name' => 'Perawatan impor', 'slug' => 'perawatan-impor', 'is_active' => true, 'reporting_enabled' => true]);
        $this->runImport(R\TicketResource::class, [['ticket_category_id' => $category->id, 'title' => 'Lampu rusak impor',
            'description' => 'Lampu di ruangan tidak dapat dinyalakan', 'status' => 'closed']], 'create');
        $ticket = M\Ticket::where('title', 'Lampu rusak impor')->firstOrFail();
        $this->assertSame('open', $ticket->status);
        $this->assertNotNull($ticket->maintenance);
        $this->assertSame(1, $ticket->events()->where('action', 'created')->count());
    }

    public function test_excel_preserves_semicolons_newlines_and_leading_zero_text(): void
    {
        $this->assertSame(['created' => 1, 'updated' => 0], $this->runImport(R\G002M002ItemTypeResource::class, [
            ['id' => '', 'name' => 'Jenis; 00123', 'description' => "Baris pertama\nBaris kedua"],
        ], 'create'));
        $type = M\G002M002ItemType::where('name', 'Jenis; 00123')->firstOrFail();
        $this->assertSame("Baris pertama\nBaris kedua", $type->description);
        $xlsx = $this->download(R\G002M002ItemTypeResource::class, query: M\G002M002ItemType::whereKey($type->id));
        $rows = app(\App\Services\DataExchange\XlsxDataFile::class)->readRows($this->tempXlsx($xlsx), ['id', 'name', 'description']);
        $this->assertSame('Jenis; 00123', array_values($rows)[0]['name']);
        $this->assertSame("Baris pertama\nBaris kedua", array_values($rows)[0]['description']);
    }

    public function test_csv_upload_is_rejected_without_database_changes(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'invman-invalid-csv-');
        $this->paths[] = $path;
        file_put_contents($path, "id,name\n,CSV tidak boleh masuk\n");
        $this->expectException(ValidationException::class);
        try {
            app(ResourceDataExchange::class)->import(R\G001M001UnitResource::class, $path, 'create');
        } finally {
            $this->assertDatabaseMissing('g001_m001_units', ['name' => 'CSV tidak boleh masuk']);
        }
    }

    public function test_unknown_headers_duplicate_ids_and_modes_do_not_write(): void
    {
        $unit = M\G001M001Unit::create(['name' => 'Unchanged']);
        foreach ([
            [[['id' => $unit->id, 'name' => 'Changed', 'is_admin' => 1]], 'update'],
            [[['id' => $unit->id, 'name' => 'Changed'], ['id' => $unit->id, 'name' => 'Changed again']], 'update'],
            [[['id' => $unit->id, 'name' => 'Changed']], 'create'],
            [[['id' => '', 'name' => 'Created']], 'update'],
        ] as [$rows, $mode]) {
            try {
                $this->runImport(R\G001M001UnitResource::class, $rows, $mode);
                $this->fail();
            } catch (ValidationException) {
                $this->assertSame('Unchanged', $unit->fresh()->name);
            }
        }
    }
}
