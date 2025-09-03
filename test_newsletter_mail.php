<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Newsletter\Campaign;
use App\Models\Newsletter\Subscriber;
use App\Mail\NewsletterMail;
use Illuminate\Support\Facades\Mail;

// Enable query logging
\DB::enableQueryLog();

// Create a test campaign and subscriber
$campaign = Campaign::firstOrCreate(
    ['name' => 'Test Campaign'],
    [
        'subject' => 'Test Newsletter',
        'preview_text' => 'This is a test email',
        'content' => 'This is a test email',
        'html_content' => '<h1>Test Newsletter</h1><p>This is a test email.</p>',
        'status' => 'sending',
        'send_type' => 'immediate',
        'scheduled_at' => now(),
        'sent_at' => now(),
        'send_to_all' => false,
        'total_recipients' => 1,
        'sent_count' => 0,
        'failed_count' => 0,
        'created_by' => 1, // Assuming user ID 1 exists
    ]
);

$subscriber = Subscriber::firstOrCreate(
    ['email' => 'test@example.com'],
    [
        'name' => 'Test User',
        'first_name' => 'Test',
        'last_name' => 'User',
        'organization' => 'Test Org',
        'status' => 'active',
        'unsubscribe_token' => \Illuminate\Support\Str::random(32),
        'subscribed_at' => now(),
        'metadata' => json_encode([]),
    ]
);

// Log the mailer configuration
\Log::info('Mail configuration', [
    'default' => config('mail.default'),
    'mailers' => array_keys(config('mail.mailers')),
    'from' => config('mail.from'),
]);

try {
    // Send the test email
    $mail = new NewsletterMail($campaign, $subscriber);
    
    \Log::info('Sending test email', [
        'to' => $subscriber->email,
        'campaign_id' => $campaign->id,
        'subscriber_id' => $subscriber->id,
    ]);
    
    Mail::mailer('campus_smtp')->to($subscriber->email)->send($mail);
    
    echo "Test email sent successfully!\n";
    
} catch (\Exception $e) {
    \Log::error('Failed to send test email', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    
    echo "Error sending test email: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

// Dump the executed queries
// echo "\nExecuted Queries:\n";
// foreach (\DB::getQueryLog() as $query) {
//     echo $query['query'] . "\n" . print_r($query['bindings'], true) . "\n";
// }
