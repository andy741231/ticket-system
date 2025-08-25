<?php

namespace App\Jobs;

use App\Mail\NewsletterMail;
use App\Models\Newsletter\AnalyticsEvent;
use App\Models\Newsletter\ScheduledSend;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendEmailToSubscriber implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $timeout = 60;
    public $tries = 3;

    public function __construct(
        public ScheduledSend $scheduledSend
    ) {}

    public function handle(): void
    {
        $scheduledSend = $this->scheduledSend->fresh();
        
        // Check if already sent or campaign is paused/cancelled
        if ($scheduledSend->status !== 'pending') {
            return;
        }

        $campaign = $scheduledSend->campaign;
        $subscriber = $scheduledSend->subscriber;

        // Check if campaign is still active
        if (!in_array($campaign->status, ['sending', 'scheduled'])) {
            $scheduledSend->update(['status' => 'skipped']);
            return;
        }

        // Check if subscriber is still active
        if ($subscriber->status !== 'active') {
            $scheduledSend->update(['status' => 'skipped']);
            return;
        }

        try {
            // Send the email
            Mail::to($subscriber->email)->send(new NewsletterMail($campaign, $subscriber));

            // Mark as sent
            $scheduledSend->markAsSent();

            // Record analytics event
            AnalyticsEvent::create([
                'campaign_id' => $campaign->id,
                'subscriber_id' => $subscriber->id,
                'event_type' => 'sent',
                'created_at' => now(),
            ]);

            // Update campaign sent count
            $campaign->increment('sent_count');

            // Check if campaign is complete
            $this->checkCampaignCompletion($campaign);

        } catch (\Exception $e) {
            // Mark as failed
            $scheduledSend->markAsFailed($e->getMessage());
            
            // Update campaign failed count
            $campaign->increment('failed_count');

            // Log the error
            \Log::error('Failed to send email to subscriber', [
                'campaign_id' => $campaign->id,
                'subscriber_id' => $subscriber->id,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    private function checkCampaignCompletion($campaign): void
    {
        $totalScheduled = $campaign->scheduledSends()->count();
        $totalProcessed = $campaign->scheduledSends()
            ->whereIn('status', ['sent', 'failed', 'skipped'])
            ->count();

        if ($totalScheduled === $totalProcessed) {
            $campaign->markAsSent();
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->scheduledSend->markAsFailed($exception->getMessage());
        $this->scheduledSend->campaign->increment('failed_count');
    }
}
