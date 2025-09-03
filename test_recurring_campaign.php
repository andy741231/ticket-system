<?php

use App\Jobs\ProcessRecurringCampaigns;
use App\Models\Newsletter\Campaign;
use App\Models\Newsletter\ScheduledSend;
use App\Models\Newsletter\Subscriber;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Start a database transaction
DB::beginTransaction();

try {
    echo "Starting test for recurring campaign...\n";
    
    // Create a test subscriber if none exists
    $subscriber = Subscriber::firstOrCreate(
        ['email' => 'test@example.com'],
        [
            'name' => 'Test User',
            'status' => 'active',
            'subscribed_at' => now(),
        ]
    );
    
    echo "Using subscriber: {$subscriber->email} (ID: {$subscriber->id})\n";
    
    // Create a test recurring campaign
    $campaign = Campaign::create([
        'name' => 'Test Recurring Campaign',
        'subject' => 'Test Recurring Campaign',
        'from_name' => 'Test Sender',
        'from_email' => 'sender@example.com',
        'html_content' => '<h1>Test Recurring Campaign</h1><p>This is a test email.</p>',
        'send_type' => 'recurring',
        'status' => 'active',
        'recurring_config' => [
            'frequency' => 'daily',
            'start_date' => now()->toDateTimeString(),
            'end_date' => now()->addDays(7)->toDateTimeString(),
            'next_scheduled_at' => now()->toDateTimeString(),
        ],
        'send_to_all' => true,
        'created_by' => 1,
    ]);
    
    echo "Created test campaign: {$campaign->name} (ID: {$campaign->id})\n";
    
    // Run the ProcessRecurringCampaigns job
    echo "Running ProcessRecurringCampaigns job...\n";
    $job = new ProcessRecurringCampaigns($campaign);
    $job->handle();
    
    // Check if scheduled sends were created
    $scheduledSends = $campaign->scheduledSends()->count();
    echo "Scheduled sends created: {$scheduledSends}\n";
    
    if ($scheduledSends > 0) {
        $firstSend = $campaign->scheduledSends()->first();
        echo "First send scheduled for: {$firstSend->scheduled_at}\n";
        echo "First send status: {$firstSend->status}\n";
    }
    
    // Check if next scheduled date was set
    $campaign->refresh();
    $nextScheduled = $campaign->recurring_config['next_scheduled_at'] ?? null;
    
    if ($nextScheduled) {
        echo "Next scheduled run: {$nextScheduled}\n";
        
        // Check if the next run is in the future
        $nextRun = Carbon::parse($nextScheduled);
        $now = now();
        
        if ($nextRun->gt($now)) {
            echo "Next run is correctly scheduled for the future.\n";
        } else {
            echo "WARNING: Next run is not in the future.\n";
        }
    } else {
        echo "WARNING: No next scheduled run was set.\n";
    }
    
    echo "Test completed successfully!\n";
    
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
} finally {
    // Rollback the transaction to avoid polluting the database
    DB::rollBack();
    echo "Transaction rolled back. No changes were made to the database.\n";
}
