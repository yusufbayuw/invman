<?php

namespace App\Providers;

use App\Licensing\EmbeddedLicensePublicKey;
use App\Licensing\LicenseManager;
use App\Licensing\LicensePublicKeyProvider;
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
        $this->app->singleton(LicensePublicKeyProvider::class, EmbeddedLicensePublicKey::class);
        $this->app->singleton(LicenseManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::unguard();
        if (env('APP_ENV') === 'production') {
            URL::forceScheme('https');
        }
    }
}
