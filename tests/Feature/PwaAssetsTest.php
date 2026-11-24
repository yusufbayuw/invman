<?php

namespace Tests\Feature;

use Tests\TestCase;

class PwaAssetsTest extends TestCase
{
    public function test_manifest_defines_installable_application_scope_and_icons(): void
    {
        $manifest = json_decode(
            file_get_contents(public_path('manifest.webmanifest')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame('LIST', $manifest['name']);
        $this->assertSame('/admin', $manifest['start_url']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertSame('standalone', $manifest['display']);

        $this->assertSame([192, 192], array_slice(getimagesize(public_path('images/pwa/icon-192.png')), 0, 2));
        $this->assertSame([512, 512], array_slice(getimagesize(public_path('images/pwa/icon-512.png')), 0, 2));
        $this->assertSame([512, 512], array_slice(getimagesize(public_path('images/pwa/icon-maskable-512.png')), 0, 2));
        $this->assertContains('maskable', array_column($manifest['icons'], 'purpose'));
    }

    public function test_onboarding_is_rendered_in_page_content_and_only_prompts_for_available_actions(): void
    {
        $client = file_get_contents(resource_path('views/pwa/client.blade.php'));
        $onboarding = file_get_contents(resource_path('views/pwa/onboarding.blade.php'));
        $provider = file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));
        $script = file_get_contents(public_path('js/pwa.js'));

        $this->assertStringContainsString('id="pwa-onboarding"', $onboarding);
        $this->assertStringContainsString('data-pwa-dismiss', $onboarding);
        $this->assertStringContainsString('position: relative;', $client);
        $this->assertStringNotContainsString('position: fixed;', $client);
        $this->assertStringContainsString("view('pwa.onboarding')", $provider);
        $this->assertStringContainsString('PanelsRenderHook::CONTENT_START', $provider);
        $this->assertStringContainsString('const needsAttention = installAvailable || canEnableNotifications;', $script);
        $this->assertStringContainsString("Notification.permission !== 'denied'", $script);
    }

    public function test_service_worker_handles_push_without_caching_pages(): void
    {
        $serviceWorker = file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString("addEventListener('push'", $serviceWorker);
        $this->assertStringContainsString("addEventListener('notificationclick'", $serviceWorker);
        $this->assertStringNotContainsString("addEventListener('fetch'", $serviceWorker);
        $this->assertStringNotContainsString('caches.open', $serviceWorker);
    }
}
