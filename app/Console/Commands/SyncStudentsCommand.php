<?php

namespace App\Console\Commands;

use App\Jobs\SyncStudentDataJob;
use App\Services\DataSyncService;
use Illuminate\Console\Command;

class SyncStudentsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'students:sync {--async : Run sync as a background job}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize student data from external API';

    /**
     * Execute the console command.
     */
    public function handle(DataSyncService $syncService): int
    {
        if ($this->option('async')) {
            // Dispatch to queue
            SyncStudentDataJob::dispatch();
            
            $this->info('✓ Sync job dispatched to queue');
            $this->line('  Use "php artisan queue:work" to process the job');
            
            return self::SUCCESS;
        }

        // Run synchronously
        $this->info('Starting student data synchronization...');
        $this->newLine();

        try {
            $stats = $syncService->fetchAndSync();

            $this->info('✓ Synchronization completed successfully!');
            $this->newLine();
            
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Total Records', $stats['total']],
                    ['Created', $stats['created']],
                    ['Updated', $stats['updated']],
                    ['Failed', $stats['failed']],
                ]
            );

            if (!empty($stats['errors'])) {
                $this->newLine();
                $this->warn('Errors encountered:');
                foreach ($stats['errors'] as $error) {
                    $this->line("  • {$error}");
                }
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('✗ Synchronization failed!');
            $this->error('  ' . $e->getMessage());
            
            return self::FAILURE;
        }
    }
}
