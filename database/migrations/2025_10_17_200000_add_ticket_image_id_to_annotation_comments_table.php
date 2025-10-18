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
        Schema::table('annotation_comments', function (Blueprint $table) {
            // Add ticket_image_id for image-level comments (when annotation_id is null)
            $table->foreignId('ticket_image_id')->nullable()->after('annotation_id');
            $table->foreign('ticket_image_id')->references('id')->on('ticket_images')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('annotation_comments', function (Blueprint $table) {
            $table->dropForeign(['ticket_image_id']);
            $table->dropColumn('ticket_image_id');
        });
    }
};
