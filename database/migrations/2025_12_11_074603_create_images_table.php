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
        Schema::create('images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('upload_id')->constrained('uploads')->onDelete('cascade');
            $table->string('original_path'); // Path to the assembled, original file
            $table->string('256_path');      // Path to 256px variant
            $table->string('512_path');      // Path to 512px variant
            $table->string('1024_path');     // Path to 1024px variant
            $table->string('checksum')->unique(); // Redundant check, ensuring unique image content
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('images');
    }
};
