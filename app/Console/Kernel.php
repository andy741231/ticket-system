<?php

namespace App\Console;

use App\Console\Commands\AssignAdminRole;
use App\Console\Commands\ProcessScheduledSends;
use App\Console\Commands\ProcessRecurringCampaigns;
use App\Console\Commands\PurgeNewsletterTempUploads;
use App\Console\Commands\PurgeTempFiles;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        AssignAdminRole::class,
        ProcessScheduledSends::class,
        ProcessRecurringCampaigns::class,
        PurgeNewsletterTempUploads::class,
        PurgeTempFiles::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Process scheduled sends every minute
        $schedule->command(ProcessScheduledSends::class, ['--limit' => 100])
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/scheduler.log'));
            
        // Process recurring campaigns every 15 minutes
        $schedule->command(ProcessRecurringCampaigns::class)
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        // Prune expired user permission overrides hourly
        $schedule->command('rbac:prune-overrides')
            ->hourly()
            ->runInBackground();

        // Purge temporary newsletter uploads daily at 2am
        $schedule->command(PurgeNewsletterTempUploads::class, ['--days' => 3])
            ->dailyAt('02:00')
            ->runInBackground();
            
        // Clean up other temp files daily at 3am  
        $schedule->command(PurgeTempFiles::class, ['--days' => 3])
            ->dailyAt('03:00')
            ->runInBackground();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
