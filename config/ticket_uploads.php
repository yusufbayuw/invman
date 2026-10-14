<?php

return [
    // Enable on servers with clamscan installed for fail-closed upload scanning.
    'require_malware_scan' => (bool) env('TICKET_UPLOAD_REQUIRE_MALWARE_SCAN', false),
    'clamav_binary' => env('TICKET_UPLOAD_CLAMAV_BINARY', 'clamscan'),
    'clamav_timeout' => (int) env('TICKET_UPLOAD_CLAMAV_TIMEOUT', 30),
];
