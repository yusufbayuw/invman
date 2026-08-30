<?php

namespace App\Licensing;

use RuntimeException;

final class LicenseTokenException extends RuntimeException
{
    public function __construct(public readonly LicenseStatusCode $status)
    {
        parent::__construct($status->value);
    }
}
