<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Models\G005M009ItemReservation;
use App\Models\G005M010RoomReservation;
use App\Models\G005M019VehicleReservation;
use App\Models\RecurringAssetSchedule;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class ModuleManager
{
    public function definitions(): array
    {
        return config('modules', []);
    }

    public function canManage(?User $actor): bool
    {
        return (bool) ($actor?->is_enabled && $actor->hasRole(config('filament-shield.super_admin.name', 'admin')));
    }

    private function key(string $module): string
    {
        if (! array_key_exists($module, $this->definitions())) {
            throw new \InvalidArgumentException('Unknown module: '.$module);
        }

        return $module === 'chat' ? 'chat_enabled' : 'module_'.$module.'_enabled';
    }

    // No process-wide cache: changes must apply to subsequent Livewire and worker requests.
    public function requested(string $module): bool
    {
        $key = $this->key($module);

        // Existing migrations can run before the settings table is created.
        return ! Schema::hasTable('application_settings')
            || DB::table('application_settings')->where('key', $key)->value('value') !== '0';
    }

    public function enabled(string $module): bool
    {
        if (! $this->requested($module)) {
            return false;
        }

        $definition = $this->definitions()[$module];
        $requires = $definition['requires'] ?? [];
        if (isset($definition['parent'])) {
            $requires[] = $definition['parent'];
        }

        foreach ($requires as $dependency) {
            if (! $this->enabled($dependency)) {
                return false;
            }
        }

        return true;
    }

    public function loanTypeOptions(): array
    {
        return array_filter([
            'item' => 'Barang / Peralatan',
            'room' => 'Ruangan / Tempat',
            'vehicle' => 'Kendaraan',
        ], fn (string $type): bool => $this->loanTypeEnabled($type), ARRAY_FILTER_USE_KEY);
    }

    public function publicLoanTypeVisible(string $type): bool
    {
        return $this->enabled('public_schedule') && $this->state('loans_'.$type) !== 'inactive';
    }

    public function anyLoanTypeEnabled(): bool
    {
        return $this->loanTypeEnabled('item') || $this->loanTypeEnabled('room') || $this->loanTypeEnabled('vehicle');
    }

    public function loanTypeEnabled(string $type): bool
    {
        return $this->enabled(match ($type) {
            'item' => 'loans_item',
            'room' => 'loans_room',
            'vehicle' => 'loans_vehicle',
            default => throw new \InvalidArgumentException('Unknown asset type: '.$type),
        });
    }

    public function assertAdvanced(Ticket $ticket): void
    {
        if (! $ticket->usesAdvancedWorkflow()) {
            throw ValidationException::withMessages(['module' => 'Tiket ini menggunakan penanganan sederhana.']);
        }
    }

    public function assertEnabled(string $module): void
    {
        if (! $this->enabled($module)) {
            throw ValidationException::withMessages(['module' => $this->definitions()[$module]['label'].' sedang dinonaktifkan.']);
        }
    }

    public function assertLoanType(string $type): void
    {
        $this->assertEnabled('loans_'.$type);
    }

    public function configuration(): array
    {
        $settings = [];
        foreach ($this->definitions() as $module => $definition) {
            $settings[$module] = $this->requested($module);
        }

        return $settings;
    }

    public function save(User $actor, array $settings): void
    {
        abort_unless($this->canManage($actor), 403);
        foreach ($settings as $module => $enabled) {
            $this->key($module);
            if (! is_bool($enabled)) {
                throw ValidationException::withMessages(['module' => 'Status modul harus berupa boolean.']);
            }
        }

        DB::transaction(function () use ($actor, $settings): void {
            // Serialize administrator changes without changing the existing Chat storage key.
            DB::table('application_settings')->insertOrIgnore(['key' => 'module_settings_lock', 'value' => '1']);
            DB::table('application_settings')->where('key', 'module_settings_lock')->lockForUpdate()->first();
            abort_unless($this->canManage(User::query()->find($actor->id)), 403);

            foreach ($settings as $module => $enabled) {
                $previous = $this->requested($module);
                if ($previous === $enabled) {
                    continue;
                }

                DB::table('application_settings')->updateOrInsert(['key' => $this->key($module)], ['value' => $enabled ? '1' : '0']);
                DB::table('module_setting_audits')->insert([
                    'actor_id' => $actor->id,
                    'module' => $module,
                    'previous_enabled' => $previous,
                    'enabled' => $enabled,
                    'created_at' => now(),
                ]);
            }
        }, 3);
    }

    public function pending(string $module): int
    {
        $loans = fn (string $model): int => $model::query()->whereIn('status', ReservationStatus::blockingValues())->count();

        return match ($module) {
            'inventory', 'loans_item' => $loans(G005M009ItemReservation::class),
            'rooms', 'loans_room' => $loans(G005M010RoomReservation::class),
            'vehicles', 'loans_vehicle' => $loans(G005M019VehicleReservation::class),
            'loans' => $loans(G005M009ItemReservation::class) + $loans(G005M010RoomReservation::class) + $loans(G005M019VehicleReservation::class),
            'tickets' => Ticket::query()->where(fn ($query) => $query->whereNotIn('status', ['closed', 'cancelled'])
                ->orWhereHas('maintenance', fn ($hold) => $hold->whereNotNull('asset_hold_at')->whereNull('asset_released_at')))->count(),
            'work_orders' => Ticket::query()->whereNotIn('status', ['closed', 'cancelled'])
                ->where(fn ($query) => $query->whereNull('workflow_mode')->orWhere('workflow_mode', 'advanced'))->count(),
            'recurring' => RecurringAssetSchedule::query()->where('is_active', true)
                ->where(fn ($query) => $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', today()))->count(),
            default => 0,
        };
    }

    public function state(string $module): string
    {
        return $this->enabled($module) ? 'active' : ($this->pending($module) > 0 ? 'draining' : 'inactive');
    }
}
