<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;

class PruneAppLogs extends Command
{
    protected $signature = 'logs:prune
                            {--days=14 : Delete dated log files older than this many days}
                            {--dry-run : Preview actions without deleting or archiving anything}';

    protected $description = 'Prune old dated log files and rotate monolithic logs (schedule.log, queue.log, scheduler.log)';

    /**
     * Monolithic log files that should be archived-and-truncated daily.
     * Archives are named  <base>-YYYY-MM-DD.log and are themselves pruned
     * after --days days.
     */
    protected array $monolithic = [
        'schedule.log',
        'queue.log',
        'scheduler.log',
    ];

    /**
     * Glob patterns for dated log files that should be deleted when old.
     */
    protected array $datedPatterns = [
        'laravel-*.log',
        'scheduler-*.log',
        'schedule-*.log',
        'queue-*.log',
    ];

    public function handle(): int
    {
        $days   = (int) $this->option('days');
        $dryRun = (bool) $this->option('dry-run');
        $logDir = storage_path('logs');
        $cutoff = Carbon::now()->subDays($days);

        $deleted  = 0;
        $archived = 0;

        if ($dryRun) {
            $this->warn('DRY RUN — no files will be modified.');
        }

        // 1. Archive-and-truncate monolithic logs
        foreach ($this->monolithic as $filename) {
            $path = $logDir . DIRECTORY_SEPARATOR . $filename;

            if (!file_exists($path) || filesize($path) === 0) {
                continue;
            }

            $archived += $this->rotateLog($path, $dryRun);
        }

        // 2. Delete old dated log files
        foreach ($this->datedPatterns as $pattern) {
            $deleted += $this->deleteOldFiles($logDir, $pattern, $cutoff, $dryRun);
        }

        $this->newLine();
        $this->info("Done. Archived: {$archived} file(s). Deleted: {$deleted} file(s).");

        return self::SUCCESS;
    }

    protected function rotateLog(string $filePath, bool $dryRun): int
    {
        $dir     = dirname($filePath);
        $base    = pathinfo($filePath, PATHINFO_FILENAME);
        $date    = Carbon::now()->format('Y-m-d');
        $archive = $dir . DIRECTORY_SEPARATOR . $base . '-' . $date . '.log';

        if ($dryRun) {
            $this->line("  [DRY RUN] Would archive: " . basename($filePath) . " → " . basename($archive));
            return 0;
        }

        // Append current content to today's archive then truncate the live file.
        if (file_exists($archive)) {
            file_put_contents($archive, file_get_contents($filePath), FILE_APPEND | LOCK_EX);
        } else {
            copy($filePath, $archive);
        }

        file_put_contents($filePath, '', LOCK_EX);

        $sizeMb = round(filesize($archive) / 1048576, 2);
        $this->line("  Archived: " . basename($filePath) . " → " . basename($archive) . " ({$sizeMb} MB)");

        return 1;
    }

    protected function deleteOldFiles(string $dir, string $pattern, Carbon $cutoff, bool $dryRun): int
    {
        $count = 0;
        $files = glob($dir . DIRECTORY_SEPARATOR . $pattern);

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            if (Carbon::createFromTimestamp(filemtime($file))->lt($cutoff)) {
                if ($dryRun) {
                    $this->line("  [DRY RUN] Would delete: " . basename($file));
                    $count++;
                } elseif (@unlink($file)) {
                    $this->line("  Deleted: " . basename($file));
                    $count++;
                } else {
                    $this->warn("  Could not delete (in use?): " . basename($file));
                }
            }
        }

        return $count;
    }
}
