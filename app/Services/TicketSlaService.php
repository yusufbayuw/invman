<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketMaintenanceCase;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class TicketSlaService
{
    public function deadlines(Ticket $ticket, ?string $priority = null): array
    {
        $targets = config('ticket_sla.targets.'.($priority ?: $ticket->priority),
            config('ticket_sla.targets.normal'));
        $start = $ticket->created_at ?: now();

        return [
            'response_due_at' => $this->addWorkingMinutes($start, (int) $targets['response_minutes']),
            'resolution_due_at' => $this->addWorkingMinutes($start, (int) $targets['resolution_minutes']),
        ];
    }

    public function addWorkingMinutes(Carbon|string $value, int $minutes): Carbon
    {
        $zone = (string) config('ticket_sla.timezone', 'Asia/Jakarta');
        $point = ($value instanceof Carbon ? $value->copy() : Carbon::parse($value))->setTimezone($zone);
        $weekdays = config('ticket_sla.working_weekdays', [1, 2, 3, 4, 5]);
        $holidays = config('ticket_sla.holidays', []);
        [$startH, $startM] = array_map('intval', explode(':', config('ticket_sla.workday_start', '08:00')));
        [$endH, $endM] = array_map('intval', explode(':', config('ticket_sla.workday_end', '17:00')));
        $remaining = max(0, $minutes);

        // Explicit safety bound against a misconfigured business calendar.
        for ($i = 0; $i < 3660; $i++) {
            $dayStart = $point->copy()->setTime($startH, $startM);
            $dayEnd = $point->copy()->setTime($endH, $endM);
            if ($dayEnd->lessThanOrEqualTo($dayStart)) {
                throw new \LogicException('Invalid ticket SLA working hours.');
            }
            if (in_array($point->dayOfWeekIso, $weekdays, true)
                && ! in_array($point->toDateString(), $holidays, true)) {
                if ($point->lessThan($dayStart)) {
                    $point = $dayStart;
                }
                if ($point->lessThan($dayEnd)) {
                    $available = (int) $point->diffInMinutes($dayEnd);
                    if ($remaining <= $available) {
                        return $point->addMinutes($remaining);
                    }
                    $remaining -= $available;
                }
            }
            $point = $point->addDay()->startOfDay();
        }
        throw new \LogicException('Ticket SLA calendar contains no usable working hours.');
    }

    /**
     * Idempotent cron scan: only commit one escalation per due milestone.
     * Repeated scheduler executions must not send repeated reminders.
     */
    public function escalateOverdue(): int
    {
        $count = 0;
        foreach (['response', 'resolution'] as $kind) {
            $due = $kind.'_due_at';
            $flag = $kind.'_escalated_at';
            TicketMaintenanceCase::query()
                ->whereNull($flag)
                ->whereNotNull($due)
                ->where($due, '<', now())
                ->whereHas('ticket', fn ($q) => $q->whereNotIn('status', ['closed', 'cancelled']))
                ->when($kind === 'response', fn ($q) => $q->whereNull('responded_at'))
                ->when($kind === 'resolution', fn ($q) => $q->whereNull('work_finished_at'))
                ->orderBy($due)->chunkById(50, function ($cases) use (&$count, $kind, $flag, $due): void {
                    foreach ($cases as $case) {
                        DB::transaction(function () use ($case, $kind, $flag, $due, &$count): void {
                            $record = TicketMaintenanceCase::query()->lockForUpdate()->find($case->ticket_id);
                            $ticket = $record?->ticket;
                            if (! $record || $record->{$flag} || ! $ticket
                                || in_array($ticket->status, ['closed', 'cancelled'], true)
                                || $record->{$due}?->isFuture()
                                || ($kind === 'response' && $record->responded_at)
                                || ($kind === 'resolution' && $record->work_finished_at)) {
                                return;
                            }
                            $record->forceFill([$flag => now()])->save();
                            $ticket->events()->create([
                                'actor_id' => null,
                                'action' => 'sla_'.$kind.'_breached',
                                'from_value' => null, 'to_value' => (string) $record->{$due},
                            ]);
                            $count++;
                            DB::afterCommit(function () use ($ticket, $kind): void {
                                $users = \App\Models\User::role(config('role.fasilitas'))->get()
                                    ->merge(\App\Models\User::role(config('role.admin'))->get())
                                    ->merge($ticket->management?->users ?? collect())
                                    ->when($ticket->assignee, fn ($c) => $c->push($ticket->assignee))
                                    ->unique('id');
                                if ($users->isNotEmpty()) {
                                    Notification::make()->title('SLA tiket terlewati: '.$ticket->number)
                                        ->body($kind === 'response' ? 'Batas respons terlampaui' : 'Batas penyelesaian terlampaui')
                                        ->danger()->sendToDatabase($users);
                                }
                            });
                        }, 3);
                    }
                }, 'ticket_id');
        }
        return $count;
    }
}
