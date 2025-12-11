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
     * Renders the main dashboard with data for the UI components.
     */
    public function index()
    {
        $latestSummary = DB::table('import_summaries')->latest('id')->first();
        $productToLink = Product::where('sku', 'TEST-000')->first() ?? Product::latest('id')->first();
        $csvExists = Storage::exists('mock_import.csv');

        return view('dashboard', compact('latestSummary', 'productToLink', 'csvExists'));
    }

    /**
     * Triggers the asynchronous CSV import job (Action from the UI form).
     */
    public function triggerImport(Request $request)
    {
        if (! Storage::exists('mock_import.csv')) {
            return back()->with('error', 'Mock CSV file not found. Run artisan command first.');
        }

        $summaryKey = 'import-'.time().'-'.uniqid();

        // 1. Create a PENDING summary record
        DB::table('import_summaries')->insert([
            'key' => $summaryKey,
            'status' => 'pending',
            'total_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Dispatch the job (async operation)
        ProcessProductImport::dispatch('mock_import.csv', $summaryKey);

        return back()->with('status', 'Product Import Job Dispatched! Summary Key: '.$summaryKey);
    }

    public function uploadImage(Request $request)
    {
        $request->validate([
            'file' => 'required|image|max:204800',
            'checksum' => 'required|string',
            'product_sku' => 'required|string',
        ]);

        $uploadedFile = $request->file('file');
        $filename = time().'-'.$uploadedFile->getClientOriginalName();

        $filePath = $uploadedFile->storeAs('uploads/raw', $filename, 'public');

        $upload = Upload::create([
            'file_name' => $filename,
            'mime_type' => $uploadedFile->getClientMimeType(),
            'file_size' => $uploadedFile->getSize(),
            'file_checksum' => $request->input('checksum'),
            'disk' => 'public',
            'is_completed' => true,
        ]);

        ProcessImageUpload::dispatch($upload->id, $request->input('checksum'), $request->input('product_sku'));

        return response()->json([
            'success' => true,
            'upload_id' => $upload->id,
            'file_name' => $filename,
        ]);
    }
}
