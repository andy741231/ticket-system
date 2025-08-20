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
        Schema::create('user_permission_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('team_id')->nullable(); // app context; null = global
            $table->enum('effect', ['allow', 'deny']);
            $table->string('reason')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'permission_id', 'team_id'], 'user_perm_override_unique');
            // Helpful indexes for common queries: fetch overrides by user & team, and joins by permission
            $table->index(['user_id', 'team_id'], 'user_perm_overrides_user_team_index');
            $table->index('permission_id', 'user_perm_overrides_permission_index');

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('permission_id')->references('id')->on(config('permission.table_names.permissions', 'permissions'))->onDelete('cascade');
            $table->foreign('team_id')->references('id')->on('apps')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_permission_overrides');
    }
};
