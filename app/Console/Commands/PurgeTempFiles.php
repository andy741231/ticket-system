<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgeTempFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cleanup:temp-files 
                            {--days=3 : Delete files/folders older than this many days}
                            {--dry-run : List files that would be deleted without actually deleting them}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up temporary files and folders across the application';

    /**
     * List of directories to clean up
     *
     * @var array
     */
    protected $directories = [
        'images/people/temp',
        'temp',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $disk = Storage::disk('public');
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $threshold = Carbon::now()->subDays($days);
        $deletedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;

        $this->info('Starting temp files cleanup...');
        $this->line(sprintf('Deleting files older than %s days', $days));
        if ($dryRun) {
            $this->warn('DRY RUN: No files will be deleted');
        }

        foreach ($this->directories as $directory) {
            $this->line("\nProcessing directory: {$directory}");
            
            if (!$disk->exists($directory)) {
                $this->warn("  Directory does not exist: {$directory}");
                continue;
            }

            // Get all files and directories
            $files = $disk->allFiles($directory);
            $directories = $disk->directories($directory);

            // Process files
            foreach ($files as $file) {
                try {
                    $lastModified = $disk->lastModified($file);
                    
                    if (Carbon::createFromTimestamp($lastModified)->lt($threshold)) {
                        if ($dryRun) {
                            $this->line("  [DRY RUN] Would delete file: {$file}");
                            $deletedCount++;
                        } else {
                            if ($disk->delete($file)) {
                                $this->line("  Deleted file: {$file}");
                                $deletedCount++;
                            } else {
                                $this->error("  Failed to delete file: {$file}");
                                $errorCount++;
                            }
                        }
                    } else {
                        $skippedCount++;
                    }
                } catch (\Exception $e) {
                    $this->error("  Error processing file {$file}: " . $e->getMessage());
                    $errorCount++;
                }
            }

            // Process directories (only delete empty ones)
            foreach ($directories as $dir) {
                try {
                    $lastModified = $this->getDirectoryLastModified($disk, $dir);
                    
                    if ($lastModified && Carbon::createFromTimestamp($lastModified)->lt($threshold)) {
                        $filesInDir = $disk->allFiles($dir);
                        
                        if (empty($filesInDir)) {
                            if ($dryRun) {
                                $this->line("  [DRY RUN] Would delete empty directory: {$dir}");
                                $deletedCount++;
                            } else {
                                if ($disk->deleteDirectory($dir)) {
                                    $this->line("  Deleted empty directory: {$dir}");
                                    $deletedCount++;
                                } else {
                                    $this->error("  Failed to delete directory: {$dir}");
                                    $errorCount++;
                                }
                            }
                        } else {
                            $this->line("  Skipping non-empty directory: {$dir}");
                            $skippedCount++;
                        }
                    } else {
                        $skippedCount++;
                    }
                } catch (\Exception $e) {
                    $this->error("  Error processing directory {$dir}: " . $e->getMessage());
                    $errorCount++;
                }
            }
        }

        // Summary
        $this->line("\nCleanup Summary:");
        $this->line(sprintf("  Deleted: %d items", $deletedCount));
        $this->line(sprintf("  Skipped: %d items (not old enough or not empty)", $skippedCount));
        $this->line(sprintf("  Errors: %d", $errorCount));

        if ($dryRun) {
            $this->warn("\nDRY RUN: No files were actually deleted");
        } else {
            $this->info("\nCleanup completed!");
        }

        return $errorCount > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Get the last modified timestamp of a directory by checking all files within it
     */
    protected function getDirectoryLastModified($disk, string $directory): ?int
    {
        $latestTime = null;
        
        try {
            $files = $disk->allFiles($directory);
            
            foreach ($files as $file) {
                $fileTime = $disk->lastModified($file);
                if ($latestTime === null || $fileTime > $latestTime) {
                    $latestTime = $fileTime;
                }
            }
        } catch (\Exception $e) {
            $this->error("Error checking directory {$directory}: " . $e->getMessage());
            return null;
        }
        
        return $latestTime;
    }
}
