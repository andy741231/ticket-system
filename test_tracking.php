<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;

// Set the application URL to match your environment
URL::forceRootUrl('http://localhost:8000');

// Sample test data
$campaignId = 1;
$subscriberId = 1;
$appKey = config('app.key');

// Generate the token the same way as in NewsletterMail
$token = hash('sha256', (string)$campaignId . (string)$subscriberId . $appKey);

// Generate the URL
$url = URL::route('newsletter.public.track-open', [
    'campaign' => $campaignId,
    'subscriber' => $subscriberId,
    'token' => $token
]);

echo "Generated URL: " . $url . "\n";
echo "Campaign ID: " . $campaignId . " (type: " . gettype($campaignId) . ")\n";
echo "Subscriber ID: " . $subscriberId . " (type: " . gettype($subscriberId) . ")\n";
echo "App Key: " . $appKey . "\n";
echo "Generated Token: " . $token . "\n";

// Test token verification
$expectedToken = hash('sha256', (string)$campaignId . (string)$subscriberId . $appKey);
$tokenMatch = hash_equals($expectedToken, $token) ? '✅ MATCH' : '❌ MISMATCH';
echo "Token verification: " . $tokenMatch . "\n";

// Test with different data types
$testCampaign = '1';
$testSubscriber = '1';
$testToken = hash('sha256', $testCampaign . $testSubscriber . $appKey);
$testMatch = hash_equals($expectedToken, $testToken) ? '✅ MATCH' : '❌ MISMATCH';
echo "Test with string IDs: " . $testMatch . "\n";
