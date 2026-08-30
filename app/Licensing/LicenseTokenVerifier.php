<?php

namespace App\Licensing;

use JsonException;

final readonly class LicenseTokenVerifier
{
    public function __construct(
        private LicensePublicKeyProvider $publicKey,
        private AllowlistNormalizer $normalizer,
    ) {}

    public function verify(string $token, string $expectedProduct): VerifiedLicense
    {
        if (! extension_loaded('sodium')) {
            throw new LicenseTokenException(LicenseStatusCode::InvalidSignature);
        }

        $parts = explode('.', trim($token));

        if (count($parts) !== 3 || $parts[0] !== 'v1') {
            throw new LicenseTokenException(LicenseStatusCode::MalformedToken);
        }

        $payload = $this->decodeBase64Url($parts[1]);
        $signature = $this->decodeBase64Url($parts[2]);
        $publicKey = base64_decode($this->publicKey->base64PublicKey(), true);

        if ($payload === null || $signature === null || $publicKey === false
            || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES
            || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new LicenseTokenException(LicenseStatusCode::MalformedToken);
        }

        if (! sodium_crypto_sign_verify_detached($signature, $payload, $publicKey)) {
            throw new LicenseTokenException(LicenseStatusCode::InvalidSignature);
        }

        try {
            $decoded = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new LicenseTokenException(LicenseStatusCode::InvalidPayload);
        }

        if (! is_array($decoded) || ($decoded['version'] ?? null) !== 1) {
            throw new LicenseTokenException(LicenseStatusCode::InvalidPayload);
        }

        if (($decoded['product'] ?? null) !== $expectedProduct) {
            throw new LicenseTokenException(LicenseStatusCode::ProductMismatch);
        }

        $licenseId = $decoded['license_id'] ?? null;
        $issuedAt = $decoded['issued_at'] ?? null;
        $domains = $decoded['domains'] ?? null;
        $hostIps = $decoded['host_ips'] ?? null;

        if (! is_string($licenseId)
            || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $licenseId) !== 1
            || ! is_string($issuedAt)
            || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $issuedAt) !== 1
            || ! is_array($domains)
            || ! is_array($hostIps)) {
            throw new LicenseTokenException(LicenseStatusCode::InvalidPayload);
        }

        try {
            $allowlist = $this->normalizer->fromArrays($domains, $hostIps);
        } catch (InvalidAllowlist) {
            throw new LicenseTokenException(LicenseStatusCode::InvalidPayload);
        }

        if ($allowlist->domains !== array_values($domains) || $allowlist->hostIps !== array_values($hostIps)) {
            throw new LicenseTokenException(LicenseStatusCode::InvalidPayload);
        }

        return new VerifiedLicense($licenseId, $issuedAt, $allowlist);
    }

    private function decodeBase64Url(string $value): ?string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_-]+$/', $value) !== 1) {
            return null;
        }

        $padding = (4 - strlen($value) % 4) % 4;
        $decoded = base64_decode(strtr($value.str_repeat('=', $padding), '-_', '+/'), true);

        if ($decoded === false || rtrim(strtr(base64_encode($decoded), '+/', '-_'), '=') !== $value) {
            return null;
        }

        return $decoded;
    }
}
