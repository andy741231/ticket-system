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
            // Drop the foreign key first
            $table->dropForeign(['annotation_id']);
            
            // Make annotation_id nullable for image-level comments
            $table->foreignId('annotation_id')->nullable()->change();
            
            // Re-add the foreign key
            $table->foreign('annotation_id')->references('id')->on('annotations')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('annotation_comments', function (Blueprint $table) {
            // Drop the foreign key
            $table->dropForeign(['annotation_id']);
            
            // Make annotation_id not nullable again
            $table->foreignId('annotation_id')->nullable(false)->change();
            
            // Re-add the foreign key
            $table->foreign('annotation_id')->references('id')->on('annotations')->onDelete('cascade');
        });
    }
};
