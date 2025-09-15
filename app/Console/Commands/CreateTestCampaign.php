<?php

namespace App\Console\Commands;

use App\Models\Newsletter\Campaign;
use App\Models\Newsletter\Subscriber;
use Illuminate\Console\Command;

class CreateTestCampaign extends Command
{
    protected $signature = 'campaign:test';
    protected $description = 'Create and send a test campaign to a test subscriber';

    public function handle()
    {
        // Ensure we have a test subscriber
        $subscriber = Subscriber::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'first_name' => 'Test',
                'last_name' => 'User',
                'status' => 'active'
            ]
        );

        $this->info('Test subscriber: ' . $subscriber->email);

        // Get the first admin user or create one if none exists
        $user = \App\Models\User::first();
        if (!$user) {
            $user = \App\Models\User::create([
                'first_name' => 'Test',
                'last_name' => 'Admin',
                'username' => 'test.admin',
                'email' => 'admin@example.com',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]);
            $this->info('Created test admin user with email: admin@example.com');
        }

        // Create a test campaign
        $campaign = Campaign::create([
            'name' => 'Test Campaign ' . now()->format('Y-m-d H:i:s'),
            'subject' => 'Test Email',
            'content' => json_encode([
                'type' => 'doc',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'Hello {{first_name}}, this is a test email.'
                            ]
                        ]
                    ]
                ]
            ]),
            'html_content' => '<p>Hello {{first_name}}, this is a test email.</p>',
            'status' => 'draft',
            'send_type' => 'immediate',
            'total_recipients' => 1,
            'sent_count' => 0,
            'failed_count' => 0,
            'created_by' => $user->id
        ]);

        $this->info('Created test campaign: ' . $campaign->name);

        // Create a scheduled send for the test subscriber
        $scheduledSend = new \App\Models\Newsletter\ScheduledSend([
            'subscriber_id' => $subscriber->id,
            'scheduled_at' => now(),
            'status' => 'pending',
            'send_attempts' => 0,
        ]);
        
        $campaign->scheduledSends()->save($scheduledSend);
        $this->info('Created scheduled send for test subscriber');

        // Update campaign with recipient count
        $campaign->update(['total_recipients' => 1]);
        
        // Dispatch the SendCampaign job
        \App\Jobs\SendCampaign::dispatch($campaign);
        $this->info('Dispatched SendCampaign job');

        $this->info('Test campaign created and queued for sending!');
        $this->info('Run "php artisan queue:work" in a separate terminal to process the queue');

        return 0;
    }
}
