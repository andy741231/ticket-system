<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('newsletter_templates')) {
            Schema::table('newsletter_templates', function (Blueprint $table) {
                if (!Schema::hasColumn('newsletter_templates', 'from_name')) {
                    $table->string('from_name')->nullable()->after('html_content');
                }
                if (!Schema::hasColumn('newsletter_templates', 'from_email')) {
                    $table->string('from_email')->nullable()->after('from_name');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('newsletter_templates')) {
            Schema::table('newsletter_templates', function (Blueprint $table) {
                if (Schema::hasColumn('newsletter_templates', 'from_email')) {
                    $table->dropColumn('from_email');
                }
                if (Schema::hasColumn('newsletter_templates', 'from_name')) {
                    $table->dropColumn('from_name');
                }
            });
        }
    }
};
