<?php

namespace App\Console\Commands;

use App\Services\RetentionHoldService;
use Illuminate\Console\Command;

class CheckRetentionMaturity extends Command
{
    /**
     * @var string
     */
    protected $signature = 'retention:check-maturity';

    /**
     * @var string
     */
    protected $description = 'Mark insurance retention holds as matured when the 6-month date is reached';

    public function handle(RetentionHoldService $holds): int
    {
        $matured = $holds->markDueAsMatured();

        $this->info(sprintf('Marked %d retention hold(s) as matured.', $matured->count()));

        return self::SUCCESS;
    }
}
