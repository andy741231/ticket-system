<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class ImportDatabase extends Command
{
    protected $signature = 'db:import {file} {--database=mysql}';
    protected $description = 'Import SQL file to the database';

    public function handle()
    {
        $file = $this->argument('file');
        $connection = $this->option('database');
        
        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return 1;
        }

        $config = Config::get("database.connections.{$connection}");
        
        $command = sprintf(
            'mysql -h %s -u %s -p%s %s < %s',
            escapeshellarg($config['host'] ?? '127.0.0.1'),
            escapeshellarg($config['username']),
            escapeshellarg($config['password'] ?? ''),
            escapeshellarg($config['database']),
            escapeshellarg($file)
        );

        $this->info("Importing database from {$file}...");
        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            $this->error("Error importing database");
            return 1;
        }

        $this->info("Database imported successfully!");
        return 0;
    }
}
