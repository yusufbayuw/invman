<?php

namespace App\Models\Concerns;

use Illuminate\Validation\ValidationException;

/**
 * Security invariant: append-only evidence is never editable/deletable by Eloquent.
 * Corrections must be recorded as new entries, preserving the original.
 */
trait HasImmutableAuditRecord
{
    protected static function bootHasImmutableAuditRecord(): void
    {
        static::updating(function (): never {
            throw ValidationException::withMessages([
                'audit' => 'Catatan audit bersifat permanen. Catat koreksi sebagai entri baru.',
            ]);
        });

        static::deleting(function (): never {
            throw ValidationException::withMessages([
                'audit' => 'Catatan audit tidak boleh dihapus.',
            ]);
        });
    }
}
