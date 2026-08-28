<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Filament\Panel;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable implements FilamentUser, HasAvatar
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'g001_m001_unit_id',
        'email',
        'email_verified_at',
        'username',
        'password',
        'avatar',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getFilamentAvatarUrl(): ?string
    {
        if (blank($this->avatar) || $this->avatar === config('chatify.user_avatar.default')) {
            return asset('images/app/fav.png');
        }

        return Storage::disk(config('chatify.storage_disk_name'))->url(
            config('chatify.user_avatar.folder') . '/' . $this->avatar,
        );
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function isAdmin(): bool
    {
        return $this->hasAnyRole([config('role.admin'), 'super_admin']);
    }

    public function isFacility(): bool
    {
        return $this->isAdmin() || $this->hasRole(config('role.fasilitas'));
    }

    public function isSarpras(): bool
    {
        return $this->hasAnyRole([config('role.sarpras'), 'unit']);
    }

    public function belongsToUnit(?int $unitId): bool
    {
        return $this->isSarpras()
            && $this->g001_m001_unit_id !== null
            && $this->g001_m001_unit_id === $unitId;
    }

    public function itemManagements(): BelongsToMany
    {
        return $this->belongsToMany(G002M003ItemManagement::class, 'g002_m003_item_management_user')
            ->withTimestamps();
    }

    public function isAssetManager(): bool
    {
        return $this->itemManagements()->exists();
    }

    public function managesReservation(Model $reservation): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $managementId = match (true) {
            $reservation instanceof G005M009ItemReservation => $reservation->item?->g002_m003_item_management_id,
            $reservation instanceof G005M010RoomReservation => $reservation->room?->g002_m003_item_management_id,
            $reservation instanceof G005M019VehicleReservation => $reservation->vehicle?->g002_m003_item_management_id,
            default => null,
        };

        return filled($managementId)
            && $this->itemManagements()->whereKey($managementId)->exists();
    }

    public function managesActivity(G004M008Activity $activity): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $managementIds = $this->itemManagements()->pluck('g002_m003_item_management.id');

        return $managementIds->isNotEmpty() && (
            $activity->item_reservation()->whereHas('item', fn ($query) => $query->whereIn('g002_m003_item_management_id', $managementIds))->exists()
            || $activity->room_reservation()->whereHas('room', fn ($query) => $query->whereIn('g002_m003_item_management_id', $managementIds))->exists()
            || $activity->vehicle_reservation()->whereHas('vehicle', fn ($query) => $query->whereIn('g002_m003_item_management_id', $managementIds))->exists()
        );
    }

    public function activity(): HasMany
    {
        return $this->hasMany(G004M008Activity::class, 'user_id');
    }
    public function item_history(): HasMany
    {
        return $this->hasMany(G007M013ItemHistory::class, 'user_id');
    }
    public function room_history(): HasMany
    {
        return $this->hasMany(G007M014RoomHistory::class, 'user_id');
    }
    public function vehicle_review(): HasMany
    {
        return $this->hasMany(G006M020VehicleReview::class, 'user_id');
    }
    public function vehicle_history(): HasMany
    {
        return $this->hasMany(G007M021VehicleHistory::class, 'user_id');
    }
    public function item_instance_checklist(): HasMany
    {
        return $this->hasMany(G009M022ItemInstanceChecklist::class, 'user_id');
    }
    public function unit(): BelongsTo
    {
        return $this->belongsTo(G001M001Unit::class, 'g001_m001_unit_id');
    }
}
