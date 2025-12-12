This Readme file is designed to be clear, professional, and directly address the goals of the Senior Developer Assessment, making it easy for the client or next developer to set up and verify.

Bulk Import & Chunked Upload:
    This project implements two core asynchronous services essential for a modern, high-volume data platform: Concurrency-Safe Bulk CSV Data Upsert and Idempotent Chunked/Resumable Image Upload with Real-time Media Processing.
    Domain	Products	Unique Identifier	SKU
    Backend	Laravel 12+	Frontend Alpine.js + Custom Checksum
    Concurrency	Queues (default, images) Integrity Database Transactions & Checksum Validation

1. Features Implemented
    All constraints and acceptance criteria defined in the assessment document have been met.
    A. Bulk CSV Import (Task A)
        Asynchronous Processing: 
        ≥ 10,000 rows are processed safely in 1,000-row batches using Laravel Queues, avoiding PHP timeout/memory errors.
        Upsert Logic: Product records are either created (new SKU) or updated (existing SKU).
        Concurrency-Safe Reporting: The final report summary (Total, Imported, Updated, Invalid) is aggregated into the dedicated import_summaries table using atomic increments, ensuring 100% accuracy across multiple parallel workers.
        Validation: Missing required columns (e.g., SKU, price) result in the row being flagged as Invalid and skipped, without halting the entire import job.
    B. Chunked Image Upload (Task B)
        Chunking & Resumability: Utilizes Livewire's built-in file upload manager to handle large files in chunks, providing inherent resumability and handling for temporary network failures.
        Integrity Check: A client-side checksum (MD5) is calculated, transmitted, and verified against the server-side hash before any processing. A mismatch blocks completion.
        Background Processing: Finalized image files are dispatched to a separate images queue for dedicated variant generation.
        Idempotency & Concurrency: Image variant creation is checked by Checksum, ensuring the same image is never processed twice (re-attaching the same upload = no-op). Primary image linkage uses a database transaction with a pessimistic lock to ensure only one worker modifies the link at a time.
        Variants: Optimized variants (256px, 512px, 1024px) are created via Intervention Image V3, with guaranteed aspect ratio preservation.
        Real-Time UX: A combined dashboard uses Livewire Polling to display the real-time processing status of the asynchronous queue job.

2. Setup Guide
    Configure your database connection.

    Database & Mock Data:
    php artisan migrate 
    php artisan generate:mock-csv 
    php artisan tinker
    App\Models\Product::create(['sku' => 'TEST-000', 'name' => 'Demo Product', 'price' => 10.00]);

3. Demonstration & Testing
    This demo requires two active terminals running simultaneously.
    A. Start Services (3 Terminals)
        Terminal Command Role
            1: Web Server	php artisan serve	Serves the UI.
            2: Main Worker	php artisan queue:work --tries=3
    
        Open Browser: Navigate to the Administration Dashboard: http://localhost:8000/admin/dashboard
        Action: Click the "Start Asynchronous Bulk Import Job" button.

        Verify Batching & Concurrency:
        Observe Terminal 2. The worker immediately processes the job in batches.
        Refresh the browser. The Report Summary updates instantly, showing Invalid Rows (> 0), Imported (> 9k), and Updated (≥ 3), proving the concurrent aggregation is accurate.

        Verify Upsert: 
        (Via Tinker) Confirm that SKU PROD-00001 (if manually set/updated by the mock) reflects the last update in the CSV.

    B. Chunked Upload & Integrity
        Dashboard Section 2: Go to the Image Upload area (using TEST-000 SKU).
        Action: Drag a large file (≥10 MB) into the drop zone.

        Verify Chunking: Observe the upload progress bar. This confirms the large file is sent in chunks.
        Verify Background Processing:
        When the upload is complete, the status moves to the "Pending Processing Status" section (using Livewire polling).
        Observe Terminal 3 (Image Worker). It shows the job being processed.
        Verify Success: After a few seconds, the job disappears from the 'Pending' section, and the new image variants appear in the Gallery below, with one image marked as PRIMARY.
        Verify Idempotency: Drag and drop the EXACT SAME large image again.
        The upload will occur, but the image processing will complete instantly. The product's Primary Image ID will not change in the database (No-op Rule Met).