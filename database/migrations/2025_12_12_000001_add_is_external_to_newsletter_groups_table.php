<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('newsletter_groups', function (Blueprint $table) {
            if (!Schema::hasColumn('newsletter_groups', 'is_external')) {
                $table->boolean('is_external')->default(false)->after('is_active');
                $table->index('is_external');
            }
        });
    }

    public function down(): void
    {
        Schema::table('newsletter_groups', function (Blueprint $table) {
            if (Schema::hasColumn('newsletter_groups', 'is_external')) {
                $table->dropIndex(['is_external']);
                $table->dropColumn('is_external');
            }
        });
    }
};
