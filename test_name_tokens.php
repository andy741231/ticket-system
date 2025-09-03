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
    <div style="background: #c8102e; color: #ffffff; padding: 30px 20px; text-align: center; border-radius: 8px 8px 0 0;"><h1 style="margin: 0; font-size: 28px; font-weight: 300;">Newsletter Title</h1><p style="margin: 10px 0 0 0; opacity: 0.9; font-size: 14px;">Your weekly dose of updates</p></div><div style="margin: 0; padding: 15px 12px; background-color: transparent; font-size: 16px; line-height: 1.6; color: #666666;"><p>Click to edit this text...</p>{{ first_name }}{{ last_name }}</div><div style="background-color: #c8102e; color: #ffffff; padding: 25px 30px; text-align: center; border-top: 1px solid #eee;"><div>Thanks for reading! Forward this to someone who might find it useful.</div><p style="margin: 5px 0; color: #fff; font-size: 14px;">Unsubscribe | Update preferences | View in browser</p><p style="margin: 5px 0; color: #fff; font-size: 14px;">&copy; 2025 UH Population Health. All rights reserved.</p></div>
  </div>
</body>
</html>';

// Create test campaign
$campaign = new Campaign();
$campaign->id = 999;
$campaign->name = 'Test Name Tokens';
$campaign->subject = 'Test Name Replacement';
$campaign->html_content = $htmlContent;

// Create test subscriber with name data
$subscriber = new Subscriber();
$subscriber->id = 999;
$subscriber->email = 'test@example.com';
$subscriber->first_name = 'John';
$subscriber->last_name = 'Doe';
$subscriber->name = 'John Doe';
$subscriber->unsubscribe_token = 'test-token-123';

echo "=== TESTING NAME TOKEN REPLACEMENT ===\n";
echo "Subscriber data:\n";
echo "  first_name: '{$subscriber->first_name}'\n";
echo "  last_name: '{$subscriber->last_name}'\n";
echo "  name: '{$subscriber->name}'\n\n";

echo "Original tokens: {{ first_name }}{{ last_name }}\n\n";

// Process through NewsletterMail
$mailable = new NewsletterMail($campaign, $subscriber);
$content = $mailable->content();
$processedHtml = $content->with['htmlContent'];

// Extract the section with name tokens
preg_match('/<div[^>]*><p>Click to edit this text\.\.\.<\/p>([^<]*)<\/div>/', $processedHtml, $nameMatch);
if ($nameMatch) {
    echo "Processed result: '{$nameMatch[1]}'\n";
    if (trim($nameMatch[1]) === 'JohnDoe') {
        echo "✅ SUCCESS: Tokens replaced correctly!\n";
    } else {
        echo "❌ ISSUE: Expected 'JohnDoe', got '{$nameMatch[1]}'\n";
    }
} else {
    echo "❌ Could not find the name section in processed HTML\n";
    echo "Full processed content:\n";
    echo substr($processedHtml, 0, 1000) . "...\n";
}
