<?php

namespace App\Jobs;

// use App\Models\Image;

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
use Intervention\Image\Interfaces\EncoderInterface;
use Intervention\Image\Encoders\JpegEncoder;
use RuntimeException;

class ProcessImageUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected int $uploadId,
        protected string $clientChecksum,
        protected string $productSku,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $manager = new ImageManager(new Driver());
        $upload = Upload::findOrFail($this->uploadId);

        // 1. Checksum Validation (Rule: Checksum mismatch blocks completion)
        $rawPath = 'uploads/raw/' . $upload->file_name;

        $serverChecksum = hash_file('md5', Storage::disk($upload->disk)->path($rawPath));
        if ($serverChecksum !== $this->clientChecksum) {
            throw new RuntimeException("Checksum mismatch for Upload ID: {$upload->id}. Client: {$this->clientChecksum}, Server: {$serverChecksum}");
        }

        // Checksum Validation complete. Now, handle Idempotency (Rule: Re-attaching the same upload = no-op)
        $existingImage = Image::where('checksum', $serverChecksum)->first();
        if ($existingImage) {
            $this->linkImageToProduct($existingImage, $this->productSku);
            return;
        }

        // 2. Image Variant Generation (256px, 512px, 1024px)
        $variants = [256, 512, 1024];
        $variantPaths = [];
        $imageHash = time() . '-' . hash('sha1', $upload->file_name); // Unique name

        try {
            $originalImageContent = Storage::disk($upload->disk)->get($rawPath);
            $image = $manager->read($originalImageContent);

            foreach ($variants as $size) {
                // Rule: Variants must respect aspect ratio (fit)
                $variantImage = clone $image;
                $variantImage->resize($size, $size, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize(); // Only upscale if smaller
                });

                $path = "images/variants/{$size}/{$imageHash}.jpg";
                Storage::disk('public')->put($path, $variantImage->encode(new JpegEncoder(quality: 90)));
                $variantPaths["{$size}_path"] = $path;
            }

            // 3. Store the Image record
            $imageRecord = Image::create([
                'upload_id' => $upload->id,
                'original_path' => $rawPath,
                '256_path' => $variantPaths['256_path'],
                '512_path' => $variantPaths['512_path'],
                '1024_path' => $variantPaths['1024_path'],
                'checksum' => $serverChecksum,
            ]);

            // 4. Link primary image to the entity
            $this->linkImageToProduct($imageRecord, $this->productSku);

        } catch (\Throwable $e) {
            // Handle image processing failure
            throw $e;
        } finally {
            // Optional: Clean up the original raw upload if not needed
            // Storage::disk($upload->disk)->delete($rawPath);
        }
    }

    protected function linkImageToProduct(Image $image, string $sku): void
    {
        DB::transaction(function () use ($image, $sku) {
            $product = Product::where('sku', $sku)->firstOrFail();
            
            // Concurrency Safe check + Idempotency (Rule: Primary image replacement is idempotent)
            if ($product->primary_image_id !== $image->id) {
                $product->update(['primary_image_id' => $image->id]);
            }
        });
    }
}
