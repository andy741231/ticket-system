<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Newsletter\Campaign;
use App\Models\Newsletter\Subscriber;
use App\Mail\NewsletterMail;

// Test HTML content with functional links
$htmlContent = '
<!DOCTYPE html>
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
    <div style="background: #c8102e; color: #ffffff; padding: 30px 20px; text-align: center; border-radius: 8px 8px 0 0;">
      <h1 style="margin: 0; font-size: 28px; font-weight: 300;">Newsletter Title</h1>
      <p style="margin: 10px 0 0 0; opacity: 0.9; font-size: 14px;">Your weekly dose of updates</p>
    </div>
    <div style="background-color: #c8102e; color: #ffffff; padding: 25px 30px; text-align: center; border-top: 1px solid #eee;">
      <div>Thanks for reading! Forward this to someone who might find it useful.</div>
      <p style="margin: 5px 0; color: #fff; font-size: 14px;">
        <a href="{{unsubscribe_url}}" style="color: #fff; text-decoration: underline;">Unsubscribe</a> | 
        <a href="{{preferences_url}}" style="color: #fff; text-decoration: underline;">Update preferences</a> | 
        <a href="{{view_in_browser_url}}" style="color: #fff; text-decoration: underline;">View in browser</a>
      </p>
      <p style="margin: 5px 0; color: #fff; font-size: 14px;">&copy; 2025 UH Population Health. All rights reserved.</p>
    </div>
  </div>
</body>
</html>';

// Create a test campaign with the functional HTML
$campaign = new Campaign();
$campaign->id = 999;
$campaign->name = 'Test Campaign';
$campaign->subject = 'Test Newsletter';
$campaign->html_content = $htmlContent;

// Create a test subscriber
$subscriber = new Subscriber();
$subscriber->id = 999;
$subscriber->email = 'test@example.com';
$subscriber->first_name = 'Test';
$subscriber->last_name = 'User';
$subscriber->unsubscribe_token = 'test-token-123';

// Create the mailable and process the content
$mailable = new NewsletterMail($campaign, $subscriber);
$processedContent = $mailable->content()->with['htmlContent'];

echo "=== PROCESSED NEWSLETTER CONTENT ===\n";
echo $processedContent;
echo "\n\n=== LINKS EXTRACTED ===\n";

// Extract and show the functional links
preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>([^<]+)<\/a>/', $processedContent, $matches);
for ($i = 0; $i < count($matches[0]); $i++) {
    echo "Link: {$matches[2][$i]} -> {$matches[1][$i]}\n";
}
