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
            $table->boolean('created_by_public')->default(false)->after('user_id');
            $table->json('public_user_info')->nullable()->after('created_by_public');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('annotations', function (Blueprint $table) {
            $table->dropColumn(['created_by_public', 'public_user_info']);
        });
    }
};
