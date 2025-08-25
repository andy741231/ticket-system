<?php

namespace App\Jobs;

use App\Models\Newsletter\Campaign;
use App\Models\Newsletter\ScheduledSend;
use App\Models\Newsletter\Subscriber;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendCampaign implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $timeout = 300;
    public $tries = 3;

    public function __construct(
        public Campaign $campaign
    ) {}

    public function handle(): void
    {
        // Check if campaign is still valid to send
        if (!$this->campaign->canBeSent()) {
            return;
        }

        // Get recipients based on campaign settings
        $recipientQuery = Subscriber::active();
        
        if (!$this->campaign->send_to_all && !empty($this->campaign->target_groups)) {
            $recipientQuery->whereHas('groups', function ($query) {
                $query->whereIn('newsletter_groups.id', $this->campaign->target_groups);
            });
        }

        $recipients = $recipientQuery->get();

        // Create scheduled sends for each recipient
        $scheduledSends = [];
        foreach ($recipients as $recipient) {
            $scheduledSends[] = [
                'campaign_id' => $this->campaign->id,
                'subscriber_id' => $recipient->id,
                'status' => 'pending',
                'scheduled_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Bulk insert scheduled sends
        ScheduledSend::insert($scheduledSends);

        // Update campaign status and recipient count
        $this->campaign->update([
            'status' => 'sending',
            'total_recipients' => count($scheduledSends),
        ]);

        // Dispatch individual email jobs with rate limiting
        $scheduledSends = ScheduledSend::where('campaign_id', $this->campaign->id)
            ->where('status', 'pending')
            ->get();

        foreach ($scheduledSends as $index => $scheduledSend) {
            // Dispatch with delay to respect rate limits (1 email per second)
            SendEmailToSubscriber::dispatch($scheduledSend)
                ->delay(now()->addSeconds($index));
        }
    }

    public function failed(\Throwable $exception): void
    {
        // Mark campaign as failed
        $this->campaign->update([
            'status' => 'draft',
        ]);

        // Log the error
        \Log::error('Campaign send failed', [
            'campaign_id' => $this->campaign->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
