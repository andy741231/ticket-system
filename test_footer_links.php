<?php

require_once __DIR__ . '/bootstrap/app.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Newsletter\Campaign;
use App\Models\Newsletter\Subscriber;
use App\Mail\NewsletterMail;

// Test HTML content with plain text footer
$htmlContent = '<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Newsletter</title>
  <style>
    body {
      margin: 0;
      padding: 20px;
      font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
      line-height: 1.6;
      background-color: #f4f4f4;
    }
    .newsletter-container {
      max-width: 600px;
      margin: 0 auto;
      background-color: #ffffff;
      border-radius: 8px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      overflow: hidden;
    }
  </style>
</head>
<body>
  <div class="newsletter-container">
    <div style="background: #c8102e; color: #ffffff; padding: 30px 20px; text-align: center; border-radius: 8px 8px 0 0;"><h1 style="margin: 0; font-size: 28px; font-weight: 300;">Newsletter Title</h1><p style="margin: 10px 0 0 0; opacity: 0.9; font-size: 14px;">Your weekly dose of updates</p></div><div style="background-color: #c8102e; color: #ffffff; padding: 25px 30px; text-align: center; border-top: 1px solid #eee;"><div>Thanks for reading! Forward this to someone who might find it useful.</div><p style="margin: 5px 0; color: #fff; font-size: 14px;">Unsubscribe | Update preferences | View in browser</p><p style="margin: 5px 0; color: #fff; font-size: 14px;">&copy; 2025 UH Population Health. All rights reserved.</p></div>
  </div>
</body>
</html>';

// Create test campaign and subscriber
$campaign = Campaign::first();
$subscriber = Subscriber::first();

if (!$campaign || !$subscriber) {
    echo "No campaign or subscriber found in database. Creating test data...\n";
    
    $campaign = new Campaign();
    $campaign->id = 999;
    $campaign->name = 'Test Campaign';
    $campaign->subject = 'Test Newsletter';
    $campaign->html_content = $htmlContent;
    
    $subscriber = new Subscriber();
    $subscriber->id = 999;
    $subscriber->email = 'test@example.com';
    $subscriber->first_name = 'Test';
    $subscriber->last_name = 'User';
    $subscriber->unsubscribe_token = 'test-token-123';
}

// Create the mailable and get processed content
$mailable = new NewsletterMail($campaign, $subscriber);
$content = $mailable->content();
$processedContent = $content->with['htmlContent'];

echo "=== ORIGINAL CONTENT ===\n";
echo "Unsubscribe | Update preferences | View in browser\n\n";

echo "=== PROCESSED CONTENT (Footer Section) ===\n";
// Extract just the footer section for clarity
preg_match('/<p[^>]*>.*?(Unsubscribe|<a[^>]*>Unsubscribe).*?<\/p>/i', $processedContent, $matches);
if ($matches) {
    echo $matches[0] . "\n\n";
} else {
    echo "Footer pattern not found, showing full content:\n";
    echo $processedContent . "\n\n";
}

echo "=== FUNCTIONAL LINKS FOUND ===\n";
preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>([^<]+)<\/a>/', $processedContent, $linkMatches);
for ($i = 0; $i < count($linkMatches[0]); $i++) {
    echo "Link: {$linkMatches[2][$i]} -> {$linkMatches[1][$i]}\n";
}
