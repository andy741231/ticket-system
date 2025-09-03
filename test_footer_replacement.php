<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Newsletter\Campaign;
use App\Models\Newsletter\Subscriber;
use App\Mail\NewsletterMail;

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

// Create test campaign
$campaign = new Campaign();
$campaign->id = 999;
$campaign->name = 'Test Footer Campaign';
$campaign->subject = 'Test Footer Links';
$campaign->html_content = $htmlContent;

// Get or create test subscriber
$subscriber = Subscriber::first();
if (!$subscriber) {
    $subscriber = new Subscriber();
    $subscriber->id = 999;
    $subscriber->email = 'test@example.com';
    $subscriber->first_name = 'Test';
    $subscriber->last_name = 'User';
    $subscriber->unsubscribe_token = 'test-token-123';
}

echo "=== TESTING FOOTER REPLACEMENT ===\n";
echo "Original contains 'Unsubscribe | Update preferences | View in browser': " . 
     (strpos($htmlContent, 'Unsubscribe | Update preferences | View in browser') !== false ? 'YES' : 'NO') . "\n\n";

// Process through NewsletterMail
$mailable = new NewsletterMail($campaign, $subscriber);
$content = $mailable->content();
$processedHtml = $content->with['htmlContent'];

echo "Processed contains links: " . (strpos($processedHtml, '<a href=') !== false ? 'YES' : 'NO') . "\n";

// Extract footer section
preg_match('/<p[^>]*>.*?(Unsubscribe|<a[^>]*>Unsubscribe).*?<\/p>/i', $processedHtml, $footerMatch);
if ($footerMatch) {
    echo "\nFooter section after processing:\n";
    echo $footerMatch[0] . "\n\n";
}

// Count and list all links
preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>([^<]+)<\/a>/', $processedHtml, $linkMatches);
echo "Total links found: " . count($linkMatches[0]) . "\n";
for ($i = 0; $i < count($linkMatches[0]); $i++) {
    echo "  - {$linkMatches[2][$i]} -> {$linkMatches[1][$i]}\n";
}
