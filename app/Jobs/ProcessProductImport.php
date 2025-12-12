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

    protected int $chunkSize = 1000;
    protected array $mandatoryColumns = ['sku', 'name', 'price', 'description'];

    public function __construct(
        protected string $sourceFilePath,
        protected string $summaryReference
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $chunkSize = $this->chunkSize;
        $requiredColumns = ['sku', 'name', 'price']; // same behavior as your original
        $absoluteFilePath = Storage::path($this->sourceFilePath);

        $importService = new ProductImportService;
        $pendingBatchRows = [];

        try {
            $csvStream = SimpleExcelReader::create($absoluteFilePath)->getRows();

            $csvStream->each(function (array $row) use (&$pendingBatchRows, $requiredColumns, $importService) {

                // 1. Validate required columns
                $missingColumns = array_diff($requiredColumns, array_keys(array_filter($row)));

                if (!empty($missingColumns) || empty($row['sku'] ?? null)) {

                    // Count invalid row
                    DB::table('import_summaries')
                        ->where('key', $this->summaryReference)
                        ->increment('invalid_count');

                    // Always increment total count
                    DB::table('import_summaries')
                        ->where('key', $this->summaryReference)
                        ->increment('total_count');

                    return;
                }

                // Valid row → add to batch
                $pendingBatchRows[] = $row;

                DB::table('import_summaries')
                    ->where('key', $this->summaryReference)
                    ->increment('total_count');

                // 2. When chunk size is reached → process batch
                if (count($pendingBatchRows) >= $this->chunkSize) {
                    $this->applyBatchAndUpdateSummary($importService, $pendingBatchRows);
                    $pendingBatchRows = [];
                }
            });

            // 3. Final partial batch
            if (!empty($pendingBatchRows)) {
                $this->applyBatchAndUpdateSummary($importService, $pendingBatchRows);
            }

            // Import completed
            DB::table('import_summaries')
                ->where('key', $this->summaryReference)
                ->update([
                    'status'       => 'completed',
                    'completed_at' => now(),
                ]);

        } catch (Throwable $exception) {
            DB::table('import_summaries')
                ->where('key', $this->summaryReference)
                ->update(['status' => 'failed']);

            throw $exception;
        }
    }

    /**
     * Processes a single batch and updates summary counters.
     */
    protected function applyBatchAndUpdateSummary(ProductImportService $importService, array $batchRows): void
    {
        $batchStats = $importService->upsertBatch($batchRows);

        DB::table('import_summaries')
            ->where('key', $this->summaryReference)
            ->update([
                'imported_count' => DB::raw("imported_count + {$batchStats['inserted']}"),
                'updated_count'  => DB::raw("updated_count + {$batchStats['updated']}"),
            ]);
    }
}