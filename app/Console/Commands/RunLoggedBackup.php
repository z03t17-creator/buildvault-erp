<?php

namespace App\Console\Commands;

use App\Models\Backup;
use App\Services\BackupService;
use Illuminate\Console\Command;

class RunLoggedBackup extends Command
{
    protected $signature = 'backup:run-logged {--type=full : full|database|files}';

    protected $description = 'Run Spatie backup and log the run to the backups table';

    public function handle(BackupService $backups): int
    {
        $type = (string) $this->option('type');
        $this->info("Starting logged backup ({$type})…");

        $backup = $backups->run($type);

        if ($backup->status === Backup::STATUS_COMPLETED) {
            $this->info("Completed: {$backup->filename} (".number_format($backup->size_bytes).' bytes)');

            return self::SUCCESS;
        }

        $this->error($backup->message ?: 'Backup failed');

        return self::FAILURE;
    }
}
