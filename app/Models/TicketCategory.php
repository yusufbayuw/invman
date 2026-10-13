<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketCategory extends Model
{
    protected $fillable = ['name', 'slug', 'is_active', 'reporting_enabled'];
    protected $casts = ['is_active' => 'boolean', 'reporting_enabled' => 'boolean'];

    public function tickets(): HasMany { return $this->hasMany(Ticket::class); }
}
