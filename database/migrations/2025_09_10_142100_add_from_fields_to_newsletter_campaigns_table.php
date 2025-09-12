<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('newsletter_campaigns')) {
            Schema::table('newsletter_campaigns', function (Blueprint $table) {
                if (!Schema::hasColumn('newsletter_campaigns', 'from_name')) {
                    $table->string('from_name')->nullable()->after('preview_text');
                }
                if (!Schema::hasColumn('newsletter_campaigns', 'from_email')) {
                    $table->string('from_email')->nullable()->after('from_name');
                }
                if (!Schema::hasColumn('newsletter_campaigns', 'reply_to')) {
                    $table->string('reply_to')->nullable()->after('from_email');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('newsletter_campaigns')) {
            Schema::table('newsletter_campaigns', function (Blueprint $table) {
                if (Schema::hasColumn('newsletter_campaigns', 'reply_to')) {
                    $table->dropColumn('reply_to');
                }
                if (Schema::hasColumn('newsletter_campaigns', 'from_email')) {
                    $table->dropColumn('from_email');
                }
                if (Schema::hasColumn('newsletter_campaigns', 'from_name')) {
                    $table->dropColumn('from_name');
                }
            });
        }
    }
};
