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
        \DB::statement("ALTER TABLE `annotations` MODIFY COLUMN `type` ENUM('point', 'rectangle', 'circle', 'arrow', 'freehand', 'text', 'root_comment') NOT NULL DEFAULT 'point'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // First, delete any root_comment annotations to avoid data integrity issues
        \DB::table('annotations')->where('type', 'root_comment')->delete();
        
        // Then modify the column to remove the root_comment option
        \DB::statement("ALTER TABLE `annotations` MODIFY COLUMN `type` ENUM('point', 'rectangle', 'circle', 'arrow', 'freehand', 'text') NOT NULL DEFAULT 'point'");
    }
};
