<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Services\ProductImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductUpsertTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_correctly_upserts_and_returns_accurate_counts()
    {
        // ARRANGE: Setup an existing product
        Product::factory()->create([
            'sku' => 'EXISTING-001',
            'price' => 10.00
        ]);

        $batch = [
            // 1. Existing Product - Will be UPDATED
            [
                'sku' => 'EXISTING-001',
                'name' => 'Updated Product Name',
                'description' => 'New Description',
                'price' => 15.00,
            ],
            // 2. New Product - Will be INSERTED
            [
                'sku' => 'NEW-002',
                'name' => 'Brand New Product',
                'description' => 'A description.',
                'price' => 20.00,
            ],
            // 3. Another New Product - Will be INSERTED
            [
                'sku' => 'NEW-003',
                'name' => 'Product C',
                'description' => 'Another description.',
                'price' => 30.00,
            ]
        ];

        // ACT
        $service = new ProductImportService();
        $result = $service->upsertBatch($batch);

        // ASSERT: Check counts
        $this->assertEquals(3, count($batch));
        $this->assertEquals(2, $result['inserted'], 'Incorrect inserted count.');
        $this->assertEquals(1, $result['updated'], 'Incorrect updated count.');

        // ASSERT: Check Database State (Update check)
        $updatedProduct = Product::where('sku', 'EXISTING-001')->first();
        $this->assertEquals('Updated Product Name', $updatedProduct->name, 'Existing product name was not updated.');
        $this->assertEquals(15.00, $updatedProduct->price, 'Existing product price was not updated.');

        // ASSERT: Check Database State (Insert check)
        $newProductCount = Product::whereIn('sku', ['NEW-002', 'NEW-003'])->count();
        $this->assertEquals(2, $newProductCount, 'New products were not inserted.');

        // ASSERT: Total count in DB
        $this->assertEquals(3, Product::count(), 'Total product count is incorrect.');
    }
}
