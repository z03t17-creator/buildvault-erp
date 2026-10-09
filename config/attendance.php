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
    | Full-day forfeit triggers when late_minutes > Attendance::LATE_FORFEIT_MINUTES (30).
    | Grace reduces computed late minutes before that check.
    */
    'late_grace_minutes' => (int) env('ATTENDANCE_LATE_GRACE_MINUTES', 0),

    /*
    |--------------------------------------------------------------------------
    | Auto-absent cutoff (HH:MM)
    |--------------------------------------------------------------------------
    */
    'absent_cutoff' => env('ATTENDANCE_ABSENT_CUTOFF', '10:00'),

    /*
    |--------------------------------------------------------------------------
    | Working days per month (for monthly_salary → daily forfeit amount)
    |--------------------------------------------------------------------------
    */
    'days_per_month' => (int) env('ATTENDANCE_DAYS_PER_MONTH', 30),

];
