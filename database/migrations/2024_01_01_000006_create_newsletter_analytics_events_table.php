<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_analytics_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('newsletter_campaigns')->onDelete('cascade');
            $table->foreignId('subscriber_id')->constrained('newsletter_subscribers')->onDelete('cascade');
            $table->enum('event_type', ['sent', 'delivered', 'opened', 'clicked', 'bounced', 'unsubscribed', 'complained']);
            $table->string('link_url')->nullable(); // For click tracking
            $table->string('user_agent')->nullable();
            $table->string('ip_address')->nullable();
            $table->json('metadata')->nullable(); // Additional tracking data
            $table->timestamps();

            $table->index(['campaign_id', 'event_type']);
            $table->index(['subscriber_id', 'event_type']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_analytics_events');
    }
};
