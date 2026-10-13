<?php

return [
    /*
    | Pengajuan berstatus submitted menahan ketersediaan aset selama periode
    | ini. Setelah lewat, kebutuhan yang belum diputuskan akan dilepaskan.
    */
    'hold_hours' => max(1, (int) env('LOAN_HOLD_HOURS', 24)),

    // Keep older programmatic callers available in local/testing only.
    // Production must use two-party checkout or explicit reason-coded fallback.
    // An emergency compatibility exception must be set deliberately and audited.
    'allow_legacy_direct_checkout' => (bool) env(
        'LOAN_ALLOW_LEGACY_DIRECT_CHECKOUT',
        env('APP_ENV', 'production') !== 'production',
    ),
];
