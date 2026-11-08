<?php

namespace App\Models;

use App\Services\RecurringAssetScheduleService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RecurringAssetSchedule extends Model
{
    protected $fillable = ['asset_type', 'asset_id', 'name', 'weekdays', 'start_time', 'end_time', 'valid_from', 'valid_until', 'is_active'];

    protected $attributes = ['is_active' => true];

    protected $casts = ['weekdays' => 'array', 'valid_from' => 'date', 'valid_until' => 'date', 'is_active' => 'boolean'];

    public function asset(): MorphTo
    {
        return $this->morphTo();
    }

    public function save(array $options = [])
    {
        return DB::transaction(function () use ($options) {
            Validator::make($this->attributesToArray(), [
                'asset_type' => ['required', Rule::in(RecurringAssetScheduleService::assetModels())],
                'asset_id' => ['required', 'integer', 'min:1'],
                'name' => ['required', 'string', 'max:255'],
                'weekdays' => ['required', 'array', 'min:1', 'max:7'],
                'weekdays.*' => ['required', 'integer', 'between:1,7', 'distinct'],
                'start_time' => ['required', 'date_format:H:i:s'],
                'end_time' => ['required', 'date_format:H:i:s'],
                'valid_from' => ['required', 'date'],
                'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            ])->validate();
            if ($this->exists && ($this->isDirty('asset_type') || $this->isDirty('asset_id'))) {
                throw ValidationException::withMessages(['asset_id' => 'Aset jadwal tidak dapat dipindahkan. Buat jadwal baru.']);
            }
            $asset = $this->asset_type::query()->lockForUpdate()->findOrFail($this->asset_id);
            if ($this->is_active) {
                app(RecurringAssetScheduleService::class)->assertNoExistingBooking($this, $asset);
            }

            return parent::save($options);
        });
    }

    public function setStartTimeAttribute(string $value): void
    {
        $this->attributes['start_time'] = strlen($value) === 5 ? $value.':00' : $value;
    }

    public function setEndTimeAttribute(string $value): void
    {
        $this->attributes['end_time'] = strlen($value) === 5 ? $value.':00' : $value;
    }

    /** Weekly local-time recurrence, with half-open intervals and overnight support. */
    public function overlaps(Carbon $start, Carbon $end): bool
    {
        if (! $this->is_active || $end->lessThanOrEqualTo($start)) {
            return false;
        }
        $zone = config('app.timezone');
        $start = $start->copy()->timezone($zone);
        $end = $end->copy()->timezone($zone);
        $first = $start->copy()->subDay()->startOfDay()->max($this->valid_from->copy()->startOfDay());
        $last = $this->valid_until?->copy()->endOfDay();
        foreach ($this->weekdays as $weekday) {
            $day = $first->copy()->addDays(((int) $weekday - $first->dayOfWeekIso + 7) % 7);
            // At most the first candidate and the following week need checking.
            for ($i = 0; $i < 2; $i++, $day->addWeek()) {
                if (($last && $day->greaterThan($last)) || $day->greaterThanOrEqualTo($end)) {
                    break;
                }
                $left = $day->copy()->setTimeFromTimeString($this->start_time);
                $right = $day->copy()->setTimeFromTimeString($this->end_time);
                if ($right->lessThanOrEqualTo($left)) {
                    $right->addDay();
                }
                if ($left->lessThan($end) && $right->greaterThan($start)) {
                    return true;
                }
            }
        }

        return false;
    }
}
