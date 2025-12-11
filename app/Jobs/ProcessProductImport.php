<?php

namespace App\Jobs;

use App\Services\ProductImportService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\SimpleExcel\SimpleExcelReader;
use Throwable;

class ProcessProductImport implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    protected int $batchSize = 1000;

    protected array $requiredCols = ['sku', 'name', 'price', 'description'];

    public function __construct(
        protected string $filePath,
        protected string $summaryKey
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // The fixed property is now available here:
        $batchSize = $this->batchSize; // Should be 1000
        $requiredCols = ['sku', 'name', 'price']; // Or $this->requiredCols
        $file = Storage::path($this->filePath);
        $service = new ProductImportService;

        // Data buffers
        $rowsToUpsert = [];

        // The entire CSV is read once in a stream for low memory usage
        try {
            $reader = SimpleExcelReader::create($file)->getRows();

            $reader->each(function (array $row) use (&$rowsToUpsert, $service, $requiredCols) {

                // 1. Initial Validation: Check for required columns
                $missingCols = array_diff($requiredCols, array_keys(array_filter($row)));

                // Validation Rule: Check for required columns, and record invalid count
                if (! empty($missingCols) || ! isset($row['sku']) || empty($row['sku'])) {
                    // Rule: Missing columns = invalid rows, but do not stop import.

                    // Atomically update global invalid count
                    DB::table('import_summaries')
                        ->where('key', $this->summaryKey)
                        ->increment('invalid_count');

                    DB::table('import_summaries')->where('key', $this->summaryKey)->increment('total_count');

                    return; // Skip invalid row
                }

                // 2. Add row to current batch and increment total count for valid rows
                $rowsToUpsert[] = $row;
                DB::table('import_summaries')->where('key', $this->summaryKey)->increment('total_count');

                // 3. Process the Batch and Dispatch the Work (When limit is hit)
                if (count($rowsToUpsert) >= $this->batchSize) {
                    // Dispatch or directly execute (execution is better as it simplifies reporting)
                    $this->processBatchAndUpdateSummary($service, $rowsToUpsert);
                    $rowsToUpsert = []; // Reset the batch buffer
                }
            });

            // Process any remaining partial batch
            if (! empty($rowsToUpsert)) {
                $this->processBatchAndUpdateSummary($service, $rowsToUpsert);
            }

            // Mark as completed
            DB::table('import_summaries')->where('key', $this->summaryKey)->update(['status' => 'completed', 'completed_at' => now()]);

        } catch (Throwable $e) {
            // Mark as failed and throw to Laravel for failed job handling/retries
            DB::table('import_summaries')->where('key', $this->summaryKey)->update(['status' => 'failed']);
            throw $e;
        }
    }

    // ⭐️ Note: This private method updates the summary table after each 1000-row execution.
    protected function processBatchAndUpdateSummary(ProductImportService $service, array $batch): void
    {
        $batchResult = $service->upsertBatch($batch);

        // Atomically increment final report counts
        DB::table('import_summaries')
            ->where('key', $this->summaryKey)
            ->update([
                'imported_count' => DB::raw("imported_count + {$batchResult['inserted']}"),
                'updated_count' => DB::raw("updated_count + {$batchResult['updated']}"),
            ]);
    }
}
