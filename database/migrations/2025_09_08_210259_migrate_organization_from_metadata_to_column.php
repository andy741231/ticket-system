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
        // Migrate organization from metadata to dedicated column
        DB::table('newsletter_subscribers')
            ->whereNotNull('metadata')
            ->where('metadata', 'like', '%organization%')
            ->get()
            ->each(function ($subscriber) {
                $metadata = json_decode($subscriber->metadata, true);
                if (isset($metadata['organization'])) {
                    DB::table('newsletter_subscribers')
                        ->where('id', $subscriber->id)
                        ->update([
                            'organization' => $metadata['organization'],
                            'metadata' => json_encode(array_diff_key($metadata, ['organization' => null])),
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse: move organization back to metadata and clear column
        DB::table('newsletter_subscribers')
            ->whereNotNull('organization')
            ->get()
            ->each(function ($subscriber) {
                $metadata = json_decode($subscriber->metadata ?: '{}', true);
                $metadata['organization'] = $subscriber->organization;
                DB::table('newsletter_subscribers')
                    ->where('id', $subscriber->id)
                    ->update([
                        'organization' => null,
                        'metadata' => json_encode($metadata),
                    ]);
            });
    }
};
