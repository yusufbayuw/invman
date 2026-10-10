<?php

namespace App\Filament\Widgets;

use App\Services\LoanOperationalPulseService;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

class OperationalPriorityWidget extends Widget
{
    use InteractsWithPageFilters;

    protected static string $view = 'filament.widgets.operational-priority-widget';

    protected static ?int $sort = -88;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public string $activeCategory = 'all';

    public static function canView(): bool
    {
        $user = auth()->user();

        return (bool) ($user && ($user->isFacility() || $user->isSarpras() || $user->isAssetManager()));
    }

    public function selectCategory(string $category): void
    {
        if ($category === 'all' || array_key_exists($category, LoanOperationalPulseService::CATEGORIES)) {
            $this->activeCategory = $category;
        }
    }

    protected function getViewData(): array
    {
        $user = auth()->user();

        // Historical period and status filters must not hide active/overdue
        // operations; only facility-selected unit filter is carried through.
        $unit = $user?->isFacility() && filled($this->filters['unit_id'] ?? null)
            ? (int) $this->filters['unit_id']
            : null;

        return [
            'categories' => LoanOperationalPulseService::CATEGORIES,
            'snapshot' => app(LoanOperationalPulseService::class)->snapshot(
                $user, $this->activeCategory, $unit,
            ),
        ];
    }
}
