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
        
        \Log::info('SendEmailToSubscriber job started', [
            'scheduled_send_id' => $scheduledSend->id,
            'current_status' => $scheduledSend->status,
            'campaign_id' => $scheduledSend->campaign_id,
            'subscriber_id' => $scheduledSend->subscriber_id
        ]);
        
        // Check if already sent, processing, or campaign is paused/cancelled
        if (!in_array($scheduledSend->status, ['pending', 'processing'])) {
            \Log::warning('SendEmailToSubscriber job skipped - invalid status', [
                'scheduled_send_id' => $scheduledSend->id,
                'current_status' => $scheduledSend->status,
                'allowed_statuses' => ['pending', 'processing']
            ]);
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
            \Log::info('About to create NewsletterMail instance', [
                'campaign_id' => $campaign->id,
                'subscriber_id' => $subscriber->id
            ]);
            
            $mail = new NewsletterMail($campaign, $subscriber);
            
            \Log::info('NewsletterMail created, about to send', [
                'campaign_id' => $campaign->id,
                'subscriber_id' => $subscriber->id
            ]);
            
            Mail::mailer('campus_smtp')->to($subscriber->email)->send($mail);
            
            \Log::info('Email sent successfully', [
                'campaign_id' => $campaign->id,
                'subscriber_id' => $subscriber->id
            ]);
            
            $result = $scheduledSend->markAsSent();
            \Log::info('markAsSent called', [
                'scheduled_send_id' => $scheduledSend->id,
                'result' => $result ? 'success' : 'failed',
                'updated_status' => $scheduledSend->fresh()->status
            ]);

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

            // Ensure campaign completion is evaluated even on failures
            $this->checkCampaignCompletion($campaign);

            // Do not rethrow; we've recorded failure and evaluated completion.
            // Keeping this non-throw prevents duplicate counting and stuck 'sending' status
            // if the last remaining sends are failures.
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
        $scheduledSend = $this->scheduledSend->fresh();
        $campaign = $scheduledSend->campaign;
        
        // Update the scheduled send
        $scheduledSend->update([
            'status' => 'failed',
            'failed_at' => now(),
            'error' => $exception->getMessage(),
        ]);

        // Record the error in the campaign
        if (method_exists($campaign, 'recordError')) {
            $campaign->recordError($exception);
        } else {
            // Fallback if recordError method doesn't exist
            $campaign->increment('failed_count');
            
            // Store error in metadata if the column exists
            if (\Schema::hasColumn($campaign->getTable(), 'metadata')) {
                $metadata = $campaign->metadata ?? [];
                $metadata['last_error'] = [
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'time' => now()->toDateTimeString(),
                ];
                $campaign->update(['metadata' => $metadata]);
            }
        }
    }
}
