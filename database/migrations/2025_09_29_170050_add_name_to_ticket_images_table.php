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
        Schema::table('ticket_images', function (Blueprint $table) {
            $table->string('name')->nullable()->after('original_name')->comment('User-provided name for the proof');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_images', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};
