<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

final class AccountPasswordPolicy
{
    public static function rule(): Password
    {
        return Password::min(16)->mixedCase()->numbers()->symbols();
    }
}
