<?php

namespace App\Console;

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
        \App\Console\Commands\AssignAdminRole::class,
        \App\Console\Commands\ProcessScheduledSends::class,
        \App\Console\Commands\ProcessRecurringCampaigns::class,
        \App\Console\Commands\PurgeNewsletterTempUploads::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Prune expired user permission overrides hourly
        $schedule->command('rbac:prune-overrides')->hourly();
        
        // Process scheduled sends every minute
        $schedule->command('campaigns:process-scheduled-sends --limit=100')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground();
            
        // Process recurring campaigns every 15 minutes
        $schedule->command('campaigns:process-recurring')
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        // Purge temporary newsletter uploads daily at 2am
        $schedule->command('newsletters:purge-temp-uploads --days=3')
            ->dailyAt('02:00')
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
