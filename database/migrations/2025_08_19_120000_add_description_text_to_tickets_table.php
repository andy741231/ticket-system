<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Ticket;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->text('description_text')->nullable()->after('description');
        });

        // Populate the new column for existing tickets
        Ticket::whereNotNull('description')->each(function (Ticket $ticket) {
            $ticket->description_text = strip_tags($ticket->description);
            $ticket->saveQuietly(); // Use saveQuietly to avoid triggering events again
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('description_text');
        });
    }
};
