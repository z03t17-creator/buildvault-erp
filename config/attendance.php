<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default shift window (HH:MM, app timezone)
    |--------------------------------------------------------------------------
    */
    'shift_start' => env('ATTENDANCE_SHIFT_START', '08:00'),
    'shift_end' => env('ATTENDANCE_SHIFT_END', '17:00'),

    /*
    |--------------------------------------------------------------------------
    | Late grace period (minutes after shift start before counting late)
    |--------------------------------------------------------------------------
    */
    'late_grace_minutes' => (int) env('ATTENDANCE_LATE_GRACE_MINUTES', 0),

    /*
    |--------------------------------------------------------------------------
    | Auto-absent cutoff (HH:MM) — workers with no check-in by this time
    | are marked absent_unexcused for the day.
    |--------------------------------------------------------------------------
    */
    'absent_cutoff' => env('ATTENDANCE_ABSENT_CUTOFF', '10:00'),

];
