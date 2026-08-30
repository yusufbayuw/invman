<?php

namespace App\Licensing;

use JsonException;

final class LicenseManager
{
    private ?string $cacheKey = null;

    private ?Allowlist $allowlist = null;

    private ?VerifiedLicense $license = null;

    private ?LicenseValidationResult $configurationResult = null;

    public function __construct(
        private readonly AllowlistNormalizer $normalizer,
        private readonly HostMatcher $matcher,
        private readonly LicenseTokenVerifier $verifier,
        private readonly LicensePublicKeyProvider $publicKey,
    ) {}

    public function validateConfiguration(): LicenseValidationResult
    {
        $domains = (string) config('license.allowed_domains', '');
        $hostIps = (string) config('license.allowed_host_ips', '');
        $token = (string) config('license.token', '');
        $product = (string) config('license.product', 'invman');
        $cacheKey = hash('sha256', implode("\0", [$domains, $hostIps, $token, $product, $this->publicKey->base64PublicKey()]));

        if ($this->cacheKey === $cacheKey && $this->configurationResult !== null) {
            return $this->configurationResult;
        }

        $this->cacheKey = $cacheKey;
        $this->allowlist = null;
        $this->license = null;

        try {
            $this->allowlist = $this->normalizer->fromStrings($domains, $hostIps);
        } catch (InvalidAllowlist $exception) {
            $code = trim($domains) === '' && trim($hostIps) === ''
                ? LicenseStatusCode::MissingAllowlist
                : LicenseStatusCode::InvalidAllowlist;

            return $this->configurationResult = LicenseValidationResult::invalid($code);
        }

        if (trim($token) === '') {
            return $this->configurationResult = LicenseValidationResult::invalid(
                LicenseStatusCode::MissingToken,
                ['fingerprint' => $this->fingerprint($this->allowlist)],
            );
        }

        try {
            $this->license = $this->verifier->verify($token, $product);
        } catch (LicenseTokenException $exception) {
            return $this->configurationResult = LicenseValidationResult::invalid(
                $exception->status,
                ['fingerprint' => $this->fingerprint($this->allowlist)],
            );
        }

        if ($this->license->allowlist->toArray() !== $this->allowlist->toArray()) {
            return $this->configurationResult = LicenseValidationResult::invalid(
                LicenseStatusCode::AllowlistMismatch,
                [
                    'license_id' => $this->license->licenseId,
                    'fingerprint' => $this->fingerprint($this->allowlist),
                ],
            );
        }

        return $this->configurationResult = LicenseValidationResult::valid([
            'license_id' => $this->license->licenseId,
            'issued_at' => $this->license->issuedAt,
            'fingerprint' => $this->fingerprint($this->allowlist),
        ]);
    }

    public function validateHost(string $host): LicenseValidationResult
    {
        $configuration = $this->validateConfiguration();

        if (! $configuration->valid) {
            return $configuration;
        }

        try {
            $normalizedHost = $this->normalizer->normalizeRequestHost($host);
        } catch (InvalidAllowlist) {
            return LicenseValidationResult::invalid(LicenseStatusCode::InvalidHost, $configuration->context);
        }

        if ($this->allowlist === null || ! $this->matcher->matches($normalizedHost, $this->allowlist)) {
            return LicenseValidationResult::invalid(
                LicenseStatusCode::HostNotAllowed,
                [...$configuration->context, 'host' => $normalizedHost],
            );
        }

        return LicenseValidationResult::valid([...$configuration->context, 'host' => $normalizedHost]);
    }

    public function validateAppUrl(): LicenseValidationResult
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            $configuration = $this->validateConfiguration();

            return LicenseValidationResult::invalid(LicenseStatusCode::InvalidAppUrl, $configuration->context);
        }

        return $this->validateHost($host);
    }

    /**
     * @return array{schema: int, product: string, domains: list<string>, host_ips: list<string>, fingerprint: string}
     */
    public function requestDocument(): array
    {
        $allowlist = $this->normalizer->fromStrings(
            (string) config('license.allowed_domains', ''),
            (string) config('license.allowed_host_ips', ''),
        );

        return [
            'schema' => 1,
            'product' => (string) config('license.product', 'invman'),
            ...$allowlist->toArray(),
            'fingerprint' => $this->fingerprint($allowlist),
        ];
    }

    public function fingerprint(Allowlist $allowlist): string
    {
        try {
            $canonical = json_encode([
                'product' => (string) config('license.product', 'invman'),
                ...$allowlist->toArray(),
            ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return '';
        }

        return hash('sha256', $canonical);
    }
}
