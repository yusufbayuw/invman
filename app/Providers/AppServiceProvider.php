<?php

namespace App\Providers;

use App\Licensing\EmbeddedLicensePublicKey;
use App\Licensing\LicenseManager;
use App\Licensing\LicensePublicKeyProvider;
use App\Services\ChatifyMessenger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind('ChatifyMessenger', ChatifyMessenger::class);
        $this->app->singleton(LicensePublicKeyProvider::class, EmbeddedLicensePublicKey::class);
        $this->app->singleton(LicenseManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Never disable Eloquent mass-assignment safeguards globally.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
        if (config('app.env') === 'production' && parse_url((string) config('app.url'), PHP_URL_SCHEME) === 'https') {
            URL::forceScheme('https');
        }
    }
}
