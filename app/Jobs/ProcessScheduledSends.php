<?php

namespace App\Jobs;

use App\Models\Newsletter\ScheduledSend;
use App\Jobs\SendEmailToSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessScheduledSends implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
    public $timeout = 300; // 5 minutes

    /**
     * The number of sends to process in this batch.
     *
     * @var int
     */
    protected $limit;

    /**
     * Create a new job instance.
     *
     * @param int $limit The maximum number of sends to process in this batch
     * @return void
     */
    public function __construct(int $limit = 100)
    {
        $this->limit = $limit;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $processed = 0;
        $now = now();

        // Process scheduled sends in batches to avoid memory issues
        ScheduledSend::with(['campaign', 'subscriber'])
            ->where('status', ScheduledSend::STATUS_PENDING)
            ->where('scheduled_at', '<=', $now)
            ->orderBy('scheduled_at')
            ->limit($this->limit)
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

        if ($processed > 0) {
            Log::info("Processed {$processed} scheduled sends");
        } else {
            Log::info("No scheduled sends found to process");
            // Check if there are any campaigns stuck in 'sending' state with no pending scheduled sends
            $stuckCampaigns = \App\Models\Newsletter\Campaign::where('status', 'sending')
                ->whereDoesntHave('scheduledSends', function($query) {
                    $query->where('status', 'pending')
                          ->orWhere('status', 'processing');
                })
                ->get();
                
            foreach ($stuckCampaigns as $campaign) {
                // Check if there are any completed sends
                $hasSentSends = $campaign->scheduledSends()
                    ->whereIn('status', ['sent', 'failed'])
                    ->exists();
                    
                if ($hasSentSends) {
                    // If some sends were processed, mark as sent
                    $campaign->markAsSent();
                    Log::info("Marked campaign as sent as all sends were processed", [
                        'campaign_id' => $campaign->id
                    ]);
                } else {
                    // If no sends were processed at all, mark as draft with error
                    $campaign->update([
                        'status' => 'draft',
                        'error_message' => 'No scheduled sends were created for this campaign',
                    ]);
                    
                    Log::warning("Reset stuck campaign to draft status", [
                        'campaign_id' => $campaign->id,
                        'reason' => 'No scheduled sends were created'
                    ]);
                }
            }
        }

        // Check if there are no more pending sends for this campaign
        $pendingSends = ScheduledSend::where('status', ScheduledSend::STATUS_PENDING)
            ->whereHas('campaign', function($query) use ($now) {
                $query->where('status', 'sending');
            })
            ->where('scheduled_at', '<=', $now)
            ->exists();

        // If no more pending sends, update campaign status to sent
        if (!$pendingSends) {
            $campaignsToUpdate = \App\Models\Newsletter\Campaign::where('status', 'sending')
                ->whereDoesntHave('scheduledSends', function($query) {
                    $query->where('status', '!=', 'sent')
                          ->where('status', '!=', 'failed');
                })
                ->get();

            foreach ($campaignsToUpdate as $campaign) {
                $campaign->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
                Log::info("Marked campaign #{$campaign->id} as sent");
            }
        }
    }

    /**
     * Process a single scheduled send.
     *
     * @param  \App\Models\Newsletter\ScheduledSend  $scheduledSend
     * @param  \Carbon\Carbon  $now
     * @return void
     */
    protected function processScheduledSend(ScheduledSend $scheduledSend, $now)
    {
        $campaign = $scheduledSend->campaign;
        
        // Skip if the scheduled send is already processed or cancelled
        if (!$scheduledSend->isPending()) {
            return;
        }

        // Mark as processing
        if (!$scheduledSend->markAsProcessing()) {
            throw new \RuntimeException("Failed to mark scheduled send #{$scheduledSend->id} as processing");
        }

        try {
            // If this is a recurring campaign, make a copy of the campaign for this send
            if ($campaign->isRecurring()) {
                $campaign = $this->createRecurringCampaignInstance($campaign, $scheduledSend->scheduled_at);
            }

            // Mark as processing first
            $scheduledSend->markAsProcessing();
            
            // Get a fresh instance to ensure we have the latest data
            $scheduledSend = $scheduledSend->fresh();
            
            // Dispatch the send job with the scheduled send instance
            SendEmailToSubscriber::dispatch($scheduledSend);
            
            Log::info("Processed scheduled send #{$scheduledSend->id} for campaign #{$campaign->id}");
            
        } catch (\Exception $e) {
            $scheduledSend->markAsFailed($e->getMessage());
            
            // Update campaign failed count
            $campaign->increment('failed_count');
            
            Log::error("Failed to process scheduled send #{$scheduledSend->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e; // Re-throw to be caught by the outer try-catch
        }
    }

    /**
     * Create a new campaign instance for a recurring send.
     *
     * @param  \App\Models\Newsletter\Campaign  $templateCampaign
     * @param  \Carbon\Carbon  $sendDate
     * @return \App\Models\Newsletter\Campaign
     */
    protected function createRecurringCampaignInstance($templateCampaign, $sendDate)
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
