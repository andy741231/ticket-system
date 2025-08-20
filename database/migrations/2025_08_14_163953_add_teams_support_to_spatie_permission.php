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
        // Add team_id to roles and adjust unique index
        Schema::table('roles', function (Blueprint $table) {
            if (!Schema::hasColumn('roles', 'team_id')) {
                $table->unsignedBigInteger('team_id')->nullable()->after('id');
                $table->index('team_id', 'roles_team_foreign_key_index');
                // Drop existing unique and add new one including team_id
                $table->dropUnique('roles_name_guard_name_unique');
                $table->unique(['team_id', 'name', 'guard_name'], 'roles_team_name_guard_unique');
                // Optional FK to apps
                $table->foreign('team_id')->references('id')->on('apps')->onDelete('cascade');
            }
        });

        // Add team_id to model_has_roles and adjust primary key
        Schema::table('model_has_roles', function (Blueprint $table) {
            if (!Schema::hasColumn('model_has_roles', 'team_id')) {
                $table->unsignedBigInteger('team_id')->nullable()->after('model_id');
                $table->index('team_id', 'model_has_roles_team_foreign_key_index');

                // Add a temporary index on role_id to satisfy FK while changing PK
                $table->index('role_id', 'model_has_roles_role_id_temp_index');

                // Drop existing primary key and recreate including team_id
                $table->dropPrimary('model_has_roles_role_model_type_primary');
                $table->primary(['team_id', 'role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');

                // Keep supporting index for FK on role_id because new PK now starts with team_id

                // Optional FK to apps
                $table->foreign('team_id')->references('id')->on('apps')->onDelete('cascade');
            }
        });

        // Add team_id to model_has_permissions and adjust primary key
        Schema::table('model_has_permissions', function (Blueprint $table) {
            if (!Schema::hasColumn('model_has_permissions', 'team_id')) {
                $table->unsignedBigInteger('team_id')->nullable()->after('model_id');
                $table->index('team_id', 'model_has_permissions_team_foreign_key_index');

                // Add a temporary index on permission_id to satisfy FK while changing PK
                $table->index('permission_id', 'model_has_permissions_permission_id_temp_index');

                // Drop existing primary key and recreate including team_id
                $table->dropPrimary('model_has_permissions_permission_model_type_primary');
                $table->primary(['team_id', 'permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');

                // Keep supporting index for FK on permission_id because new PK now starts with team_id

                // Optional FK to apps
                $table->foreign('team_id')->references('id')->on('apps')->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert changes to model_has_permissions
        Schema::table('model_has_permissions', function (Blueprint $table) {
            if (Schema::hasColumn('model_has_permissions', 'team_id')) {
                $table->dropPrimary('model_has_permissions_permission_model_type_primary');
                $table->dropForeign(['team_id']);
                $table->dropIndex('model_has_permissions_team_foreign_key_index');
                // Drop supporting index created during up()
                $table->dropIndex('model_has_permissions_permission_id_temp_index');
                $table->dropColumn('team_id');
                $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
            }
        });

        // Revert changes to model_has_roles
        Schema::table('model_has_roles', function (Blueprint $table) {
            if (Schema::hasColumn('model_has_roles', 'team_id')) {
                $table->dropPrimary('model_has_roles_role_model_type_primary');
                $table->dropForeign(['team_id']);
                $table->dropIndex('model_has_roles_team_foreign_key_index');
                // Drop supporting index created during up()
                $table->dropIndex('model_has_roles_role_id_temp_index');
                $table->dropColumn('team_id');
                $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
            }
        });

        // Revert changes to roles
        Schema::table('roles', function (Blueprint $table) {
            if (Schema::hasColumn('roles', 'team_id')) {
                $table->dropForeign(['team_id']);
                $table->dropIndex('roles_team_foreign_key_index');
                $table->dropUnique('roles_team_name_guard_unique');
                $table->dropColumn('team_id');
                $table->unique(['name', 'guard_name']);
            }
        });
    }
};
