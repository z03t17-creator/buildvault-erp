<?php

namespace App\Console\Commands;

use App\Services\BusinessDataWipeService;
use Illuminate\Console\Command;

/**
 * Clear projects, people, money, stock, imports — keep roles + core @zhako.test logins.
 * Does not import Mayorca. Use when starting from an empty contractor books.
 */
class WipeBusinessData extends Command
{
    protected $signature = 'business:wipe
        {--dry-run : Preview row counts without deleting}
        {--commit : Confirm destructive wipe}';

    protected $description = 'Wipe all business data; keep roles and core login users (empty vault)';

    public function handle(BusinessDataWipeService $wiper): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $commit = (bool) $this->option('commit');

        if (! $dryRun && ! $commit) {
            $this->error('Refusing wipe without --commit (or use --dry-run).');

            return self::FAILURE;
        }

        $this->info($dryRun ? 'Dry-run wipe preview…' : 'Wiping business data (no Mayorca import)…');

        $result = $wiper->wipe(dryRun: $dryRun);

        $this->line('Kept users: '.implode(', ', $result['kept_users']));
        foreach ($result['wiped_tables'] as $table => $count) {
            if ($count > 0) {
                $this->line(sprintf('  %s: %d', $table, $count));
            }
        }

        if ($dryRun) {
            $this->comment('Dry-run only — nothing deleted.');
        } else {
            $this->info('Wipe committed. Vault balances are 0. No demo projects or people remain.');
        }

        return self::SUCCESS;
    }
}
