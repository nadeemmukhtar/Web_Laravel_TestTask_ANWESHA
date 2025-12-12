<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class GenerateMockData extends Command
{
    protected $signature = 'generate:mock-csv {count=10000}';

    protected $description = 'Generates a mock CSV file for product import testing (min 10,000 records).';

    public function handle(): int
    {
        $count = max(10000, (int) $this->argument('count'));
        $filePath = 'mock_import.csv';

        // Use a file handle for efficient, low-memory writing of large data
        $fullPath = Storage::path($filePath);
        $fileHandle = fopen($fullPath, 'w');

        if (! $fileHandle) {
            $this->error("Failed to open file handle at: {$fullPath}");

            return Command::FAILURE;
        }

        $headers = "sku,name,description,price\n";
        fwrite($fileHandle, $headers);
        $this->output->write("Generating $count rows...");

        $totalInvalid = 0;

        for ($i = 1; $i <= $count; $i++) {
            $sku = 'PROD-'.str_pad($i, 5, '0', STR_PAD_LEFT);
            $name = 'Product Model '.$i;
            $description = "Description for product {$i}";
            $price = number_format(rand(10, 5000) + (rand(0, 99) / 100), 2, '.', '');

            $rowData = "$sku,\"$name\",\"$description\",$price\n";

            // 1. Invalid Row Sample (Rule: Missing columns = invalid rows)
            if ($i % 997 == 0 && $totalInvalid < 50) { // Add an invalid row every ~1000 items
                $rowData = "$sku,\"$name\",,"; // Missing both 'description' and 'price'
                $totalInvalid++;
            }

            fwrite($fileHandle, $rowData);

            // 2. Update Sample (Upsert Test): Create a row to be UPDATED later in the same batch (or in next imports)
            if ($i == 1 || $i == 5000 || $i == 9999) {
                $updateName = 'UPDATED Product '.$i;
                $updatePrice = number_format(rand(5000, 9999) + (rand(0, 99) / 100), 2, '.', '');
                fwrite($fileHandle, "$sku,\"$updateName\",\"$description\",$updatePrice\n");
            }

            // Update the terminal progress line
            if ($i % 500 == 0) {
                $this->output->write("...$i rows generated\r");
            }
        }

        fclose($fileHandle);

        $this->info("Generation complete! Mock CSV saved to storage/app/private/$filePath with $count base rows and test samples.");

        return Command::SUCCESS;
    }
}
