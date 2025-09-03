<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Mail;

// Test SMTP connection
try {
    // Test campus_smtp mailer
    $transport = Mail::mailer('campus_smtp')->getSymfonyTransport();
    
    try {
        // Try to connect to the SMTP server
        $transport->getStream()->initialize();
        echo "✅ Successfully connected to campus_smtp mailer\n";
    } catch (\Exception $e) {
        echo "❌ Failed to connect to campus_smtp mailer: " . $e->getMessage() . "\n";
    } finally {
        // Ensure the connection is closed
        if (method_exists($transport, 'getStream') && $transport->getStream()) {
            $transport->getStream()->terminate();
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error testing campus_smtp mailer: " . $e->getMessage() . "\n";
}

// Test sending a simple email
try {
    Mail::mailer('campus_smtp')
        ->to('test@example.com')
        ->send(new class extends \Illuminate\Mail\Mailable {
            public function build() {
                return $this->subject('Test Email from UHPH Hub')
                           ->view('emails.test');
            }
        });
    
    echo "✅ Test email sent successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Failed to send test email: " . $e->getMessage() . "\n";
    
    // Log the full exception for debugging
    \Log::error('Email send failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    echo "Check the Laravel logs for more details.\n";
}
