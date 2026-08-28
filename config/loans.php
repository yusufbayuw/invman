<?php

return [
    /*
    | Pengajuan berstatus submitted menahan ketersediaan aset selama periode
    | ini. Setelah lewat, kebutuhan yang belum diputuskan akan dilepaskan.
    */
    'hold_hours' => max(1, (int) env('LOAN_HOLD_HOURS', 24)),
];
