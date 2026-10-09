<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Business record retention (years)
    |--------------------------------------------------------------------------
    |
    | Iraqi commercial / tax recordkeeping often expects multi-year retention
    | (commonly discussed as 7+ years). Confirm the exact obligation with
    | qualified counsel for Zhako's entity and jurisdictions — this value is
    | an operational target for backups and audit archives, not legal advice.
    |
    */
    'retention_years' => (int) env('COMPLIANCE_RETENTION_YEARS', 7),

    /*
    |--------------------------------------------------------------------------
    | Backup lifecycle (operational policy)
    |--------------------------------------------------------------------------
    */
    'backup_lifecycle' => [
        'daily_hot_days' => (int) env('COMPLIANCE_BACKUP_DAILY_HOT_DAYS', 30),
        'weekly_archive_weeks' => (int) env('COMPLIANCE_BACKUP_WEEKLY_ARCHIVE_WEEKS', 52),
        'cold_retention_years' => (int) env('COMPLIANCE_BACKUP_COLD_YEARS', 7),
    ],

];
