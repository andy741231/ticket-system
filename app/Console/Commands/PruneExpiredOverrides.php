<?php

namespace App\Console\Commands;

use App\Models\UserPermissionOverride;
use Illuminate\Console\Command;

class PruneExpiredOverrides extends Command
{
    protected $signature = 'rbac:prune-overrides {--dry-run : Show how many would be pruned without deleting}';

    protected $description = 'Delete expired user permission overrides (expires_at in the past)';

    public function handle(): int
    {
        $query = UserPermissionOverride::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());

        $count = (clone $query)->count();
        $dry = (bool) $this->option('dry-run');

        if ($dry) {
            $this->info("[DRY-RUN] Would prune {$count} expired overrides.");
            return self::SUCCESS;
        }

        if ($count === 0) {
            $this->info('No expired overrides to prune.');
            return self::SUCCESS;
        }

        // Delete in chunks to fire model events per record (for cache flush) and avoid memory spikes
        $deleted = 0;
        UserPermissionOverride::whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(200, function ($overrides) use (&$deleted) {
                foreach ($overrides as $override) {
                    if ($override->delete()) {
                        $deleted++;
                    }
                }
            });

        $this->info("Pruned {$deleted} expired overrides.");
        return self::SUCCESS;
    }
}
