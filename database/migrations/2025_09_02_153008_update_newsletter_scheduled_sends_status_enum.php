<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, update the enum values to include all required statuses
        DB::statement("ALTER TABLE newsletter_scheduled_sends 
            MODIFY COLUMN status ENUM('pending', 'processing', 'sent', 'failed', 'cancelled', 'skipped') 
            NOT NULL DEFAULT 'pending'");
            
        // Update any existing 'failed' status to 'pending' to match our new status flow
        DB::table('newsletter_scheduled_sends')
            ->where('status', 'failed')
            ->update(['status' => 'pending']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert the enum values back to the original set
        DB::statement("ALTER TABLE newsletter_scheduled_sends 
            MODIFY COLUMN status ENUM('pending', 'sent', 'failed', 'skipped') 
            NOT NULL DEFAULT 'pending'");
            
        // Convert any 'processing' or 'cancelled' statuses back to 'pending'
        DB::table('newsletter_scheduled_sends')
            ->whereIn('status', ['processing', 'cancelled'])
            ->update(['status' => 'pending']);
    }
};
