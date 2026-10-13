<?php

namespace Tests\Feature;

use DominionSolutions\FilamentCaptcha\Forms\Components\Captcha;
use Filament\Forms\Components\Component as FormComponent;
use Filament\Contracts\Plugin as FilamentPlugin;
use SolutionForest\FilamentSimpleLightBox\SimpleLightBoxPlugin;
use Tests\TestCase;

class Laravel12CompatibilityTest extends TestCase
{
    public function test_framework_and_filament_plugins_remain_compatible(): void
    {
        $this->assertSame(12, (int) explode('.', app()->version())[0]);

        // These classes are part of the existing login CAPTCHA and asset
        // preview screens. Catch accidental package removals on upgrade.
        $this->assertInstanceOf(FormComponent::class, Captcha::make('captcha'));
        $this->assertInstanceOf(FilamentPlugin::class, SimpleLightBoxPlugin::make());
    }
}
