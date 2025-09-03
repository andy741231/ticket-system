<?php

namespace App\Console\Commands;

use App\Models\Newsletter\Campaign;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessRecurringCampaigns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'campaigns:process-recurring';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process recurring campaigns and schedule the next send';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Processing recurring campaigns...');
        $now = now();
        $processed = 0;
        $skipped = 0;
        $failed = 0;

        // Get all active recurring campaigns
        Campaign::query()
            ->where('send_type', 'recurring')
            ->where('status', 'active')
            ->where(function ($query) use ($now) {
                $query->whereNull('recurring_config->end_date')
                    ->orWhere('recurring_config->end_date', '>=', $now->toDateString());
            })
            ->chunk(50, function ($campaigns) use (&$processed, &$skipped, &$failed, $now) {
                foreach ($campaigns as $campaign) {
                    try {
                        if ($this->processCampaign($campaign, $now)) {
                            $processed++;
                        } else {
                            $skipped++;
                        }
                    } catch (\Exception $e) {
                        Log::error("Failed to process recurring campaign #{$campaign->id}", [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        $failed++;
                    }
                }
            });

        $this->info("Processed {$processed} recurring campaigns, skipped {$skipped}, failed {$failed}.");
        return 0;
    }

    /**
     * Process a single recurring campaign.
     *
     * @param \App\Models\Newsletter\Campaign $campaign
     * @param \Carbon\Carbon $now
     * @return bool Whether the campaign was processed
     */
    protected function processCampaign(Campaign $campaign, Carbon $now): bool
    {
        $config = $campaign->recurring_config;
        
        // Check if we've reached the end date
        if (!empty($config['end_date']) && $now->gt(Carbon::parse($config['end_date']))) {
            $this->warn("Skipping campaign #{$campaign->id} - Reached end date");
            return false;
        }

        // Check if we've reached the maximum number of occurrences
        if (!empty($config['occurrences']) && $campaign->scheduledSends()->count() >= $config['occurrences']) {
            $this->warn("Skipping campaign #{$campaign->id} - Reached maximum occurrences");
            return false;
        }

        // Get the last send date or use campaign creation date if no sends yet
        $lastSendDate = $campaign->getLastSentDate() ?? $campaign->created_at;
        $nextSendDate = $this->calculateNextSendDate($lastSendDate, $config, $now);

        // If next send date is in the future, skip for now
        if ($nextSendDate->gt($now)) {
            $this->info("Skipping campaign #{$campaign->id} - Next send is scheduled for {$nextSendDate}");
            return false;
        }

        // Create scheduled sends for each subscriber
        $subscribers = $campaign->getRecipientsQuery()->get();
        $sendCount = 0;

        foreach ($subscribers as $subscriber) {
            $scheduledSend = $campaign->scheduledSends()->create([
                'subscriber_id' => $subscriber->id,
                'scheduled_at' => $nextSendDate,
                'status' => \App\Models\Newsletter\ScheduledSend::STATUS_PENDING,
            ]);

            if ($scheduledSend) {
                $sendCount++;
            }
        }

        // Update campaign stats
        $campaign->increment('total_recipients', $sendCount);
        
        $this->info("Scheduled {$sendCount} sends for campaign #{$campaign->id} to be sent on {$nextSendDate}");
        return true;
    }

    /**
     * Calculate the next send date based on the frequency configuration.
     *
     * @param \Carbon\Carbon $lastSendDate
     * @param array $config
     * @param \Carbon\Carbon $now
     * @return \Carbon\Carbon
     */
    protected function calculateNextSendDate(Carbon $lastSendDate, array $config, Carbon $now): Carbon
    {
        $nextDate = clone $lastSendDate;
        $frequency = $config['frequency'] ?? 'weekly';
        
        switch ($frequency) {
            case 'daily':
                $nextDate->addDay();
                break;
                
            case 'weekly':
                $daysOfWeek = $config['days_of_week'] ?? [now()->dayOfWeek];
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
     * @param \Carbon\Carbon $date
     * @param array $daysOfWeek Array of day numbers (0-6, where 0 is Sunday)
     * @return \Carbon\Carbon
     */
    protected function nextDayOfWeek(Carbon $date, array $daysOfWeek): Carbon
    {
        $nextDate = clone $date;
        
        // If today is one of the target days and we haven't already passed the time
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
     * @param \Carbon\Carbon $date
     * @param int $dayOfMonth
     * @return \Carbon\Carbon
     */
    protected function nextDayOfMonth(Carbon $date, int $dayOfMonth): Carbon
    {
        $nextDate = clone $date;
        
        // If the day of month is in the future this month, use it
        $nextMonth = (clone $nextDate)->day($dayOfMonth);
        if ($nextMonth->gt($nextDate)) {
            return $nextMonth;
        }
        
        // Otherwise, use the next month
        return $nextDate->addMonthNoOverflow()->day($dayOfMonth);
    }
}
