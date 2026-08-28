<?php

namespace App\Providers\Filament;

use App\Filament\Public\Pages\LoanSchedule;
use App\Filament\Public\Widgets\ItemLoanSchedule;
use App\Filament\Public\Widgets\LoanScheduleStats;
use App\Filament\Public\Widgets\RoomLoanSchedule;
use App\Filament\Public\Widgets\VehicleLoanSchedule;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class PublicPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('public')
            ->path('')
            ->brandName(config('app.name'))
            ->brandLogo(fn () => view('filament.components.logo'))
            ->brandLogoHeight('4rem')
            ->favicon(asset(config('app.logo')))
            ->darkMode(false)
            ->navigation(false)
            ->maxContentWidth(MaxWidth::SevenExtraLarge)
            ->colors([
                'primary' => Color::Indigo,
                'success' => Color::Emerald,
                'info' => Color::Blue,
                'gray' => Color::Gray,
            ])
            ->pages([
                LoanSchedule::class,
            ])
            ->widgets([
                LoanScheduleStats::class,
                ItemLoanSchedule::class,
                RoomLoanSchedule::class,
                VehicleLoanSchedule::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ]);
    }
}
