<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgeNewsletterTempUploads extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'newsletters:purge-temp-uploads {--days=3 : Delete tmp folders older than this many days}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete temporary newsletter upload folders older than N days from public storage';

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $base = 'images/newsletters/tmp';
        $days = (int) $this->option('days');
        if ($days < 1) { $days = 1; }
        $threshold = Carbon::now()->subDays($days);

        if (!$disk->exists($base)) {
            $this->info("No temp directory at {$base}.");
            return self::SUCCESS;
        }

        $directories = $disk->directories($base);
        $deleted = 0;
        foreach ($directories as $dir) {
            try {
                // Determine last modified time by checking all files within the directory
                $lastModified = $this->getDirectoryLastModified($disk, $dir);
                if (!$lastModified || Carbon::createFromTimestamp($lastModified)->lt($threshold)) {
                    // Safe to delete the entire directory
                    $disk->deleteDirectory($dir);
                    $deleted++;
                    $this->line("Deleted: {$dir}");
                }
            } catch (\Throwable $e) {
                $this->error("Failed to check/delete {$dir}: " . $e->getMessage());
            }
        }

        $this->info("Purge complete. Deleted {$deleted} temp folder(s).");
        return self::SUCCESS;
    }

    private function getDirectoryLastModified($disk, string $dir): ?int
    {
        $latest = null;
        $files = $disk->allFiles($dir);
        if (empty($files)) {
            // If no files, fall back to the directory itself if supported
            try {
                $latest = $disk->lastModified($dir);
            } catch (\Throwable $e) {
                $latest = null;
            }
            return $latest;
        }
        foreach ($files as $file) {
            try {
                $lm = $disk->lastModified($file);
                if ($latest === null || $lm > $latest) {
                    $latest = $lm;
                }
            } catch (\Throwable $e) {
                // ignore file errors
            }
        }
        return $latest;
    }
}
