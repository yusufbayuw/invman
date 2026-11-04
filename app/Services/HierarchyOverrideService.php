<?php

namespace App\Services;

use App\Models\OperationalOverride;
use App\Models\TicketTeamMember;
use App\Models\User;
use App\Models\WorkOrderAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Top-down exception authority: one login per employee; never impersonate the subordinate.
 * Admin may replace everyone; Facility may replace lower tiers; other superiors need an
 * ACTIVE and in-scope organizational/team reporting path to the original responsible person.
 */
class HierarchyOverrideService
{
    public function can(User $actor, ?User $subject, ?int $unitId = null, ?int $managementId = null): bool
    {
        // The actor must be the REAL authenticated employee; never grant authority
        // from a forged User argument or impersonate a locked/disabled subordinate.
        if (! auth()->check() || (int) auth()->id() !== (int) $actor->id || ! $actor->is_enabled) {
            return false;
        }

        if ($actor->isAdmin()) {
            return true;
        }

        if ($actor->isFacility()) {
            // Facility cannot overrule an admin or another peer facility. Self-override
            // is allowed, but still creates an explicit and visible self-approval audit.
            return ! $subject || $subject->id === $actor->id || ! $subject->isFacility();
        }

        if (! $subject || $subject->id === $actor->id || $subject->isFacility()) {
            return false;
        }

        if ($unitId && $actor->g001_m001_unit_id && (int) $actor->g001_m001_unit_id !== $unitId) {
            return false;
        }

        // A subordinate's team leader can act within a defined team hierarchy.
        if (in_array($subject->id, TicketTeamMember::descendantsOf($actor), true)) {
            return true;
        }

        $edges = WorkOrderAssignment::query()->with('jobTitle')
            ->where('is_active', true)
            ->where(function ($q) use ($unitId): void {
                $q->whereNull('g001_m001_unit_id');
                if ($unitId) {
                    $q->orWhere('g001_m001_unit_id', $unitId);
                }
            })
            ->where(function ($q) use ($managementId): void {
                $q->whereNull('g002_m003_item_management_id');
                if ($managementId) {
                    $q->orWhere('g002_m003_item_management_id', $managementId);
                }
            })
            ->whereNotNull('supervisor_user_id')
            ->get();

        $children = [];
        foreach ($edges as $edge) {
            if ($edge->jobTitle?->is_active) {
                $children[(int) $edge->supervisor_user_id][] = (int) $edge->user_id;
            }
        }
        $visited = [$actor->id => true];
        $queue = [(int) $actor->id];
        while ($queue !== []) {
            $parent = array_shift($queue);
            foreach ($children[$parent] ?? [] as $child) {
                if (isset($visited[$child])) {
                    continue;
                }
                if ($child === (int) $subject->id) {
                    return true;
                }
                $visited[$child] = true;
                $queue[] = $child;
            }
        }

        return false;
    }

    public function authorize(User $actor, ?User $subject, ?int $unitId = null, ?int $managementId = null): void
    {
        if (! $this->can($actor, $subject, $unitId, $managementId)) {
            throw ValidationException::withMessages([
                'override' => 'Tidak berwenang menggantikan peran tersebut dalam cakupan unit/tim ini.',
            ]);
        }
    }

    public function validateReason(string $reason): string
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 2000) {
            throw ValidationException::withMessages([
                'reason' => 'Alasan tindakan hierarki wajib diisi (10–2.000 karakter).',
            ]);
        }
        return $reason;
    }

    /**
     * Must run in the SAME DB transaction as the business mutation.
     * Corrections append new events; audit records cannot be edited or removed.
     */
    public function record(
        User $actor, ?User $subject, string $entityType, string $entityId,
        string $action, string $reason, ?string $from = null, ?string $to = null,
        array $details = [],
    ): OperationalOverride {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('Audit override harus berada dalam transaksi bisnis.');
        }
        $reason = $this->validateReason($reason);
        if (! auth()->check() || (int) auth()->id() !== (int) $actor->id) {
            throw new \LogicException('Pelaku audit harus sesuai akun pegawai yang login.');
        }
        $request = app()->runningInConsole() ? null : request();
        return OperationalOverride::query()->create([
            'actor_id' => $actor->id,
            'subject_user_id' => $subject?->id,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason,
            'details' => $details,
            'ip_address' => $request?->ip(),
            'user_agent' => mb_substr((string) $request?->userAgent(), 0, 512) ?: null,
        ]);
    }
}
