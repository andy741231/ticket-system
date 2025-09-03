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
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('campaign_analytics');
        Schema::dropIfExists('mail_lists');
        Schema::dropIfExists('mail_list_subscriber');
        Schema::dropIfExists('subscribers');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // These tables can't be recreated here as we don't have their original schema
        // A database backup should be restored if these tables need to be recovered
    }
};
