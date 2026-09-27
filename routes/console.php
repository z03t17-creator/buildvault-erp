<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('retention:check-maturity')->daily();

// Phase 4.8 — Spatie backup to storage/app/backups (logged in `backups` table)
// SiteBunker: cron `* * * * * php artisan schedule:run` (or direct `php artisan backup:run` / `backup:run-logged`)
Schedule::command('backup:run-logged --type=full')->dailyAt('02:00');
Schedule::command('backup:clean')->dailyAt('03:00');
