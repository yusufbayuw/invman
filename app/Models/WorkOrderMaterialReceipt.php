<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class WorkOrderMaterialReceipt extends Model
{
    protected $fillable = [
        'work_order_material_order_id', 'quantity', 'accepted_quantity', 'status',
        'reference', 'inspection_notes', 'received_by', 'inspected_by', 'inspected_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:3', 'accepted_quantity' => 'decimal:3',
        'inspected_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(fn (): never => throw ValidationException::withMessages([
            'receipt' => 'Riwayat penerimaan dan pemeriksaan tidak dapat dihapus.',
        ]));
    }

    public function order(): BelongsTo { return $this->belongsTo(WorkOrderMaterialOrder::class, 'work_order_material_order_id'); }
}
