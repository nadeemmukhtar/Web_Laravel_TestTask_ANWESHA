<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProductImportService
{
    public function upsertBatch(array $batchRows): array
    {
        if (empty($batchRows)) {
            return ['inserted' => 0, 'updated' => 0, 'duplicates' => 0];
        }

        $currentTimestamp = Carbon::now();
        $preparedRows = [];

        foreach ($batchRows as $csvRow) {
            $preparedRows[] = [
                'sku'         => $csvRow['sku'],
                'name'        => $csvRow['name'],
                'description' => $csvRow['description'],
                'price'       => (float) $csvRow['price'],
                'created_at'  => $currentTimestamp,
                'updated_at'  => $currentTimestamp,
            ];
        }

        // --- Perform UPSERT ---
        $affectedCount = DB::table('products')->upsert(
            $preparedRows,
            ['sku'], // Unique Key
            ['name', 'description', 'price', 'updated_at']
        );

        $totalRows = count($batchRows);
        $currentSkus = array_column($preparedRows, 'sku');

        // Count existing before upsert
        $existingBefore = DB::table('products')
            ->whereIn('sku', $currentSkus)
            ->count();

        // Count again after upsert
        $existingAfter = DB::table('products')
            ->whereIn('sku', $currentSkus)
            ->count();

        // How many new rows inserted?
        $newlyInsertedCount = $existingAfter - $existingBefore;

        // Count how many rows got updated
        $rowsUpdatedAtTime = DB::table('products')
            ->whereIn('sku', $currentSkus)
            ->where('updated_at', $currentTimestamp)
            ->count();

        $inserted = $newlyInsertedCount;
        $updated = $rowsUpdatedAtTime - $newlyInsertedCount;

        return [
            'inserted'   => $inserted,
            'updated'    => $updated,
            'duplicates' => 0,
        ];
    }
}