<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Newsletter\Campaign;
use App\Models\Newsletter\Subscriber;
use App\Mail\NewsletterMail;
use Illuminate\Support\Facades\Mail;

try {
    $campaign = Campaign::find(79);
    $subscriber = Subscriber::find(1);
    
    if (!$campaign || !$subscriber) {
        echo "Campaign or subscriber not found\n";
        exit(1);
    }
    
    echo "Creating NewsletterMail instance...\n";
    $mail = new NewsletterMail($campaign, $subscriber);
    
    echo "Getting envelope...\n";
    $envelope = $mail->envelope();
    echo "Subject: " . $envelope->subject . "\n";
    
    echo "Getting content...\n";
    $content = $mail->content();
    echo "View: " . $content->view . "\n";
    
    echo "Rendering content...\n";
    $rendered = view($content->view, $content->with)->render();
    echo "Rendered successfully!\n";
    echo "Content length: " . strlen($rendered) . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
