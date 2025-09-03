<?php

namespace App\Jobs;

use App\Models\Newsletter\Campaign;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessRecurringCampaigns implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The campaign instance.
     *
     * @var \App\Models\Newsletter\Campaign
     */
    protected $campaign;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 600; // 10 minutes

    /**
     * Create a new job instance.
     *
     * @param  \App\Models\Newsletter\Campaign  $campaign
     * @return void
     */
    public function __construct(Campaign $campaign)
    {
        $this->campaign = $campaign->withoutRelations();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $campaign = $this->campaign->fresh();
        
        // Skip if campaign is not a recurring campaign or is not active
        if (!$campaign->isRecurring() || $campaign->status !== 'active') {
            Log::info("Skipping non-recurring or inactive campaign #{$campaign->id}");
            return;
        }

        $config = $campaign->recurring_config;
        $now = now();
        
        // Check if we've reached the end date
        if (!empty($config['end_date']) && $now->gt(Carbon::parse($config['end_date']))) {
            Log::info("Recurring campaign #{$campaign->id} has reached its end date");
            return;
        }

        // Check if we've reached the maximum number of occurrences
        if (!empty($config['occurrences']) && $campaign->scheduledSends()->count() >= $config['occurrences']) {
            Log::info("Recurring campaign #{$campaign->id} has reached its maximum number of occurrences");
            return;
        }

        // Get the last send date or use campaign creation date if no sends yet
        $lastSendDate = $campaign->getLastSentDate() ?? $campaign->created_at;
        $nextSendDate = $this->calculateNextSendDate($lastSendDate, $config, $now);

        // If next send date is in the future, schedule a job to run then
        if ($nextSendDate->gt($now)) {
            Log::info("Next send for campaign #{$campaign->id} is scheduled for {$nextSendDate}");
            
            // Update the next scheduled time in the config
            $config['next_scheduled_at'] = $nextSendDate;
            $campaign->update(['recurring_config' => $config]);
            
            // Schedule the next run
            self::dispatch($campaign)->delay($nextSendDate);
            return;
        }

        // Create scheduled sends for each subscriber
        $subscribers = $campaign->getRecipientsQuery()->get();
        $sendCount = 0;
        $scheduledAt = $nextSendDate;

        foreach ($subscribers as $subscriber) {
            $scheduledSend = $campaign->scheduledSends()->create([
                'subscriber_id' => $subscriber->id,
                'scheduled_at' => $scheduledAt,
                'status' => \App\Models\Newsletter\ScheduledSend::STATUS_PENDING,
            ]);

            if ($scheduledSend) {
                $sendCount++;
            }
        }

        // Update campaign stats and config
        $config['last_scheduled_at'] = $scheduledAt->toDateTimeString();
        $config['next_scheduled_at'] = $this->calculateNextSendDate($scheduledAt, $config, $now)->toDateTimeString();
        
        $campaign->increment('total_recipients', $sendCount);
        $campaign->update(['recurring_config' => $config]);

        Log::info("Scheduled {$sendCount} sends for recurring campaign #{$campaign->id} to be sent on {$scheduledAt}");
        
        // Schedule the next run
        $nextRunDate = $this->calculateNextSendDate($scheduledAt, $config, $now);
        if ($nextRunDate->gt($now)) {
            self::dispatch($campaign)->delay($nextRunDate);
        }
    }

    /**
     * Calculate the next send date based on the frequency configuration.
     *
     * @param  \Carbon\Carbon  $lastSendDate
     * @param  array  $config
     * @param  \Carbon\Carbon  $now
     * @return \Carbon\Carbon
     */
    protected function calculateNextSendDate($lastSendDate, $config, $now)
    {
        $nextDate = $lastSendDate->copy();
        $frequency = $config['frequency'] ?? 'weekly';
        
        switch ($frequency) {
            case 'daily':
                $nextDate->addDay();
                break;
                
            case 'weekly':
                $daysOfWeek = $config['days_of_week'] ?? [$now->dayOfWeek];
                $nextDate = $this->nextDayOfWeek($nextDate, $daysOfWeek);
                break;
                
            case 'monthly':
                $dayOfMonth = $config['day_of_month'] ?? $now->day;
                $nextDate = $this->nextDayOfMonth($nextDate, $dayOfMonth);
                break;
                
            case 'quarterly':
                $nextDate->addMonths(3);
                break;
                
            default:
                // Default to weekly if frequency is not recognized
                $nextDate->addWeek();
        }
        
        // If the calculated date is in the past, try the next occurrence
        if ($nextDate->lt($now)) {
            return $this->calculateNextSendDate($nextDate, $config, $now);
        }
        
        return $nextDate;
    }
    
    /**
     * Get the next occurrence of a day of the week.
     *
     * @param  \Carbon\Carbon  $date
     * @param  array  $daysOfWeek Array of day numbers (0-6, where 0 is Sunday)
     * @return \Carbon\Carbon
     */
    protected function nextDayOfWeek($date, $daysOfWeek)
    {
        $nextDate = $date->copy();
        
        // If today is one of the target days and we haven't passed the time yet
        if (in_array($nextDate->dayOfWeek, $daysOfWeek) && $nextDate->gt(now())) {
            return $nextDate;
        }
        
        // Find the next occurrence of any of the target days
        do {
            $nextDate->addDay();
        } while (!in_array($nextDate->dayOfWeek, $daysOfWeek));
        
        return $nextDate;
    }
    
    /**
     * Get the next occurrence of a day of the month.
     *
     * @param  \Carbon\Carbon  $date
     * @param  int  $dayOfMonth
     * @return \Carbon\Carbon
     */
    protected function nextDayOfMonth($date, $dayOfMonth)
    {
        $nextDate = $date->copy();
        
        // If the day of month is in the future this month, use it
        $nextMonth = $nextDate->copy()->day($dayOfMonth);
        if ($nextMonth->gt($nextDate)) {
            return $nextMonth;
        }
        
        // Otherwise, use the next month
        return $nextDate->addMonthNoOverflow()->day($dayOfMonth);
    }
    
    /**
     * Handle a job failure.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        Log::error("Failed to process recurring campaign #{$this->campaign->id}", [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
        
        // Optionally, you could update the campaign status or notify an admin here
    }
}
