<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'first_name')) {
                $table->string('first_name')->nullable()->after('id');
            }
            if (!Schema::hasColumn('users', 'last_name')) {
                $table->string('last_name')->nullable()->after('first_name');
            }
        });

        // Backfill first_name/last_name from existing name column
        if (Schema::hasColumn('users', 'name')) {
            DB::table('users')
                ->select('id', 'name')
                ->orderBy('id')
                ->chunkById(500, function ($rows) {
                    foreach ($rows as $row) {
                        $name = trim((string)($row->name ?? ''));
                        $first = null;
                        $last = null;
                        if ($name !== '') {
                            $parts = preg_split('/\s+/', $name);
                            $first = $parts[0] ?? null;
                            if (count($parts) > 1) {
                                $last = implode(' ', array_slice($parts, 1));
                            }
                        }
                        DB::table('users')->where('id', $row->id)->update([
                            'first_name' => $first,
                            'last_name' => $last,
                        ]);
                    }
                });
        }
    }

    public function down(): void
    {
        // Recombine into `name` where possible before dropping columns
        if (Schema::hasColumn('users', 'first_name') && Schema::hasColumn('users', 'last_name')) {
            DB::table('users')
                ->select('id', 'first_name', 'last_name')
                ->orderBy('id')
                ->chunkById(500, function ($rows) {
                    foreach ($rows as $row) {
                        $name = trim(implode(' ', array_filter([
                            $row->first_name ?? '',
                            $row->last_name ?? '',
                        ], function ($v) { return $v !== ''; })));
                        if ($name !== '') {
                            DB::table('users')->where('id', $row->id)->update(['name' => $name]);
                        }
                    }
                });
        }

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'last_name')) {
                $table->dropColumn('last_name');
            }
            if (Schema::hasColumn('users', 'first_name')) {
                $table->dropColumn('first_name');
            }
        });
    }
};
