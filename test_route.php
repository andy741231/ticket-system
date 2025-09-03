<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illware_Contracts_Console_Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

// Set the application URL to match your environment
URL::forceRootUrl(config('app.url'));

// Test the route generation
try {
    $url = route('newsletter.public.track-open', [
        'campaign' => 1,
        'subscriber' => 1,
        'token' => hash('sha256', '11' . config('app.key'))
    ]);
    
    echo "Generated URL: " . $url . "\n";
    echo "App URL: " . config('app.url') . "\n";
    echo "App Key: " . config('app.key') . "\n";
} catch (Exception $e) {
    echo "Error generating route: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
