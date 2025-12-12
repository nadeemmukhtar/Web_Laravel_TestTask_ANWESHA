<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Senior Assessment Demo Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.3/dropzone.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.3/dropzone.min.js"></script>
    <style>
        /* Minimal CSS for presentation */
        body {
            font-family: sans-serif;
            padding: 20px;
            background-color: #f4f4f9;
        }

        .container {
            max-width: 900px;
            margin: auto;
        }

        .card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .success {
            color: green;
            font-weight: bold;
        }

        .error {
            color: red;
            font-weight: bold;
        }
    </style>
</head>

<body>

    <div class="container">
        <h1 style="color: #3b82f6;">Laravel Senior Developer Assessment Demonstration</h1>

        @if (session('status'))
            <div class="card success">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="card error">{{ session('error') }}</div>
        @endif

        <!---------------------------------------------------->
        <!-- SECTION 1: Task A - Bulk Import (Synchronous Form Submit) -->
        <!---------------------------------------------------->
        <div class="card">
            <h2>1. Bulk CSV Import (≥ 10,000 Rows)</h2>
            <p>Trigger the asynchronous queue job for CSV upsert and reporting.</p>

            @if (!$csvExists)
                <div class="error">**MOCK FILE MISSING**: Run <code>php artisan generate:mock-csv</code></div>
            @else
                <form action="{{ url('/imports/trigger') }}" method="POST">
                    @csrf
                    <button type="submit"
                        style="background-color:#10b981; color:white; padding: 10px 15px; border:none; border-radius:4px; cursor: pointer;">
                        Start Asynchronous Bulk Import Job
                    </button>
                </form>
            @endif

            @if ($latestSummary)
                <h3 style="margin-top: 20px; border-top: 1px solid #ccc; padding-top: 10px;">Latest Report Summary:</h3>
                <p><strong>Key:</strong> {{ $latestSummary->key }} (Status: <span
                        style="font-weight: bold;">{{ ucfirst($latestSummary->status) }}</span>)</p>
                <p>Total Attempted (T+I): {{ $latestSummary->total_count + $latestSummary->invalid_count }}</p>
                <p style="color: blue;">Imported (New): {{ $latestSummary->imported_count }}</p>
                <p style="color: orange;">Updated: {{ $latestSummary->updated_count }}</p>
                <p style="color: red;">Invalid Rows: {{ $latestSummary->invalid_count }}</p>
            @endif
        </div>

        <!---------------------------------------------------->
        <!-- SECTION 2: Task B - Chunked Drag-and-Drop Image Upload (Livewire) -->
        <!---------------------------------------------------->
        @if ($productToLink)
            <div class="card">
                <h2>2. Chunked Image Upload (SKU: {{ $productToLink->sku }})</h2>
                <p>Demonstrates client-side chunking, server-side checksum, variant generation, and Idempotency.</p>
                <p style="color:#f97316;">Upload a **large** image (10MB+) to verify chunking and variant creation.</p>

                @include('chunked-upload', ['productSku' => $productToLink->sku])
            </div>
        @else
            <div class="card error">No Product found in database to link an image to (SKU TEST-000 not created).</div>
        @endif
    </div>
</body>

</html>
