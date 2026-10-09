<?php

return [
    // Development fixtures are always disabled in production regardless of this switch.
    'seed_demo_users' => (bool) env('SEED_DEMO_USERS', false),
    'seed_demo_password' => env('SEEDED_USER_PASSWORD'),

    // Only infrastructure you control may supply X-Forwarded-* headers.
    // Use a comma-separated list of IP addresses or CIDR ranges; do not use '*'.
    'trusted_proxies' => env('TRUSTED_PROXIES', ''),
];
