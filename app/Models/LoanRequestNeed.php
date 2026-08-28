<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanRequestNeed extends Model
{
    /**
     * This model represents the read-only union used by the Peminjaman Saya page.
     */
    protected $table = 'loan_request_needs';

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'returned_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
