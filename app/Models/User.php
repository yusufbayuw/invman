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
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

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
        'email',
        'username',
        'password',
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
        return asset('storage/users-avatar/' . $this->avatar);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
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
