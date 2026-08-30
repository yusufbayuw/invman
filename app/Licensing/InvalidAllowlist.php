<?php

namespace App\Licensing;

use InvalidArgumentException;

final class InvalidAllowlist extends InvalidArgumentException
{
    public static function empty(): self
    {
        return new self('At least one licensed domain or host IP is required.');
    }

    public static function domain(string $value): self
    {
        return new self("Invalid licensed domain pattern: {$value}");
    }

    public static function ip(string $value): self
    {
        return new self("Invalid licensed host IP or CIDR: {$value}");
    }
}
