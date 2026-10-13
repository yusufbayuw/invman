<?php

return [
    // Organizational starting targets. Adjust to the approved operational policy.
    // Minutes are accumulated only in configured working periods.
    'timezone' => env('TICKET_SLA_TIMEZONE', 'Asia/Jakarta'),
    'workday_start' => env('TICKET_SLA_WORKDAY_START', '08:00'),
    'workday_end' => env('TICKET_SLA_WORKDAY_END', '17:00'),
    // ISO Monday=1; changes should reflect approved local operational hours.
    'working_weekdays' => array_map('intval', explode(',', env('TICKET_SLA_WORKING_WEEKDAYS', '1,2,3,4,5'))),
    // YYYY-MM-DD comma-separated, e.g. 2026-12-25,2026-12-31.
    // Update the calendar before each year; deadlines are stamped at ticket creation.
    'holidays' => array_values(array_filter(array_map('trim', explode(',', env('TICKET_SLA_HOLIDAYS', ''))))),
    'targets' => [
        'critical' => ['response_minutes' => 60, 'resolution_minutes' => 240],
        'high' => ['response_minutes' => 120, 'resolution_minutes' => 480],
        'normal' => ['response_minutes' => 480, 'resolution_minutes' => 1440],
        'low' => ['response_minutes' => 960, 'resolution_minutes' => 2880],
    ],
    // Deliberately no automatic SLA pause while waiting for parts.
    'pause_for_waiting_parts' => false,
];
