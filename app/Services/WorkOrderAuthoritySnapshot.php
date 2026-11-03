<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketTeamMember;
use App\Models\User;
use App\Models\WorkOrderAssignment;
use App\Models\WorkOrderJobTitle;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/** Current locking reads, including when a caller already opened a repeatable-read snapshot. */
final class WorkOrderAuthoritySnapshot
{
    private function __construct(private Collection $users, private Collection $assignments, private Collection $titles, private Collection $teamMembers) {}

    public static function lock(array $userIds, array $titleIds = []): self
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('Authority locks require a transaction.');
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        sort($ids);
        $users = User::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $assignments = WorkOrderAssignment::query()->whereIn('user_id', $ids)->orderBy('id')->lockForUpdate()->get();
        $titleIds = array_values(array_unique(array_merge(array_filter(array_map('intval', $titleIds)), $assignments->pluck('work_order_job_title_id')->all())));
        sort($titleIds);
        $titles = WorkOrderJobTitle::query()->whereKey($titleIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $teamMembers = TicketTeamMember::query()->whereIn('user_id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('user_id');
        // Filament may sync the role relationship directly; lock the real pivot rows as well.
        $modelType = (new User)->getMorphClass();
        $pivots = DB::table(config('permission.table_names.model_has_roles'))->where('model_type', $modelType)
            ->whereIn('model_id', $ids)->orderBy('model_id')->orderBy('role_id')->lockForUpdate()->get();
        $roleClass = config('permission.models.role');
        $roles = $roleClass::query()->whereKey($pivots->pluck('role_id')->unique()->all())->orderBy('id')->lockForUpdate()->get();
        foreach ($users as $user) {
            $roleIds = $pivots->where('model_id', $user->id)->pluck('role_id')->all();
            $user->setRelation('roles', $roles->whereIn('id', $roleIds)->values());
        }

        return new self($users, $assignments, $titles, $teamMembers);
    }

    public function user(int $id): User
    {
        return $this->users->get($id) ?? throw new \LogicException('Actor not locked.');
    }

    public function teamMember(int $id): ?TicketTeamMember
    {
        return $this->teamMembers->get($id);
    }

    private function inScope(WorkOrderAssignment $assignment, Ticket $ticket): bool
    {
        return $assignment->is_active && $this->titles->get($assignment->work_order_job_title_id)?->is_active
            && (! $assignment->g001_m001_unit_id || (int) $assignment->g001_m001_unit_id === (int) $ticket->g001_m001_unit_id)
            && (! $assignment->g002_m003_item_management_id || (int) $assignment->g002_m003_item_management_id === (int) $ticket->g002_m003_item_management_id);
    }

    public function hasTitle(Ticket $ticket, User $user, int $titleId): bool
    {
        return $this->assignments->contains(fn ($a) => $a->user_id === $user->id && $a->work_order_job_title_id === $titleId && $this->inScope($a, $ticket));
    }

    public function isReporterSupervisor(Ticket $ticket, User $user): bool
    {
        return $this->assignments->contains(fn ($a) => $a->user_id === $ticket->reporter_id && $a->supervisor_user_id === $user->id && $this->inScope($a, $ticket));
    }
}
