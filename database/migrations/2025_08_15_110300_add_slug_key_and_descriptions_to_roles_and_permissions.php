<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add slug and description to roles
        Schema::table('roles', function (Blueprint $table) {
            if (!Schema::hasColumn('roles', 'slug')) {
                $table->string('slug')->nullable()->after('name');
            }
            if (!Schema::hasColumn('roles', 'description')) {
                $table->string('description')->nullable()->after('slug');
            }
        });

        // Backfill role slugs uniquely per team_id
        if (Schema::hasColumn('roles', 'slug')) {
            $roles = DB::table('roles')->select('id', 'team_id', 'name', 'slug')->get();
            $seen = [];
            foreach ($roles as $role) {
                if (!empty($role->slug)) {
                    // preserve existing
                    $key = (string)($role->team_id ?? 'global');
                    $seen[$key] = $seen[$key] ?? [];
                    $seen[$key][$role->slug] = true;
                    continue;
                }
                $key = (string)($role->team_id ?? 'global');
                $base = Str::slug($role->name ?: 'role');
                $slug = $base;
                $i = 1;
                $seen[$key] = $seen[$key] ?? [];
                while (isset($seen[$key][$slug])) {
                    $i++;
                    $slug = $base . '-' . $i;
                }
                DB::table('roles')->where('id', $role->id)->update(['slug' => $slug]);
                $seen[$key][$slug] = true;
            }
        }

        // Add unique index on (team_id, slug)
        Schema::table('roles', function (Blueprint $table) {
            // Use a named index so we can drop it reliably in down()
            $table->unique(['team_id', 'slug'], 'roles_team_slug_unique');
        });

        // Add key and description to permissions
        Schema::table('permissions', function (Blueprint $table) {
            if (!Schema::hasColumn('permissions', 'key')) {
                $table->string('key')->nullable()->after('name');
            }
            if (!Schema::hasColumn('permissions', 'description')) {
                $table->string('description')->nullable()->after('key');
            }
        });

        // Backfill permission keys from name (idempotent)
        if (Schema::hasColumn('permissions', 'key')) {
            $perms = DB::table('permissions')->select('id', 'name', 'key')->get();
            $seenKeys = [];
            foreach ($perms as $perm) {
                if (!empty($perm->key)) {
                    $seenKeys[$perm->key] = true;
                    continue;
                }
                $base = Str::of($perm->name ?: 'permission')->lower()->replace(' ', '.')->replace([':', '/', '\\'], '.')->__toString();
                $base = trim(preg_replace('/\.+/', '.', $base), '.');
                $k = $base;
                $i = 1;
                while (isset($seenKeys[$k])) {
                    $i++;
                    $k = $base . '.' . $i;
                }
                DB::table('permissions')->where('id', $perm->id)->update(['key' => $k]);
                $seenKeys[$k] = true;
            }
        }

        // Add unique index on permissions.key (global uniqueness)
        Schema::table('permissions', function (Blueprint $table) {
            $table->unique(['key'], 'permissions_key_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop unique index and columns from permissions
        Schema::table('permissions', function (Blueprint $table) {
            // Drop unique index if exists
            try { $table->dropUnique('permissions_key_unique'); } catch (\Throwable $e) {}
            if (Schema::hasColumn('permissions', 'description')) {
                $table->dropColumn('description');
            }
            if (Schema::hasColumn('permissions', 'key')) {
                $table->dropColumn('key');
            }
        });

        // Drop unique index and columns from roles
        Schema::table('roles', function (Blueprint $table) {
            try { $table->dropUnique('roles_team_slug_unique'); } catch (\Throwable $e) {}
            if (Schema::hasColumn('roles', 'description')) {
                $table->dropColumn('description');
            }
            if (Schema::hasColumn('roles', 'slug')) {
                $table->dropColumn('slug');
            }
        });
    }
};
