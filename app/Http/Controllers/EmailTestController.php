<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Inertia\Inertia;

class EmailTestController extends Controller
{
    /**
     * Show the email test form.
     */
    public function index()
    {
        return Inertia::render('Tools/EmailTest');
    }

    /**
     * Send a test email using current SMTP settings.
     */
    public function send(Request $request)
    {
        $validated = $request->validate([
            'to' => ['required', 'email'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
        ]);

        // Align with PHPMailer example: UH SMTP requires only host on port 25, no auth/encryption
        // Apply at runtime so this test page works without .env edits when on UH network
        config([
            'mail.default' => 'smtp',
            // Use DSN to force no STARTTLS and relaxed verification (mirrors PHPMailer opts)
            'mail.mailers.smtp.url' => 'smtp://post-office.uh.edu:25?tls=0&verify_peer=0&verify_peer_name=0&allow_self_signed=1',
            'mail.mailers.smtp.host' => 'post-office.uh.edu',
            'mail.mailers.smtp.port' => 25,
            'mail.mailers.smtp.encryption' => null,
            'mail.mailers.smtp.tls' => false,
            'mail.mailers.smtp.username' => null,
            'mail.mailers.smtp.password' => null,
            // Provide HELO domain explicitly
            'mail.mailers.smtp.local_domain' => 'central.uh.edu',
            // Disable TLS verification if encryption is later enabled
            'mail.mailers.smtp.stream' => [
                'ssl' => [
                    'allow_self_signed' => true,
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ],
        ]);

        // Ensure the mailer is rebuilt with the updated runtime config
        try {
            app('mail.manager')->forgetDriver('smtp');
        } catch (\Throwable $e) {
            // noop if method not available in this framework version
        }

        // Collect configuration and connectivity diagnostics
        $host = (string) config('mail.mailers.smtp.host');
        $port = (int) config('mail.mailers.smtp.port');
        $encryption = config('mail.mailers.smtp.encryption');
        $username = (string) config('mail.mailers.smtp.username');
        $fromAddr = (string) config('mail.from.address');
        $fromName = (string) config('mail.from.name');

        $resolvedIp = @gethostbyname($host);
        $dnsResolved = $resolvedIp && $resolvedIp !== $host;

        $errno = 0; $errstr = '';
        $socketOk = false;
        try {
            $conn = @fsockopen($host, $port, $errno, $errstr, 5.0);
            if ($conn) {
                $socketOk = true;
                fclose($conn);
            }
        } catch (\Throwable $t) {
            $socketOk = false;
            if (!$errstr) {
                $errstr = $t->getMessage();
            }
        }

        $diagnostics = [
            'mailer' => (string) config('mail.default'),
            'host' => $host,
            'port' => $port,
            'encryption' => $encryption ?: 'none',
            'username_set' => $username !== '' ? 'yes' : 'no',
            'from' => $fromName . ' <' . $fromAddr . '>',
            'dns_resolved' => $dnsResolved ? 'yes' : 'no',
            'resolved_ip' => $dnsResolved ? $resolvedIp : 'unresolved',
            'tcp_connect_ok' => $socketOk ? 'yes' : 'no',
            'tcp_error' => $socketOk ? null : trim($errno . ' ' . $errstr),
            'time' => now()->toDateTimeString(),
        ];

        // SMTP preflight: HELO/MAIL FROM/RCPT TO without sending data
        $preflight = $this->smtpPreflight($host, $port, $fromAddr ?: 'mchan3@central.uh.edu', $validated['to'], 'central.uh.edu');

        try {
            Mail::raw($validated['message'], function ($message) use ($validated, $fromAddr, $fromName) {
                // Ensure From and Return-Path are set explicitly
                if ($fromAddr) {
                    $message->from($fromAddr, $fromName ?: null);
                    $message->returnPath($fromAddr);
                }
                $message->to($validated['to'])
                        ->subject($validated['subject']);
            });

            Log::info('Email test sent successfully', [
                'to' => $validated['to'],
                'diagnostics' => $diagnostics,
                'smtp_preflight' => $preflight,
            ]);

            return back()
                ->with('success', 'Test email queued/sent successfully to ' . $validated['to'])
                ->with('info', $this->formatDiagnostics($diagnostics, $preflight));
        } catch (\Throwable $e) {
            // Capture nested exception messages if any
            $messages = [];
            $ex = $e;
            while ($ex) {
                $messages[] = get_class($ex) . ': ' . $ex->getMessage();
                $ex = $ex->getPrevious();
            }

            Log::error('Email test failed', [
                'to' => $validated['to'],
                'exception_chain' => $messages,
                'diagnostics' => $diagnostics,
                'smtp_preflight' => $preflight,
            ]);

            return back()
                ->with('error', 'Failed to send test email. ' . ($messages[0] ?? 'Unknown error'))
                ->with('info', $this->formatDiagnostics($diagnostics, $preflight));
        }
    }

    /**
     * Format diagnostics array into a human-friendly string for display.
     */
    protected function formatDiagnostics(array $d, array $preflight = []): string
    {
        $lines = [
            'Diagnostics:',
            "- Mailer: {$d['mailer']}",
            "- Host: {$d['host']}",
            "- Port: {$d['port']}",
            "- Encryption: {$d['encryption']}",
            "- Username set: {$d['username_set']}",
            "- From: {$d['from']}",
            "- DNS resolved: {$d['dns_resolved']}" . ($d['dns_resolved'] === 'yes' ? " ({$d['resolved_ip']})" : ''),
            "- TCP connect: {$d['tcp_connect_ok']}" . ($d['tcp_connect_ok'] === 'no' && !empty($d['tcp_error']) ? " ({$d['tcp_error']})" : ''),
            "- Time: {$d['time']}",
        ];
        if (!empty($preflight)) {
            $lines[] = 'SMTP Preflight:';
            $lines[] = "  - Banner: " . ($preflight['banner'] ?? '');
            $lines[] = "  - HELO: " . ($preflight['helo'] ?? '');
            $lines[] = "  - MAIL FROM: " . ($preflight['mail_from'] ?? '');
            $lines[] = "  - RCPT TO: " . ($preflight['rcpt_to'] ?? '');
            if (!empty($preflight['errors'])) {
                $lines[] = '  - Errors: ' . implode(' | ', $preflight['errors']);
            }
        }
        return implode("\n", $lines);
    }

    /**
     * Perform a lightweight SMTP handshake to check relay acceptance without sending data.
     */
    protected function smtpPreflight(string $host, int $port, string $from, string $rcpt, string $heloDomain): array
    {
        $out = ['errors' => []];
        $fp = @fsockopen($host, $port, $errno, $errstr, 5.0);
        if (!$fp) {
            $out['errors'][] = trim($errno . ' ' . $errstr);
            return $out;
        }
        stream_set_timeout($fp, 5);
        $read = function() use ($fp) {
            $line = @fgets($fp, 512);
            return $line !== false ? trim($line) : '';
        };
        $write = function(string $cmd) use ($fp) {
            @fwrite($fp, $cmd . "\r\n");
        };
        $out['banner'] = $read();
        $write('HELO ' . $heloDomain);
        $out['helo'] = $read();
        $write('MAIL FROM:<' . $from . '>');
        $out['mail_from'] = $read();
        $write('RCPT TO:<' . $rcpt . '>');
        $out['rcpt_to'] = $read();
        $write('RSET');
        $read();
        $write('QUIT');
        fclose($fp);
        return $out;
    }
}
