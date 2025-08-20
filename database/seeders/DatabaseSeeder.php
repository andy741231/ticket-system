<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // If using production seeder, run that and return early
        if (filter_var(env('RBAC_USE_PRODUCTION_SEEDER', false), FILTER_VALIDATE_BOOL)) {
            $this->call([
                ProductionRbacSeeder::class,
            ]);
            return;
        }

        $this->call([
            // Seed sub-applications first (provides team/app context)
            AppsSeeder::class,

            // Legacy/global roles & permissions (backward compatibility)
            RolesAndPermissionsSeeder::class,
            AdminUserSeeder::class,

            // RBAC with app scoping and immutable flags
            RbacSeeder::class,

            // Super admin user and role
            SuperAdminSeeder::class,
        ]);
    }
}
