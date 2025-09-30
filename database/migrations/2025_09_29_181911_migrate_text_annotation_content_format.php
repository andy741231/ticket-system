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
        // Get all text annotations with content
        $annotations = DB::table('annotations')
            ->where('type', 'text')
            ->whereNotNull('content')
            ->get();

        foreach ($annotations as $annotation) {
            // Decode the JSON-encoded string
            $content = json_decode($annotation->content, true);
            
            // If it's already a string (not an array), just keep it as is
            // The content field is now JSON, so we need to store it properly
            if (is_string($content)) {
                // Store as plain string in JSON field (Laravel will handle the encoding)
                DB::table('annotations')
                    ->where('id', $annotation->id)
                    ->update(['content' => json_encode($content)]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to reverse this data migration
    }
};
