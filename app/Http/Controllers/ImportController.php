<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessImageUpload;
use App\Jobs\ProcessProductImport;
use App\Models\Product;
use App\Models\Upload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ImportController extends Controller
{
    /**
     * Display the dashboard with the latest import information.
     */
    public function index()
    {
        $latestImportSummary = DB::table('import_summaries')->latest('id')->first();
        $defaultProduct = Product::where('sku', 'TEST-000')->first() 
                           ?? Product::latest('id')->first();
        $isCsvAvailable = Storage::exists('mock_import.csv');

        return view('dashboard', [
            'latestSummary' => $latestImportSummary,
            'productToLink' => $defaultProduct,
            'csvExists'     => $isCsvAvailable,
        ]);
    }

    /**
     * Start the background CSV import task.
     */
    public function triggerImport(Request $request)
    {
        if (! Storage::exists('mock_import.csv')) {
            return back()->with('error', 'Mock CSV file not found. Run artisan command first.');
        }

        $importKey = 'import-' . time() . '-' . uniqid();

        // Create pending summary record
        DB::table('import_summaries')->insert([
            'key'         => $importKey,
            'status'      => 'pending',
            'total_count' => 0,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // Dispatch async job
        ProcessProductImport::dispatch('mock_import.csv', $importKey);

        return back()->with('status', 'Product Import Job Dispatched! Summary Key: ' . $importKey);
    }

    /**
     * Handle single image upload and trigger image processing job.
     */
    public function uploadImage(Request $request)
    {
        $request->validate([
            'file'        => 'required|image|max:204800',
            'checksum'    => 'required|string',
            'product_sku' => 'required|string',
        ]);

        $imageFile = $request->file('file');
        $generatedFileName = time() . '-' . $imageFile->getClientOriginalName();

        $storedPath = $imageFile->storeAs('uploads/raw', $generatedFileName, 'public');

        $uploadRecord = Upload::create([
            'file_name'     => $generatedFileName,
            'mime_type'     => $imageFile->getClientMimeType(),
            'file_size'     => $imageFile->getSize(),
            'file_checksum' => $request->input('checksum'),
            'disk'          => 'public',
            'is_completed'  => true,
        ]);

        ProcessImageUpload::dispatch(
            $uploadRecord->id,
            $request->input('checksum'),
            $request->input('product_sku')
        );

        return response()->json([
            'success'    => true,
            'upload_id'  => $uploadRecord->id,
            'file_name'  => $generatedFileName,
        ]);
    }
}