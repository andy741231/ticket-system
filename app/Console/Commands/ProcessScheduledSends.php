<?php

namespace App\Console\Commands;

use App\Models\Newsletter\Campaign;
use App\Models\Newsletter\ScheduledSend;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessScheduledSends extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'campaigns:process-scheduled-sends {--limit=100 : Maximum number of sends to process in this batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process scheduled campaign sends that are due to be sent';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $limit = (int) $this->option('limit');
        $this->info("Processing up to {$limit} scheduled sends...");

        // Timezone debugging information
        $now = now()->utc();
        $localNow = now();
        
        $this->info("Timezone Debug Info:");
        $this->info("  PHP timezone: " . date_default_timezone_get());
        $this->info("  Laravel app timezone: " . config('app.timezone'));
        $this->info("  Current local time: {$localNow}");
        $this->info("  Current UTC time: {$now}");
        $this->info("  Time difference: " . $localNow->diffInHours($now) . " hours");

        $processed = 0;

        // Check for pending sends that should be processed
        $pendingSends = ScheduledSend::where('status', ScheduledSend::STATUS_PENDING)
            ->where('scheduled_at', '<=', $now)
            ->count();
            
        $this->info("Found {$pendingSends} pending sends due for processing");

        // Process scheduled sends in batches to avoid memory issues
        ScheduledSend::with(['campaign', 'subscriber'])
            ->where('status', ScheduledSend::STATUS_PENDING)
            ->where('scheduled_at', '<=', $now)
            ->orderBy('scheduled_at')
            ->limit($limit)
            ->chunk(50, function ($scheduledSends) use (&$processed, $now) {
                foreach ($scheduledSends as $scheduledSend) {
                    try {
                        $this->processScheduledSend($scheduledSend, $now);
                        $processed++;
                    } catch (\Exception $e) {
                        Log::error("Failed to process scheduled send #{$scheduledSend->id}", [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        
                        $scheduledSend->markAsFailed($e->getMessage());
                    }
                }
            });

        $this->info("Processed {$processed} scheduled sends.");
        return 0;
    }

    /**
     * Process a single scheduled send.
     *
     * @param \App\Models\Newsletter\ScheduledSend $scheduledSend
     * @param \Carbon\Carbon $now
     * @return void
     */
    protected function processScheduledSend(ScheduledSend $scheduledSend, Carbon $now)
    {
        $campaign = $scheduledSend->campaign;
        
        $this->info("Processing scheduled send #{$scheduledSend->id} for campaign #{$campaign->id}");
        $this->info("  Scheduled at: {$scheduledSend->scheduled_at} UTC");
        $this->info("  Current time: {$now} UTC");
        $this->info("  Campaign status: {$campaign->status}");
        
        // Skip if campaign is not in a sendable state
        if (!$campaign->canBeSent()) {
            $scheduledSend->markAsFailed('Campaign is not in a sendable state');
            $this->warn("Skipping scheduled send #{$scheduledSend->id} - Campaign #{$campaign->id} is not sendable");
            return;
        }

        // Mark as processing
        if (!$scheduledSend->markAsProcessing()) {
            $this->error("Failed to mark scheduled send #{$scheduledSend->id} as processing");
            return;
        }

        try {
            // If this is a recurring campaign, make a copy of the campaign for this send
            if ($campaign->isRecurring()) {
                $campaign = $this->createRecurringCampaignInstance($campaign, $scheduledSend->scheduled_at);
            }

            // Dispatch the send job for individual subscriber
            dispatch(new \App\Jobs\SendEmailToSubscriber($scheduledSend));
            
            $this->info("Dispatched email job for scheduled send #{$scheduledSend->id} for campaign #{$campaign->id}");
            
        } catch (\Exception $e) {
            $scheduledSend->markAsFailed($e->getMessage());
            $this->error("Failed to process scheduled send #{$scheduledSend->id}: " . $e->getMessage());
            throw $e; // Re-throw to be caught by the outer try-catch
        }
    }

    /**
     * Create a new campaign instance for a recurring send.
     *
     * @param \App\Models\Newsletter\Campaign $templateCampaign
     * @param \Carbon\Carbon $sendDate
     * @return \App\Models\Newsletter\Campaign
     */
    protected function createRecurringCampaignInstance(Campaign $templateCampaign, Carbon $sendDate): Campaign
    {
        // Create a new campaign based on the template
        $newCampaign = $templateCampaign->replicate([
            'status',
            'send_type',
            'scheduled_at',
            'sent_at',
            'total_recipients',
            'sent_count',
            'failed_count',
            'created_at',
            'updated_at',
        ]);

        // Set the parent campaign ID and update the name
        $newCampaign->parent_id = $templateCampaign->id;
        $newCampaign->name = $templateCampaign->name . ' - ' . $sendDate->format('Y-m-d');
        $newCampaign->status = 'scheduled';
        $newCampaign->send_type = 'scheduled';
        $newCampaign->scheduled_at = $sendDate;
        $newCampaign->save();

        return $newCampaign;
    }
}
