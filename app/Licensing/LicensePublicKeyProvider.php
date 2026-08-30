<?php

namespace App\Licensing;

interface LicensePublicKeyProvider
{
    public function base64PublicKey(): string;
}
