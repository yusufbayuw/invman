<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetBulkMovement extends Model
{
    protected $fillable = ['item_id', 'delta', 'reason', 'performed_by'];
    public function item() { return $this->belongsTo(G002M007Item::class, 'item_id'); }
}
