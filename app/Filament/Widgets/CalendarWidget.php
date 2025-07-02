<?php

namespace App\Filament\Widgets;

use App\Models\G004M008Activity;
use Illuminate\Database\Eloquent\Model;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;
 
class CalendarWidget extends FullCalendarWidget
{
    public Model | string | null $model = G004M008Activity::class;
 
    public function fetchEvents(array $fetchInfo): array
    {
        return G004M008Activity::where('start_time', '>=', $fetchInfo['start'])
            ->where('end_time', '<=', $fetchInfo['end'])
            ->get()
            ->map(function (G004M008Activity $task) {
                return [
                    'id'    => $task->id,
                    'title' => $task->name,
                    'start' => $task->start_time,
                    'end'   => $task->end_time,
                ];
            })
            ->toArray();
    }
 
    public static function canView(): bool
    {
        return true;
    }
}