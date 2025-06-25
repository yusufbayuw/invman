<?php

namespace App\Filament\Widgets;

use App\Models\PinnedMenu;
use Filament\Widgets\Widget;
use Filament\Facades\Filament;

class MenuGridWidget extends Widget
{
    protected static string $view = 'filament.widgets.menu-grid-widget';

    protected int | string | array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public string $searchTerm = '';

    public int $currentPage = 1;

    protected int $itemsPerPage = 9;

    public bool $isMobile = false;

    public array $pinnedMenus = [];

    public function mount()
    {
        $this->loadPinnedMenus();
    }

    public function loadPinnedMenus()
    {
        $this->pinnedMenus = PinnedMenu::where('user_id', auth()->id())->get()->toArray();
    }

    public function togglePin($label, $url, $icon = null)
    {
        $existing = PinnedMenu::where('user_id', auth()->id())->where('url', $url)->first();

        if ($existing) {
            $existing->delete();
        } else {
            PinnedMenu::create([
                'user_id' => auth()->id(),
                'label' => $label,
                'url' => $url,
                'icon' => $icon,
            ]);
        }

        $this->loadPinnedMenus();
    }


    protected function getItemsPerPage(): int
    {
        return $this->isMobile ? 4 : 9;
    }

    protected function getFilteredMenuItems(): array
    {
        return collect(Filament::getNavigation())
            ->flatMap(fn($group) => method_exists($group, 'getItems') ? $group->getItems() : [])
            ->filter(fn($item) => $item->isVisible() && !$item->isHidden())
            ->map(fn($item) => [
                'label' => $item->getLabel(),
                'url'   => $item->getUrl(),
                'icon'  => $item->getIcon() ?? 'heroicon-o-question-mark-circle',
            ])
            ->filter(fn($item) => str_contains(strtolower($item['label']), strtolower($this->searchTerm)))
            ->values()
            ->toArray();
    }

    public function getPaginatedItems(): array
    {
        $items = $this->getFilteredMenuItems();
        return array_slice(
            $items,
            ($this->currentPage - 1) * $this->getItemsPerPage(),
            $this->getItemsPerPage()
        );
    }

    public function getTotalPages(): int
    {
        $items = $this->getFilteredMenuItems();
        return ceil(count($items) / $this->getItemsPerPage());
    }

    public function goToPage($page)
    {
        $this->currentPage = max(1, min($page, $this->getTotalPages()));
    }

    public function previousPage()
    {
        $this->goToPage($this->currentPage - 1);
    }

    public function nextPage()
    {
        $this->goToPage($this->currentPage + 1);
    }

    public function updatedSearchTerm()
    {
        $this->currentPage = 1;
    }
}
// In this code, we have a Filament widget that displays a grid of menu items.
// The widget allows users to search for menu items, paginate through them, and pin/unpin items.
// The `loadPinnedMenus` method retrieves the pinned menus for the authenticated user.
// The `togglePin` method allows users to pin or unpin menu items.
// The `getFilteredMenuItems` method filters the menu items based on the search term.
// The `getPaginatedItems` method retrieves the paginated items based on the current page.
// The `getTotalPages` method calculates the total number of pages based on the filtered items.
// The `goToPage`, `previousPage`, and `nextPage` methods handle pagination.
// The `updatedSearchTerm` method resets the current page when the search term is updated.
// The widget is designed to be responsive, with different item counts for mobile and desktop views.
// The `isMobile` property is used to determine the current device type.
// The widget uses the Filament framework to manage navigation and display menu items.
// The `Filament::getNavigation()` method retrieves the navigation items defined in the Filament configuration.
// The widget is designed to be used within a Filament application, providing a user-friendly interface for navigating through menu items.
// The widget is fully customizable, allowing developers to modify the appearance and behavior as needed.
// The widget can be easily integrated into any Filament page or layout, providing a consistent user experience.