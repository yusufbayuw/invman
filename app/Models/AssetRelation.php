<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetRelation extends Model
{
    protected $fillable = ['parent_instance_id', 'child_instance_id', 'relation_type'];

    public function parentInstance()
    {
        return $this->belongsTo(G002M015ItemInstance::class, 'parent_instance_id');
    }

    public function childInstance()
    {
        return $this->belongsTo(G002M015ItemInstance::class, 'child_instance_id');
    }
}
