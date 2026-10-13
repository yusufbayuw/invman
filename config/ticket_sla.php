<?php

return [
    // Organizational starting targets. Adjust to the approved operational policy.
    // Minutes are accumulated only in configured working periods.
    'timezone' => env('TICKET_SLA_TIMEZONE', 'Asia/Jakarta'),
    'workday_start' => '08:00',
    'workday_end' => '17:00',
    'working_weekdays' => [1, 2, 3, 4, 5], // ISO Monday = 1.
    'holidays' => [], // YYYY-MM-DD; maintain annually per organizational calendar.
    'targets' => [
        'critical' => ['response_minutes' => 60, 'resolution_minutes' => 240],
        'high' => ['response_minutes' => 120, 'resolution_minutes' => 480],
        'normal' => ['response_minutes' => 480, 'resolution_minutes' => 1440],
        'low' => ['response_minutes' => 960, 'resolution_minutes' => 2880],
    ],
    // Deliberately no automatic SLA pause while waiting for parts.
    'pause_for_waiting_parts' => false,
];
