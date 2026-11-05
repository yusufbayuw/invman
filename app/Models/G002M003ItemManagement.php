<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class G002M003ItemManagement extends Model
{
    /** @var list<int> */
    private array $previousMemberIds = [];

    protected static function booted(): void
    {
        // FK cascades bypass pivot model events. Reconcile former members when
        // an empty/unused management group is deleted through Eloquent.
        static::deleting(function (self $management): void {
            $management->previousMemberIds = $management->users()
                ->pluck('users.id')->map(fn ($id): int => (int) $id)->all();
        });

        static::deleted(function (self $management): void {
            app(\App\Services\AssetManagerRoleSynchronizer::class)
                ->syncUserIds($management->previousMemberIds);
        });
    }

    /** Explicit, audited mass-assignment allowlist. */
    protected $fillable = [
        'name',
        'description',
    ];

    public function item(): HasMany
    {
        return $this->hasMany(G002M007Item::class, 'g002_m003_item_management_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'g002_m003_item_management_user')
            ->using(ItemManagementMembership::class)
            ->withTimestamps();
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(G003M006Room::class, 'g002_m003_item_management_id');
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(G008M017Vehicle::class, 'g002_m003_item_management_id');
    }
}
