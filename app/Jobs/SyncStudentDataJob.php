<?php

namespace App\Jobs;

use App\Services\DataSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncStudentDataJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public int $timeout = 300;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(DataSyncService $syncService): void
    {
        Log::info('SyncStudentDataJob started', [
            'attempt' => $this->attempts(),
        ]);

        try {
            $stats = $syncService->fetchAndSync();

            Log::info('SyncStudentDataJob completed successfully', [
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error('SyncStudentDataJob failed', [
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            // Let Laravel handle retry logic based on $tries
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('SyncStudentDataJob failed permanently after all retries', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
