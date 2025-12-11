<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('import_summaries', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique()->comment('A unique identifier for this import run (e.g., UUID or timestamped slug)');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->unsignedInteger('total_count')->default(0)->comment('Total rows read from the CSV (valid + invalid)');
            $table->unsignedInteger('imported_count')->default(0)->comment('New database records created (Upsert -> Insert)');
            $table->unsignedInteger('updated_count')->default(0)->comment('Existing database records updated (Upsert -> Update)');
            $table->unsignedInteger('invalid_count')->default(0)->comment('Rows skipped due to validation failure (e.g., missing columns)');
            $table->unsignedInteger('duplicates_count')->default(0)->comment('Duplicates found *within* the input CSV file');
            $table->timestamps();
            $table->timestamp('completed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_summaries');
    }
};
