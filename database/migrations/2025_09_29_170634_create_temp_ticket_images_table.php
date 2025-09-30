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
        Schema::create('temp_ticket_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('source_type')->comment('url, file, or newsletter');
            $table->text('source_value')->nullable()->comment('URL or original file path');
            $table->string('image_path')->comment('Path to generated/processed image');
            $table->string('original_name')->nullable();
            $table->string('name')->nullable()->comment('User-provided name for the proof');
            $table->string('mime_type')->default('image/png');
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->enum('status', ['processing', 'completed', 'failed'])->default('processing');
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable()->comment('Additional data like viewport size, etc.');
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index('source_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('temp_ticket_images');
    }
};
