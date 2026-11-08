<?php

namespace App\Providers;

use App\Filament\Actions\ResourceDataActions;
use App\Licensing\EmbeddedLicensePublicKey;
use App\Licensing\LicenseManager;
use App\Licensing\LicensePublicKeyProvider;
use App\Services\ChatifyMessenger;
use BezhanSalleh\FilamentShield\Resources\RoleResource;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\Column;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind('ChatifyMessenger', ChatifyMessenger::class);
        $this->app->singleton(LicensePublicKeyProvider::class, EmbeddedLicensePublicKey::class);
        $this->app->singleton(LicenseManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $labels = [
            'name' => 'Nama', 'description' => 'Deskripsi', 'code' => 'Kode', 'quantity' => 'Jumlah',
            'available_quantity' => 'Jumlah tersedia', 'status' => 'Status', 'created_at' => 'Dibuat pada',
            'updated_at' => 'Diperbarui pada', 'email' => 'Email', 'username' => 'Username',
            'start_time' => 'Waktu mulai', 'end_time' => 'Waktu selesai', 'notes' => 'Catatan',
            'photo' => 'Foto', 'date' => 'Tanggal', 'capacity' => 'Kapasitas', 'location' => 'Lokasi',
            'g001_m001_unit_id' => 'Unit', 'g002_m003_item_management_id' => 'Pengelola',
            'g002_m007_item_id' => 'Barang', 'g002_m015_item_instance_id' => 'Barang Satuan',
            'g003_m004_building_id' => 'Gedung', 'g003_m005_floor_id' => 'Lantai',
            'g003_m006_room_id' => 'Ruangan', 'g008_m017_vehicle_id' => 'Kendaraan',
            'user_id' => 'Pengguna', 'tracking_mode' => 'Metode pencatatan', 'is_borrowable' => 'Dapat dipinjam',
            'is_available' => 'Tersedia', 'rating' => 'Penilaian', 'review' => 'Ulasan', 'action' => 'Tindakan',
            'checklist_date' => 'Waktu pemeriksaan', 'is_ok' => 'Kondisi baik', 'license_plate' => 'Nomor polisi',
            'stnk_date' => 'Masa berlaku STNK', 'kir_date' => 'Masa berlaku KIR', 'email_verified_at' => 'Email diverifikasi pada',
        ];
        Field::configureUsing(function (Field $field) use ($labels): void {
            if (isset($labels[$field->getName()])) {
                $field->label($labels[$field->getName()]);
            }
        });
        Column::configureUsing(function (Column $column) use ($labels): void {
            if (isset($labels[$column->getName()])) {
                $column->label($labels[$column->getName()]);
            }
        });
        // Apply the same searchable, bounded preload behavior to resource,
        // relation, action, filter and vendor-provided selects.
        Select::configureUsing(function (Select $select): void {
            $select->searchable()->preload()->native(false)->optionsLimit(50)
                ->options(fn (Select $component): array => $component->getSearchResults(''));
        });
        SelectFilter::configureUsing(fn (SelectFilter $filter) => $filter->searchable()->preload()->native(false)->optionsLimit(50));
        // Add transfer actions to Shield's own role table without replacing its resource.
        Table::configureUsing(function (Table $table): void {
            $livewire = $table->getLivewire();
            if (method_exists($livewire, 'getResource') && $livewire::getResource() === RoleResource::class) {
                $table->headerActions(ResourceDataActions::make($livewire::getResource()));
            }
        });
        // Never disable Eloquent mass-assignment safeguards globally.
        Model::preventSilentlyDiscardingAttributes(true);
        if (config('app.env') === 'production' && parse_url((string) config('app.url'), PHP_URL_SCHEME) === 'https') {
            URL::forceScheme('https');
        }
    }
}
