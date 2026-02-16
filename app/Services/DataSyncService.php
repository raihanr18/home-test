<?php

namespace App\Services;

use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DataSyncService
{
    /**
     * Sync result statistics
     */
    public array $stats = [
        'total' => 0,
        'created' => 0,
        'updated' => 0,
        'failed' => 0,
        'errors' => [],
    ];

    /**
     * Fetch data from external API and sync to database
     *
     * @return array Sync statistics
     * @throws \Exception
     */
    public function fetchAndSync(): array
    {
        Log::info('Starting student data sync from external API');

        try {
            // Fetch data from external endpoint
            $data = $this->fetchDataFromApi();

            // Parse the pipe-delimited data
            $records = $this->parseData($data);

            // Sync to database
            $this->syncToDatabase($records);

            Log::info('Student data sync completed successfully', $this->stats);

            return $this->stats;
        } catch (\Exception $e) {
            Log::error('Student data sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Fetch data from external API
     *
     * @return string The DATA field from API response
     * @throws \Exception
     */
    protected function fetchDataFromApi(): string
    {
        $url = config('services.external_api.url');
        $timeout = config('services.external_api.timeout', 30);

        if (empty($url)) {
            throw new \Exception('External API URL is not configured. Set EXTERNAL_API_URL in .env');
        }

        try {
            $response = Http::timeout($timeout)
                ->withOptions([
                    // NOTE: SSL verification disabled for development only.
                    // For production, remove this option or set 'verify' => true
                    'verify' => false
                ])
                ->get($url);

            if (!$response->successful()) {
                throw new \Exception("API request failed with status {$response->status()}");
            }

            $json = $response->json();

            if (!isset($json['RC']) || $json['RC'] != 200) {
                throw new \Exception("API returned error response: " . ($json['RCM'] ?? 'Unknown error'));
            }

            if (!isset($json['DATA'])) {
                throw new \Exception("API response missing DATA field");
            }

            return $json['DATA'];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw new \Exception("Failed to connect to external API: " . $e->getMessage());
        } catch (\Illuminate\Http\Client\RequestException $e) {
            throw new \Exception("API request failed: " . $e->getMessage());
        }
    }

    /**
     * Parse pipe-delimited data string
     *
     * @param string $data Raw pipe-delimited data with header
     * @return Collection Collection of parsed student records
     */
    public function parseData(string $data): Collection
    {
        $lines = explode("\n", trim($data));

        // Remove header row
        $header = array_shift($lines);

        if ($header !== 'YMD|NIM|NAMA') {
            Log::warning('Unexpected header format', ['header' => $header]);
        }

        $records = collect();

        foreach ($lines as $lineNumber => $line) {
            $line = trim($line);

            // Skip empty lines
            if (empty($line)) {
                continue;
            }

            $fields = explode('|', $line);

            // Validate field count
            if (count($fields) !== 3) {
                $this->stats['failed']++;
                $this->stats['errors'][] = "Line " . ($lineNumber + 2) . ": Invalid field count - " . $line;
                Log::warning('Skipping line with invalid field count', [
                    'line_number' => $lineNumber + 2,
                    'line' => $line,
                ]);
                continue;
            }

            [$ymd, $nim, $nama] = $fields;

            // Validate and transform data
            try {
                $record = $this->validateAndTransform($nama, $nim, $ymd, $lineNumber + 2);
                $records->push($record);
                $this->stats['total']++;
            } catch (\Exception $e) {
                $this->stats['failed']++;
                $this->stats['errors'][] = "Line " . ($lineNumber + 2) . ": " . $e->getMessage();
                Log::warning('Skipping invalid record', [
                    'line_number' => $lineNumber + 2,
                    'error' => $e->getMessage(),
                    'data' => compact('nama', 'nim', 'ymd'),
                ]);
            }
        }

        return $records;
    }

    /**
     * Validate and transform a single record
     *
     * @param string $nama Student name
     * @param string $nim Student ID
     * @param string $ymd Birth date in YYYYMMDD format
     * @param int $lineNumber Line number for error reporting
     * @return array Validated and transformed record
     * @throws \Exception
     */
    protected function validateAndTransform(string $nama, string $nim, string $ymd, int $lineNumber): array
    {
        // Validate nama
        $nama = trim($nama);
        if (empty($nama)) {
            throw new \Exception("Empty nama field");
        }
        if (strlen($nama) > 255) {
            throw new \Exception("Nama exceeds 255 characters");
        }

        // Validate NIM
        $nim = trim($nim);
        if (!preg_match('/^\d{10}$/', $nim)) {
            throw new \Exception("NIM must be exactly 10 digits (got: {$nim})");
        }

        // Transform date
        try {
            $tanggalLahir = $this->transformDate($ymd);
        } catch (\Exception $e) {
            throw new \Exception("Invalid date format for YMD '{$ymd}': " . $e->getMessage());
        }

        return [
            'nama' => $nama,
            'nim' => $nim,
            'tanggal_lahir' => $tanggalLahir,
        ];
    }

    /**
     * Transform date from YYYYMMDD to Y-m-d format
     *
     * @param string $ymd Date in YYYYMMDD format
     * @return string Date in Y-m-d format
     * @throws \Exception
     */
    protected function transformDate(string $ymd): string
    {
        $ymd = trim($ymd);

        if (strlen($ymd) !== 8) {
            throw new \Exception("Date must be 8 digits (YYYYMMDD format)");
        }
        
        if (!ctype_digit($ymd)) {
            throw new \Exception("Date must contain only digits");
        }

        try {
            $date = Carbon::createFromFormat('Ymd', $ymd);
            
            // Validate that the date is a real calendar date
            $errors = Carbon::getLastErrors();
            if ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                throw new \Exception("Invalid calendar date");
            }
            
            return $date->format('Y-m-d');
        } catch (\Exception $e) {
            throw new \Exception("Cannot parse date: {$ymd}");
        }
    }

    /**
     * Sync records to database using upsert
     *
     * @param Collection $records Collection of student records
     * @return void
     */
    protected function syncToDatabase(Collection $records): void
    {
        if ($records->isEmpty()) {
            Log::info('No valid records to sync');
            return;
        }

        try {
            DB::transaction(function () use ($records) {
                // Get existing NIMs before upsert
                $nims = $records->pluck('nim');
                $existingNims = Student::whereIn('nim', $nims)->pluck('nim')->toArray();
                
                $this->stats['updated'] = count($existingNims);
                $this->stats['created'] = $this->stats['total'] - $this->stats['updated'];

                // Perform upsert - update existing or create new based on nim
                Student::upsert(
                    $records->toArray(),
                    ['nim'], // Unique key
                    ['nama', 'tanggal_lahir', 'updated_at'] // Fields to update
                );

                Log::info('Database upsert completed', [
                    'total_records' => $records->count(),
                    'created' => $this->stats['created'],
                    'updated' => $this->stats['updated'],
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Database sync failed', [
                'error' => $e->getMessage(),
                'records_count' => $records->count(),
            ]);
            throw new \Exception("Database sync failed: " . $e->getMessage());
        }
    }
}
