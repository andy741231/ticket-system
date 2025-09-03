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
    echo "=== Starting Recurring Campaign Flow Test ===\n\n";
    
    // Create a test subscriber if none exists
    $subscriber = Subscriber::firstOrCreate(
        ['email' => 'test@example.com'],
        [
            'name' => 'Test User',
            'status' => 'active',
            'subscribed_at' => now(),
        ]
    );
    
    echo "[1/5] Using subscriber: {$subscriber->email} (ID: {$subscriber->id})\n";
    
    // Create a test recurring campaign
    echo "[2/5] Creating test recurring campaign...\n";
    $campaign = Campaign::create([
        'name' => 'Test Recurring Campaign',
        'subject' => 'Test Recurring Campaign',
        'from_name' => 'Test Sender',
        'from_email' => 'sender@example.com',
        'content' => '<h1>Test Recurring Campaign</h1><p>This is a test email.</p>',
        'html_content' => '<h1>Test Recurring Campaign</h1><p>This is a test email.</p>',
        'send_type' => 'recurring',
        'status' => 'scheduled',
        'recurring_config' => [
            'frequency' => 'daily',
            'start_date' => now()->toDateTimeString(),
            'end_date' => now()->addDays(7)->toDateTimeString(),
            'next_scheduled_at' => now()->toDateTimeString(),
        ],
        'send_to_all' => true,
        'created_by' => 1,
    ]);
    
    echo "    - Created campaign ID: {$campaign->id}\n";
    
    // Verify campaign is marked as recurring
    if (!$campaign->isRecurring()) {
        throw new Exception("Campaign is not recognized as a recurring campaign");
    }
    echo "[3/5] Verified campaign is marked as recurring\n";
    
    // Run the ProcessRecurringCampaigns job
    echo "[4/5] Running ProcessRecurringCampaigns job...\n";
    $job = new ProcessRecurringCampaigns($campaign);
    $job->handle();
    
    // Refresh the campaign to get updated data
    $campaign->refresh();
    
    // Check if scheduled sends were created
    $scheduledSends = $campaign->scheduledSends()->count();
    echo "[5/5] Scheduled sends created: {$scheduledSends}\n";
    
    if ($scheduledSends > 0) {
        $firstSend = $campaign->scheduledSends()->first();
        echo "    - First send scheduled for: {$firstSend->scheduled_at}\n";
        echo "    - First send status: {$firstSend->status}\n";
    }
    
    // Check if next scheduled date was set
    $nextScheduled = $campaign->recurring_config['next_scheduled_at'] ?? null;
    
    if ($nextScheduled) {
        echo "\n[SUCCESS] Next scheduled run: {$nextScheduled}\n";
        
        // Check if the next run is in the future
        $nextRun = Carbon::parse($nextScheduled);
        $now = now();
        
        if ($nextRun->gt($now)) {
            echo "- Next run is correctly scheduled for the future.\n";
        } else {
            echo "- WARNING: Next run is not in the future.\n";
        }
    } else {
        echo "\n[WARNING] No next scheduled run was set.\n";
    }
    
    echo "\n=== Test completed successfully! ===\n";
    
} catch (\Exception $e) {
    echo "\n[ERROR] " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
} finally {
    // Rollback the transaction to avoid polluting the database
    DB::rollBack();
    echo "\n=== Transaction rolled back. No changes were made to the database. ===\n";
}
