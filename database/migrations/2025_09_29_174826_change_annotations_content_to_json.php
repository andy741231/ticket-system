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
        // First, migrate existing text data to JSON format
        $annotations = DB::table('annotations')
            ->whereNotNull('content')
            ->get();

        foreach ($annotations as $annotation) {
            // If content is not already JSON, encode it
            $decoded = json_decode($annotation->content);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // It's plain text, encode it as JSON string
                DB::table('annotations')
                    ->where('id', $annotation->id)
                    ->update(['content' => json_encode($annotation->content)]);
            }
        }

        // Now change the column type to JSON
        Schema::table('annotations', function (Blueprint $table) {
            $table->json('content')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Decode JSON content back to text
        $annotations = DB::table('annotations')
            ->whereNotNull('content')
            ->get();

        foreach ($annotations as $annotation) {
            $decoded = json_decode($annotation->content, true);
            if (is_string($decoded)) {
                DB::table('annotations')
                    ->where('id', $annotation->id)
                    ->update(['content' => $decoded]);
            }
        }

        Schema::table('annotations', function (Blueprint $table) {
            $table->text('content')->nullable()->change();
        });
    }
};