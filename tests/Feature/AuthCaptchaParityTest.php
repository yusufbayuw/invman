<?php

namespace Tests\Feature;

use App\Filament\Auth\Login;
use App\Filament\Forms\Components\ShieldCaptcha;
use App\Filament\Support\InvmanCaptcha;
use Filament\Facades\Filament;
use MortezaAshrafi\FilamentShieldCaptcha\CaptchaManager;
use MortezaAshrafi\FilamentShieldCaptcha\Enums\Theme;
use MortezaAshrafi\FilamentShieldCaptcha\Verification\VerificationStatus;
use Tests\TestCase;

class AuthCaptchaParityTest extends TestCase
{
    public function test_login_uses_the_custom_filament_auth_page(): void
    {
        $this->assertSame(Login::class, Filament::getPanel('admin')->getLoginRouteAction());
        $this->assertInstanceOf(ShieldCaptcha::class, InvmanCaptcha::make());
    }

    public function test_local_captcha_matches_spmb_readable_defaults(): void
    {
        $this->assertSame('custom', config('filament-shield-captcha.defaults.mode'));
        $this->assertSame('ABCDEFGHJKLMNPQRSTUVWXYZ23456789', config('filament-shield-captcha.defaults.charset'));
        $this->assertFalse(config('filament-shield-captcha.defaults.case_sensitive'));
        $this->assertSame(5, config('filament-shield-captcha.defaults.length'));
        $this->assertSame(1, config('filament-shield-captcha.defaults.noise.level'));
        $this->assertSame(5, config('filament-shield-captcha.store.max_attempts'));
        $this->assertSame(300, config('filament-shield-captcha.store.ttl'));
    }

    public function test_local_captcha_provides_light_and_dark_images_and_rejects_invalid_code(): void
    {
        $manager = app(CaptchaManager::class);
        $options = $manager->optionsFromConfig([]);
        $context = $manager->contextKey('invman-login-captcha-test');

        $manager->refreshChallenge($context, $options);

        $this->assertStringStartsWith('data:image/png;base64,', $manager->imageDataUri($context, $options, Theme::Light));
        $this->assertStringStartsWith('data:image/png;base64,', $manager->imageDataUri($context, $options, Theme::Dark));
        $this->assertSame(VerificationStatus::Failed, $manager->verify($context, '!!!!!')->status);
    }
}
