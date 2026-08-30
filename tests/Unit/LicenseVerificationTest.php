<?php

namespace Tests\Unit;

use App\Licensing\LicenseManager;
use App\Licensing\LicenseStatusCode;
use Tests\TestCase;

class LicenseVerificationTest extends TestCase
{
    public function test_reordering_case_whitespace_and_duplicates_do_not_invalidate_license(): void
    {
        $token = $this->configureValidTestLicense('example.com,*.example.com', '127.0.0.1,192.168.1.0/24');
        config()->set([
            'license.allowed_domains' => ' *.EXAMPLE.COM, example.com,example.com ',
            'license.allowed_host_ips' => '192.168.1.44/24,127.0.0.1',
            'license.token' => $token,
        ]);

        $result = $this->app->make(LicenseManager::class)->validateConfiguration();

        $this->assertTrue($result->valid);
        $this->assertSame(LicenseStatusCode::Valid, $result->code);
    }

    public function test_a_semantic_allowlist_change_invalidates_the_existing_token(): void
    {
        $this->configureValidTestLicense('example.com', '127.0.0.1');
        config()->set('license.allowed_domains', 'example.com,new.example.com');

        $result = $this->app->make(LicenseManager::class)->validateConfiguration();

        $this->assertFalse($result->valid);
        $this->assertSame(LicenseStatusCode::AllowlistMismatch, $result->code);
    }

    public function test_corrupted_signature_is_rejected(): void
    {
        $token = $this->configureValidTestLicense();
        $parts = explode('.', $token);
        $parts[2][0] = $parts[2][0] === 'A' ? 'B' : 'A';
        config()->set('license.token', implode('.', $parts));

        $result = $this->app->make(LicenseManager::class)->validateConfiguration();

        $this->assertFalse($result->valid);
        $this->assertSame(LicenseStatusCode::InvalidSignature, $result->code);
    }

    public function test_malformed_token_and_wrong_product_are_distinguished(): void
    {
        config()->set('license.token', 'not-a-token');
        $malformed = $this->app->make(LicenseManager::class)->validateConfiguration();

        $this->assertSame(LicenseStatusCode::MalformedToken, $malformed->code);

        $allowlist = $this->app->make(\App\Licensing\AllowlistNormalizer::class)
            ->fromStrings('localhost,invman.test', '127.0.0.1,::1');
        config()->set('license.token', $this->signTestLicense($allowlist->domains, $allowlist->hostIps, 'another-product'));
        $wrongProduct = $this->app->make(LicenseManager::class)->validateConfiguration();

        $this->assertSame(LicenseStatusCode::ProductMismatch, $wrongProduct->code);
    }

    public function test_noncanonical_signed_payload_is_rejected(): void
    {
        config()->set('license.token', $this->signTestLicense(['localhost', 'localhost'], ['127.0.0.1', '::1']));

        $result = $this->app->make(LicenseManager::class)->validateConfiguration();

        $this->assertSame(LicenseStatusCode::InvalidPayload, $result->code);
    }

    public function test_wrong_signed_payload_version_is_rejected(): void
    {
        $allowlist = $this->app->make(\App\Licensing\AllowlistNormalizer::class)
            ->fromStrings('localhost,invman.test', '127.0.0.1,::1');
        config()->set('license.token', $this->signTestLicense(
            $allowlist->domains,
            $allowlist->hostIps,
            overrides: ['version' => 2],
        ));

        $result = $this->app->make(LicenseManager::class)->validateConfiguration();

        $this->assertSame(LicenseStatusCode::InvalidPayload, $result->code);
    }
}
