<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class WorkOrderMaterialOrder extends Model
{
    protected $fillable = [
        'work_order_material_line_id', 'ticket_id', 'quantity', 'status', 'vendor_name',
        'order_reference', 'amount', 'reason', 'requested_by', 'approved_by',
        'ordered_by', 'approved_at', 'ordered_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:3', 'amount' => 'decimal:0',
        'approved_at' => 'datetime', 'ordered_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(fn (): never => throw ValidationException::withMessages([
            'order' => 'Permintaan dan pembelian material tidak dapat dihapus.',
        ]));
    }

    public function line(): BelongsTo { return $this->belongsTo(WorkOrderMaterialLine::class, 'work_order_material_line_id'); }
    public function receipts(): HasMany { return $this->hasMany(WorkOrderMaterialReceipt::class); }
}
