<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->longText('content'); // JSON structure for drag-and-drop builder
            $table->longText('html_content'); // Compiled HTML
            $table->string('thumbnail')->nullable(); // Preview image path
            $table->boolean('is_default')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index('is_default');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_templates');
    }
};
