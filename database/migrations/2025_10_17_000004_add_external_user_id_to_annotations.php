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
        Schema::table('annotations', function (Blueprint $table) {
            $table->foreignId('external_user_id')->nullable()->after('user_id')->constrained()->onDelete('cascade');
            $table->index('external_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('annotations', function (Blueprint $table) {
            $table->dropForeign(['external_user_id']);
            $table->dropColumn('external_user_id');
        });
    }
};
