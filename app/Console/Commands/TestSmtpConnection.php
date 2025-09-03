<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class TestSmtpConnection extends Command
{
    protected $signature = 'mail:test-smtp';
    protected $description = 'Test SMTP connection by sending a test email';

    public function handle()
    {
        $this->info('Testing SMTP connection...');
        
        try {
            Mail::raw('This is a test email from UHPH Hub', function($message) {
                $message->to('test@example.com')
                        ->subject('SMTP Test Email');
            });
            
            $this->info('Test email sent successfully!');
            return 0;
        } catch (\Exception $e) {
            $error = 'SMTP Error: ' . $e->getMessage();
            $this->error($error);
            Log::error($error);
            return 1;
        }
    }
}
