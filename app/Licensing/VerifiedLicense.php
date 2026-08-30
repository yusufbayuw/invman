<?php

namespace App\Licensing;

final readonly class VerifiedLicense
{
    public function __construct(
        public string $licenseId,
        public string $issuedAt,
        public Allowlist $allowlist,
    ) {}
}
