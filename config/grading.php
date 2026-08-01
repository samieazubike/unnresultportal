<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Course failure-rate threshold
    |--------------------------------------------------------------------------
    |
    | A course whose failure rate for a session exceeds this percentage is
    | flagged (README §7). This is the default; an administrator may override
    | it at runtime, in which case the value is stored in the settings table
    | and read through Setting::failureThreshold().
    |
    */

    'failure_threshold' => (float) env('RESULT_FAILURE_THRESHOLD', 40),

    /*
    |--------------------------------------------------------------------------
    | Academic session
    |--------------------------------------------------------------------------
    |
    | The session offered by default on entry and registration screens, in the
    | '2025/2026' form used throughout registrations.session.
    |
    */

    'current_session' => env('RESULT_CURRENT_SESSION', '2025/2026'),

];
