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
        Schema::create('external_user_image_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('external_user_id')->constrained()->onDelete('cascade');
            $table->foreignId('ticket_image_id')->constrained()->onDelete('cascade');
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('invited_at');
            $table->timestamp('first_accessed_at')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->boolean('access_revoked')->default(false);
            $table->timestamps();
            
            $table->unique(['external_user_id', 'ticket_image_id'], 'ext_user_image_unique');
            $table->index('ticket_image_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('external_user_image_access');
    }
};
