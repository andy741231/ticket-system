<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class SetupStorage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'storage:setup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set up the storage directories and create the storage link';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Setting up storage directories...');
        
        // Ensure the storage/app/public directory exists
        $publicPath = storage_path('app/public');
        if (!File::exists($publicPath)) {
            File::makeDirectory($publicPath, 0755, true);
            $this->line('Created directory: ' . $publicPath);
        }

        // Create the tickets directory
        $ticketsPath = storage_path('app/public/tickets');
        if (!File::exists($ticketsPath)) {
            File::makeDirectory($ticketsPath, 0755, true);
            $this->line('Created directory: ' . $ticketsPath);
        }

        // Create the storage link if it doesn't exist
        $linkPath = public_path('storage');
        if (!file_exists($linkPath)) {
            $this->call('storage:link');
            $this->info('Storage link created.');
        } else {
            $this->info('Storage link already exists.');
        }

        // Set proper permissions
        $directories = [
            storage_path('app'),
            storage_path('app/public'),
            storage_path('app/public/tickets'),
            storage_path('framework'),
            storage_path('logs'),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
        ];

        foreach ($directories as $directory) {
            if (File::exists($directory)) {
                File::chmod($directory, 0755);
            }
        }

        $this->info('Storage setup completed successfully.');
        $this->line('Files will be stored in: ' . storage_path('app/public/tickets'));
        $this->line('They will be accessible at: ' . url('storage/tickets'));
    }
}
