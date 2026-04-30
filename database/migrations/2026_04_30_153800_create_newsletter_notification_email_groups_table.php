<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_notification_email_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('notification_email_id');
            $table->unsignedBigInteger('group_id');
            $table->timestamps();

            $table->unique(['notification_email_id', 'group_id'], 'nneg_email_group_unique');
            $table->index('notification_email_id', 'nneg_email_index');
            $table->index('group_id', 'nneg_group_index');

            $table->foreign('notification_email_id', 'nneg_email_foreign')
                ->references('id')
                ->on('newsletter_subscription_notification_emails')
                ->onDelete('cascade');
            $table->foreign('group_id', 'nneg_group_foreign')
                ->references('id')
                ->on('newsletter_groups')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_notification_email_groups');
    }
};
