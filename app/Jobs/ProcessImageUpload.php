<?php

namespace App\Jobs;

use App\Models\Image;
use App\Models\Product;
use App\Models\Upload;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use RuntimeException;
use Throwable;

class ProcessImageUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Job instance properties.
     */
    public function __construct(
        protected int $uploadRecordId,
        protected string $uploadedChecksum,
        protected string $targetProductSku,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $imageManager = new ImageManager(new Driver());
        $uploadRecord = Upload::findOrFail($this->uploadRecordId);

        // 1. Validate checksum integrity
        $rawImagePath = 'uploads/raw/' . $uploadRecord->file_name;
        $serverGeneratedChecksum = hash_file(
            'md5',
            Storage::disk($uploadRecord->disk)->path($rawImagePath)
        );

        if ($serverGeneratedChecksum !== $this->uploadedChecksum) {
            throw new RuntimeException(
                "Checksum mismatch for Upload ID: {$uploadRecord->id}. " .
                "Client: {$this->uploadedChecksum}, Server: {$serverGeneratedChecksum}"
            );
        }

        // Idempotency check — if image exists, just link it
        $existingImageRecord = Image::where('checksum', $serverGeneratedChecksum)->first();

        if ($existingImageRecord) {
            $this->assignImageToProduct($existingImageRecord, $this->targetProductSku);
            return;
        }

        // 2. Generate image variants
        $variantSizes = [256, 512, 1024];
        $generatedVariantPaths = [];
        $uniqueImageName = time() . '-' . hash('sha1', $uploadRecord->file_name);

        try {
            $originalContent = Storage::disk($uploadRecord->disk)->get($rawImagePath);
            $baseImage = $imageManager->read($originalContent);

            foreach ($variantSizes as $dimension) {
                $resizedImage = clone $baseImage;

                $resizedImage->resize($dimension, $dimension, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });

                $variantPath = "images/variants/{$dimension}/{$uniqueImageName}.jpg";
                Storage::disk('public')->put(
                    $variantPath,
                    $resizedImage->encode(new JpegEncoder(quality: 90))
                );

                $generatedVariantPaths["{$dimension}_path"] = $variantPath;
            }

            // 3. Store the processed image record
            $newImageRecord = Image::create([
                'upload_id'     => $uploadRecord->id,
                'original_path' => $rawImagePath,
                '256_path'      => $generatedVariantPaths['256_path'],
                '512_path'      => $generatedVariantPaths['512_path'],
                '1024_path'     => $generatedVariantPaths['1024_path'],
                'checksum'      => $serverGeneratedChecksum,
            ]);

            // 4. Attach processed image to product
            $this->assignImageToProduct($newImageRecord, $this->targetProductSku);

        } catch (Throwable $error) {
            throw $error;
        }
    }

    /**
     * Attach image to product safely.
     */
    protected function assignImageToProduct(Image $imageRecord, string $sku): void
    {
        DB::transaction(function () use ($imageRecord, $sku) {
            $productRecord = Product::where('sku', $sku)->firstOrFail();

            if ($productRecord->primary_image_id !== $imageRecord->id) {
                $productRecord->update(['primary_image_id' => $imageRecord->id]);
            }
        });
    }
}