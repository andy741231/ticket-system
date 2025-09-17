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
            ->withoutOverlapping();
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
