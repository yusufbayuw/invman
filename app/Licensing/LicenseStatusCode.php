<?php

namespace App\Licensing;

enum LicenseStatusCode: string
{
    case Valid = 'valid';
    case MissingAllowlist = 'missing_allowlist';
    case InvalidAllowlist = 'invalid_allowlist';
    case MissingToken = 'missing_token';
    case MalformedToken = 'malformed_token';
    case InvalidSignature = 'invalid_signature';
    case InvalidPayload = 'invalid_payload';
    case ProductMismatch = 'product_mismatch';
    case AllowlistMismatch = 'allowlist_mismatch';
    case InvalidHost = 'invalid_host';
    case HostNotAllowed = 'host_not_allowed';
    case InvalidAppUrl = 'invalid_app_url';
}
