<?php

namespace App\Models;

use App\Services\AssetManagerRoleSynchronizer;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Covers attach/detach/sync from both User and Pengelola Barang, including
 * seeders and service code that use Eloquent's relationship APIs.
 */
class ItemManagementMembership extends Pivot
{
    protected $table = 'g002_m003_item_management_user';

    /** Only the two relationship keys may be filled; IDs, timestamps and flags are protected. */
    protected $fillable = ['user_id', 'g002_m003_item_management_id'];

    public $incrementing = true;

    public $timestamps = true;

    protected static function booted(): void
    {
        static::created(fn (self $membership) => app(AssetManagerRoleSynchronizer::class)
            ->syncUserId((int) $membership->user_id));

        static::deleted(fn (self $membership) => app(AssetManagerRoleSynchronizer::class)
            ->syncUserId((int) $membership->user_id));
    }
}
