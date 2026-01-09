<?php
namespace squipix\Idempotency\Console;

use Illuminate\Console\Command;
use squipix\Idempotency\Services\IdempotencyService;

class CleanupExpiredKeysCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'idempotency:cleanup
                            {--days=7 : Number of days to keep records}
                            {--dry-run : Show what would be deleted without deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired idempotency key records';

    /**
     * Execute the console command.
     */
    public function handle(IdempotencyService $service): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');

        $this->info("Cleaning up idempotency keys older than {$days} days...");

        if ($dryRun) {
            $count = $service->countExpiredRecords($days);
            $this->warn("DRY RUN: Would delete {$count} records");
            return self::SUCCESS;
        }

        $deleted = $service->cleanupExpiredRecords($days);
        
        $this->info("Successfully deleted {$deleted} expired records");
        
        return self::SUCCESS;
    }
}
