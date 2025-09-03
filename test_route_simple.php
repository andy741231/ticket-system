<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

// Set the application URL to match your environment
URL::forceRootUrl('http://localhost:8000');

// Test the route generation
try {
    $url = URL::route('newsletter.public.track-open', [
        'campaign' => 1,
        'subscriber' => 1,
        'token' => 'testtoken123'
    ]);
    
    echo "Generated URL: " . $url . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
