<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Widen link_url beyond varchar(255): real click destinations wrapped by
     * security gateways (urldefense/AWS/SendIt style nesting) exceed 255 chars
     * and the AnalyticsEvent::create insert then fails inside trackClick.
     */
    public function up(): void
    {
        Schema::table('newsletter_analytics_events', function (Blueprint $table) {
            $table->text('link_url')->nullable()->change();
        });
    }

    /**
     * Intentionally a no-op. Shrinking back to varchar(255) could truncate
     * click destinations already recorded after the widening; this migration
     * is therefore effectively irreversible (widening stays).
     */
    public function down(): void
    {
        // Do not shrink: existing TEXT data may exceed varchar(255).
    }
};
