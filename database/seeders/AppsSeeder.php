<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AppsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $apps = [
            ['slug' => 'hub', 'name' => 'Hub'],
            ['slug' => 'tickets', 'name' => 'Tickets'],
            ['slug' => 'directory', 'name' => 'Directory'],
            ['slug' => 'newsletter', 'name' => 'Newsletter'],
        ];

        foreach ($apps as $app) {
            DB::table('apps')->updateOrInsert(
                ['slug' => $app['slug']],
                ['name' => $app['name'], 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }
}
