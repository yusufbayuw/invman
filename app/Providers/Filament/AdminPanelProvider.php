<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Login;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\CustomChatifyPage;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\RekapanPenggunaan;
use App\Filament\Widgets\CalendarWidget;
use App\Filament\Widgets\LoanOperationsStats;
use App\Filament\Widgets\LoanStatusChart;
use App\Filament\Widgets\LoanUsageTrendChart;
use App\Filament\Widgets\MenuGridWidget;
use App\Filament\Widgets\QuickActionsWidget;
use App\Filament\Widgets\RecentLoanRequests;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Monzer\FilamentChatifyIntegration\ChatifyPlugin;
use Saade\FilamentFullCalendar\FilamentFullCalendarPlugin;
use SolutionForest\FilamentSimpleLightBox\SimpleLightBoxPlugin;
use Swis\Filament\Backgrounds\FilamentBackgroundsPlugin;
use Swis\Filament\Backgrounds\ImageProviders\Triangles;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->darkMode(false)
            ->brandLogo(fn () => view('filament.components.logo'))
            ->brandLogoHeight('4rem')
            ->path('admin')
            ->favicon(asset(config('app.logo')))
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => view('pwa.meta'))
            ->renderHook(PanelsRenderHook::BODY_END, fn () => view('pwa.client', [
                'showBanner' => auth()->check(),
            ]))
            ->login(Login::class)
            ->profile(EditProfile::class, isSimple: false)
            ->databaseNotifications()
            ->databaseNotificationsPolling('15s')
            ->globalSearchKeyBindings(['ctrl+k', 'command+k'])
            ->globalSearchFieldKeyBindingSuffix()
            ->globalSearchDebounce('400ms')
            ->sidebarCollapsibleOnDesktop()
            ->unsavedChangesAlerts()
            ->maxContentWidth(MaxWidth::Full)
            ->userMenuItems([
                MenuItem::make()
                    ->label('Rekapan Penggunaan')
                    ->icon('heroicon-o-chart-bar-square')
                    ->url(fn (): string => RekapanPenggunaan::getUrl())
                    ->sort(10),
            ])
            ->colors([
                'danger' => Color::Rose,
                'gray' => Color::Gray,
                'info' => Color::Blue,
                'primary' => Color::Indigo,
                'success' => Color::Emerald,
                'warning' => Color::Orange,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            // ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                // Widgets\AccountWidget::class,
                MenuGridWidget::class,
                LoanOperationsStats::class,
                QuickActionsWidget::class,
                LoanStatusChart::class,
                LoanUsageTrendChart::class,
                RecentLoanRequests::class,
                CalendarWidget::class,
                // Widgets\FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugins([
                ChatifyPlugin::make()->customPage(CustomChatifyPage::class),
                SimpleLightBoxPlugin::make(),
                FilamentShieldPlugin::make(),
                FilamentBackgroundsPlugin::make()
                    ->imageProvider(
                        Triangles::make()
                    )
                    ->showAttribution(false),
                FilamentFullCalendarPlugin::make()
                    ->editable(false),
            ]);
    }
}
