<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Late penalty (USD per late minute)
    |--------------------------------------------------------------------------
    |
    | Applied to the sum of attendance.late_minutes in the period for rows
    | that are present or late (worked days). Set to 0 to disable.
    |
    */
    'late_penalty_per_minute_usd' => (float) env('PAYROLL_LATE_PENALTY_PER_MINUTE', 0.25),

    /*
    |--------------------------------------------------------------------------
    | Unexcused absence penalty
    |--------------------------------------------------------------------------
    |
    | When `unexcused_absence_penalty_usd` is null, each absent_unexcused day
    | costs `worker.daily_rate_usd * unexcused_absence_penalty_multiplier`.
    | Set an explicit USD amount to override the multiplier path.
    |
    */
    'unexcused_absence_penalty_usd' => env('PAYROLL_ABSENCE_PENALTY_USD'), // null = use multiplier
    'unexcused_absence_penalty_multiplier' => (float) env('PAYROLL_ABSENCE_PENALTY_MULTIPLIER', 1.0),

    /*
    |--------------------------------------------------------------------------
    | Paid leave treatment
    |--------------------------------------------------------------------------
    |
    | When true, leave_paid / leave_sick days count toward "days present" base
    | pay (no OT/late from those rows). Unexcused absences never count as days.
    |
    */
    'count_leave_paid_as_day' => (bool) env('PAYROLL_COUNT_LEAVE_PAID', true),
    'count_leave_sick_as_day' => (bool) env('PAYROLL_COUNT_LEAVE_SICK', false),

];
