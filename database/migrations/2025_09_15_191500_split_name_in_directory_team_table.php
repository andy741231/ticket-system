<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('directory')->table('directory_team', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('id');
            $table->string('last_name')->nullable()->after('first_name');
        });

        // Backfill first_name and last_name from existing name column if present
        if (Schema::connection('directory')->hasColumn('directory_team', 'name')) {
            $records = DB::connection('directory')->table('directory_team')->select('id', 'name')->get();
            foreach ($records as $rec) {
                $name = trim((string) $rec->name);
                $first = null;
                $last = null;
                if ($name !== '') {
                    $parts = preg_split('/\s+/', $name);
                    if ($parts) {
                        $first = array_shift($parts);
                        $last = count($parts) ? implode(' ', $parts) : null;
                    }
                }
                DB::connection('directory')->table('directory_team')
                    ->where('id', $rec->id)
                    ->update([
                        'first_name' => $first,
                        'last_name' => $last,
                    ]);
            }

            Schema::connection('directory')->table('directory_team', function (Blueprint $table) {
                $table->dropColumn('name');
            });
        }

        // Make new columns non-null after backfill
        Schema::connection('directory')->table('directory_team', function (Blueprint $table) {
            $table->string('first_name')->nullable(false)->change();
            $table->string('last_name')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::connection('directory')->table('directory_team', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
        });

        // Rebuild the single name from first and last
        $records = DB::connection('directory')->table('directory_team')->select('id', 'first_name', 'last_name')->get();
        foreach ($records as $rec) {
            $full = trim(trim((string) ($rec->first_name ?? '')) . ' ' . trim((string) ($rec->last_name ?? '')));
            DB::connection('directory')->table('directory_team')
                ->where('id', $rec->id)
                ->update([
                    'name' => $full !== '' ? $full : null,
                ]);
        }

        Schema::connection('directory')->table('directory_team', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
        });

        Schema::connection('directory')->table('directory_team', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
        });
    }
};
