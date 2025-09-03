<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illwarenterface::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

use Illuminate\Support\Facades\Mail;

// Test sending an email
Mail::raw('Test email content', function($message) {
    $message->to('mchan3@central.uh.edu')
            ->subject('Test Email from Script');
});

echo "Test email sent!\n";

// Check if there were any errors
$failures = Mail::failures();
if (!empty($failures)) {
    echo "Failed to send to: " . implode(", ", $failures) . "\n";
} else {
    echo "No failures reported. Check your mail server logs if email not received.\n";
}
