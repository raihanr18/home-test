<?php

namespace App\Http\Controllers;

use App\Http\Requests\SyncRequest;
use App\Jobs\SyncStudentDataJob;
use App\Services\DataSyncService;
use Illuminate\Http\JsonResponse;

class SyncController extends Controller
{
    /**
     * Trigger student data synchronization from external API.
     *
     * @param SyncRequest $request
     * @param DataSyncService $syncService
     * @return JsonResponse
     */
    public function store(SyncRequest $request, DataSyncService $syncService): JsonResponse
    {
        $async = $request->input('async', false);

        if ($async) {
            // Dispatch job to queue for async processing
            SyncStudentDataJob::dispatch();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Sync job dispatched to queue',
                'async' => true,
            ], 202);
        }

        // Run sync synchronously
        try {
            $stats = $syncService->fetchAndSync();

            return response()->json([
                'status' => 'success',
                'message' => 'Sync completed successfully',
                'async' => false,
                'stats' => $stats,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sync failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
