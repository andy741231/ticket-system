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

        // Build recipient emails (includes directory-only emails when protected group selected)
        $emails = method_exists($this->campaign, 'getRecipientEmails')
            ? $this->campaign->getRecipientEmails()
            : $this->campaign->getRecipientsQuery()->pluck('email')->unique();

        // Ensure we have Subscriber records for all emails
        $existing = Subscriber::query()->whereIn('email', $emails)->get()->keyBy(fn($s) => strtolower($s->email));
        $recipients = collect();
        foreach ($emails as $email) {
            $key = strtolower($email);
            $subscriber = $existing->get($key);
            if (!$subscriber) {
                // Create a minimal active subscriber record so the mailer pipeline works
                $subscriber = Subscriber::create([
                    'email' => $key,
                    'status' => 'active',
                    'metadata' => ['source' => 'directory']
                ]);
                $existing->put($key, $subscriber);
            }
            $recipients->push($subscriber);
        }

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

        $createdCount = 0;
        $batchSize = 100; // Process in batches to avoid memory issues with large recipient lists
        
        foreach (array_chunk($scheduledSends, $batchSize) as $batch) {
            try {
                // Use insertOrIgnore to skip duplicates
                $result = \DB::table('newsletter_scheduled_sends')->insertOrIgnore($batch);
                $createdCount += $result;
            } catch (\Exception $e) {
                \Log::error('Error creating scheduled sends batch', [
                    'campaign_id' => $this->campaign->id,
                    'error' => $e->getMessage(),
                    'batch_size' => count($batch)
                ]);
                
                // If we can't insert, try individual inserts to find the problematic record
                foreach ($batch as $send) {
                    try {
                        \DB::table('newsletter_scheduled_sends')->insertOrIgnore($send);
                        $createdCount++;
                    } catch (\Exception $e) {
                        \Log::error('Failed to create scheduled send', [
                            'campaign_id' => $this->campaign->id,
                            'subscriber_id' => $send['subscriber_id'] ?? null,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
        }
        
        if ($createdCount === 0) {
            // No sends were created, mark as failed
            $this->campaign->update([
                'status' => 'draft',
                'total_recipients' => 0,
                'last_error' => 'Failed to create any scheduled sends. Check logs for details.'
            ]);
            
            \Log::error('Failed to create any scheduled sends for campaign', [
                'campaign_id' => $this->campaign->id,
                'send_to_all' => $this->campaign->send_to_all,
                'target_groups' => $this->campaign->target_groups,
                'scheduled_sends_count' => count($scheduledSends)
            ]);
            return;
        }

        \Log::info('Updated campaign with recipient count', [
            'campaign_id' => $this->campaign->id,
            'total_recipients' => $createdCount
        ]);

        // Check if this is a test send (draft campaign being tested)
        $metadata = $this->campaign->metadata ?? [];
        $isTestSend = $metadata['is_test_send'] ?? false;

        // Update campaign status and recipient count
        // For test sends, status is already 'sending' from sendDraft method
        $this->campaign->update([
            'status' => 'sending',
            'total_recipients' => $createdCount,
            'last_error' => null
        ]);
        
        \Log::info('Campaign status updated', [
            'campaign_id' => $this->campaign->id,
            'is_test_send' => $isTestSend,
            'status' => 'sending'
        ]);

        // Dispatch the ProcessScheduledSends job to process the pending sends
        ProcessScheduledSends::dispatch()
            ->onQueue('default')
            ->delay(now()->addSeconds(5)); // Small delay to ensure all scheduled sends are created

        \Log::info('Dispatched ProcessScheduledSends job', [
            'campaign_id' => $this->campaign->id,
            'scheduled_sends_count' => $createdCount
        ]);
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
