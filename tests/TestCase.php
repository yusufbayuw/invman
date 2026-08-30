<?php

namespace Tests;

use App\Licensing\AllowlistNormalizer;
use App\Licensing\LicenseManager;
use App\Licensing\LicensePublicKeyProvider;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    private static ?string $licenseSecretKey = null;

    private static ?string $licensePublicKey = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureTestLicenseKeyPair();
        $this->app->instance(LicensePublicKeyProvider::class, new class(self::$licensePublicKey) implements LicensePublicKeyProvider
        {
            public function __construct(private readonly string $publicKey) {}

            public function base64PublicKey(): string
            {
                return base64_encode($this->publicKey);
            }
        });
        $this->app->forgetInstance(LicenseManager::class);
        $this->configureValidTestLicense();
    }

    protected function configureValidTestLicense(
        string $domains = 'localhost,invman.test',
        string $hostIps = '127.0.0.1,::1',
        string $product = 'invman',
    ): string {
        $normalizer = $this->app->make(AllowlistNormalizer::class);
        $allowlist = $normalizer->fromStrings($domains, $hostIps);
        $token = $this->signTestLicense($allowlist->domains, $allowlist->hostIps, $product);

        config()->set([
            'license.product' => 'invman',
            'license.allowed_domains' => $domains,
            'license.allowed_host_ips' => $hostIps,
            'license.token' => $token,
            'app.url' => 'http://localhost',
        ]);

        return $token;
    }

    /**
     * @param  list<string>  $domains
     * @param  list<string>  $hostIps
     * @param  array<string, mixed>  $overrides
     */
    protected function signTestLicense(
        array $domains,
        array $hostIps,
        string $product = 'invman',
        array $overrides = [],
    ): string {
        $payload = json_encode(array_merge([
            'version' => 1,
            'product' => $product,
            'license_id' => '123e4567-e89b-42d3-a456-426614174000',
            'issued_at' => '2026-08-31T00:00:00Z',
            'domains' => $domains,
            'host_ips' => $hostIps,
        ], $overrides), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $signature = sodium_crypto_sign_detached($payload, self::$licenseSecretKey);

        return 'v1.'.$this->base64UrlEncode($payload).'.'.$this->base64UrlEncode($signature);
    }

    private function ensureTestLicenseKeyPair(): void
    {
        if (self::$licenseSecretKey !== null && self::$licensePublicKey !== null) {
            return;
        }

        $keyPair = sodium_crypto_sign_keypair();
        self::$licenseSecretKey = sodium_crypto_sign_secretkey($keyPair);
        self::$licensePublicKey = sodium_crypto_sign_publickey($keyPair);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
