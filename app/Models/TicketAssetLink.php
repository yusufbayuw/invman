<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketAssetLink extends Model
{
    protected $fillable = ['ticket_id', 'asset_type', 'asset_id'];
    public function ticket(): BelongsTo { return $this->belongsTo(Ticket::class); }
}
