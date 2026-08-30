<?php

namespace App\Licensing;

final readonly class LicenseValidationResult
{
    /**
     * @param  array<string, scalar|null>  $context
     */
    public function __construct(
        public bool $valid,
        public LicenseStatusCode $code,
        public array $context = [],
    ) {}

    /**
     * @param  array<string, scalar|null>  $context
     */
    public static function valid(array $context = []): self
    {
        return new self(true, LicenseStatusCode::Valid, $context);
    }

    /**
     * @param  array<string, scalar|null>  $context
     */
    public static function invalid(LicenseStatusCode $code, array $context = []): self
    {
        return new self(false, $code, $context);
    }
}
