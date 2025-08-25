<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_subscriber_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscriber_id')->constrained('newsletter_subscribers')->onDelete('cascade');
            $table->foreignId('group_id')->constrained('newsletter_groups')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['subscriber_id', 'group_id']);
            $table->index('subscriber_id');
            $table->index('group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscriber_groups');
    }
};
