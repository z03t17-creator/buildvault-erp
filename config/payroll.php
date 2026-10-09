<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Period base salary
    |--------------------------------------------------------------------------
    |
    | Payroll no longer reads attendance. Worker `daily_rate_usd` is treated as
    | the base salary for the selected pay period (typically one calendar month).
    | Optional overtime uses `manual_ot_hours` × `overtime_rate_usd`.
    |
    | Net = base + OT − recorded penalties − insurance holdback − advances
    |
    */

];
