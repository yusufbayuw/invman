<?php

namespace App\Licensing;

final class EmbeddedLicensePublicKey implements LicensePublicKeyProvider
{
    private const PUBLIC_KEY = 'iKyJF+iwouxMqOkGWkNACagQ++Q8IdnZe6YKdWG1LBw=';

    public function base64PublicKey(): string
    {
        return self::PUBLIC_KEY;
    }
}
