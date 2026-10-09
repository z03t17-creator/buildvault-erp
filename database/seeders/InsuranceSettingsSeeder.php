<?php

namespace Database\Seeders;

use App\Services\InsuranceSettings;
use Illuminate\Database\Seeder;

class InsuranceSettingsSeeder extends Seeder
{
    public function run(): void
    {
        app(InsuranceSettings::class)->ensureDefaults();
    }
}
