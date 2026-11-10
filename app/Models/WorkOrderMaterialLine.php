<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class WorkOrderMaterialLine extends Model
{
    protected $fillable = [
        'ticket_id', 'name', 'unit', 'required_quantity', 'stock_allocated_quantity',
        'issued_quantity', 'created_by', 'stock_confirmed_by', 'stock_confirmed_at',
    ];

    protected $casts = [
        'required_quantity' => 'decimal:3', 'stock_allocated_quantity' => 'decimal:3',
        'issued_quantity' => 'decimal:3', 'stock_confirmed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(fn (): never => throw ValidationException::withMessages([
            'material' => 'Riwayat kebutuhan material tidak dapat dihapus.',
        ]));
    }

    public function ticket(): BelongsTo { return $this->belongsTo(Ticket::class); }
    public function orders(): HasMany { return $this->hasMany(WorkOrderMaterialOrder::class); }
}
