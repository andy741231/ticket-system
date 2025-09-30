<?php

namespace App\Mail;

use App\Models\Newsletter\Campaign;
use App\Models\Newsletter\Subscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewsletterMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Campaign $campaign,
        public Subscriber $subscriber
    ) {}

    public $mailer = 'campus_smtp';

    public function envelope(): Envelope
    {
        // Prefer campaign-provided values, then template overrides, then env/config defaults
        $fromAddress = $this->campaign->from_email
            ?: optional($this->campaign->template)->from_email
            ?: env('MAIL_NEWSLETTER_FROM_ADDRESS', config('mail.from.address'));
        $fromName = $this->campaign->from_name
            ?: optional($this->campaign->template)->from_name
            ?: env('MAIL_NEWSLETTER_FROM_NAME', config('mail.from.name', 'UHPH News'));

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            subject: $this->campaign->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.newsletter',
            with: [
                'htmlContent' => $this->addTrackingAndPersonalization($this->campaign->html_content),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }

    private function addTrackingAndPersonalization(string $htmlContent): string
    {
        // Build personalization values with sensible fallbacks
        $firstName = $this->subscriber->first_name ?: ($this->subscriber->name ?: '');
        $lastName = $this->subscriber->last_name ?: '';
        $fullName = $this->subscriber->full_name;
        // Get organization from the dedicated column
        $organization = $this->subscriber->organization ?: '';

        // Map token names to values (support both legacy subscriber_* and shorthand tokens)
        $tokenMap = [
            'subscriber_email' => $this->subscriber->email,
            'subscriber_name' => $fullName,
            'subscriber_first_name' => $firstName,
            'subscriber_last_name' => $lastName,
            'unsubscribe_url' => route('newsletter.public.unsubscribe', $this->subscriber->unsubscribe_token),
            'preferences_url' => route('newsletter.public.preferences', $this->subscriber->unsubscribe_token),
            'campaign_name' => $this->campaign->name,
            'view_in_browser_url' => route('newsletter.public.campaign.view', $this->campaign->id),

            // Shorthand/common aliases
            'email' => $this->subscriber->email,
            'name' => $fullName,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'full_name' => $fullName,
            // New preferred token name
            'organization' => $organization,
            // Backward-compat alias
            'company' => $organization,
            // Browser URL alias
            'browser_url' => route('newsletter.public.campaign.view', $this->campaign->id),
        ];

        // Add "View in browser" link at the top
        $viewInBrowserLink = '<div style="text-align: center; padding: 10px 0; font-size: 12px; color: #666; font-family: Arial, sans-serif;">' .
                            '<a href="' . htmlspecialchars($tokenMap['view_in_browser_url'], ENT_QUOTES, 'UTF-8') . '" style="color: #007bff; text-decoration: underline;">View in browser</a>' .
                            '</div>';
        $htmlContent = $viewInBrowserLink . $htmlContent;

        // FIRST: Replace tokens while they're still in regular {{ }} format
        foreach ($tokenMap as $token => $value) {
            $pattern = '/\{\{\s*' . preg_quote($token, '/') . '\s*\}\}/i';
            $htmlContent = preg_replace($pattern, $value, $htmlContent);
        }

        // SECOND: Also replace HTML-entity-escaped tokens (in case editors encoded braces)
        foreach ($tokenMap as $token => $value) {
            $patternEntity = '/&#123;&#123;\s*' . preg_quote($token, '/') . '\s*&#125;&#125;/i';
            $htmlContent = preg_replace($patternEntity, $value, $htmlContent);
        }

        // THEN: Escape any remaining Blade directives to prevent evaluation during view rendering
        $htmlContent = str_replace(['{!!', '{{'], ['&#123;&#123;!', '&#123;&#123;'], $htmlContent);

        // Auto-replace common footer patterns with functional links
        $htmlContent = $this->autoReplaceFooterLinks($htmlContent, $tokenMap);

        // Determine tracking preference (default to enabled when null)
        $trackingEnabled = $this->campaign->enable_tracking ?? true;

        if ($trackingEnabled) {
            // Add tracking pixel for open tracking
            $campaignId = (string)$this->campaign->id;
            $subscriberId = (string)$this->subscriber->id;
            $trackingPixel = '<img src="' . route('newsletter.public.track-open', [
                'campaign' => $campaignId,
                'subscriber' => $subscriberId,
                'token' => hash('sha256', $campaignId . $subscriberId . config('app.key'))
            ]) . '" width="1" height="1" style="display:none;" alt="" />';

            // Add tracking pixel before closing body tag (case-insensitive). If no </body>, append at end.
            if (stripos($htmlContent, '</body>') !== false) {
                $htmlContent = str_ireplace('</body>', $trackingPixel . '</body>', $htmlContent);
            } else {
                $htmlContent .= $trackingPixel;
            }

            // Add click tracking to all links
            $htmlContent = preg_replace_callback(
                '/<a\s+([^>]*href=["\']([^"\']+)["\'][^>]*)>/i',
                function ($matches) {
                    $originalUrl = $matches[2];
                    
                    // Skip if already a tracking URL or unsubscribe link (avoid re-wrapping)
                    if (
                        str_contains($originalUrl, '/newsletter/track-click/') ||
                        str_contains($originalUrl, '/newsletter/public/track-click/') ||
                        str_contains($originalUrl, '/newsletter/unsubscribe/') ||
                        str_contains($originalUrl, '/newsletter/public/unsubscribe/')
                    ) {
                        return $matches[0];
                    }

                    $trackingUrl = route('newsletter.public.track-click', [
                        'campaign' => $this->campaign->id,
                        'subscriber' => $this->subscriber->id,
                        'url' => base64_encode($originalUrl),
                        'token' => hash('sha256', $this->campaign->id . $this->subscriber->id . $originalUrl . config('app.key'))
                    ]);

                    return '<a ' . str_replace($originalUrl, $trackingUrl, $matches[1]) . '>';
                },
                $htmlContent
            );
        }


        return $htmlContent;
    }

    private function autoReplaceFooterLinks(string $htmlContent, array $tokenMap): string
    {
        // Ensure URLs are properly encoded
        $unsubscribeUrl = htmlspecialchars($tokenMap['unsubscribe_url'], ENT_QUOTES, 'UTF-8');
        $preferencesUrl = htmlspecialchars($tokenMap['preferences_url'], ENT_QUOTES, 'UTF-8');
        $viewInBrowserUrl = htmlspecialchars($tokenMap['view_in_browser_url'], ENT_QUOTES, 'UTF-8');
        
        // Pattern 1: "Unsubscribe | Update preferences | View in browser"
        $pattern1 = '/Unsubscribe\s*\|\s*Update preferences\s*\|\s*View in browser/i';
        $replacement1 = '<a href="' . $unsubscribeUrl . '" style="color: #007bff; text-decoration: underline;">Unsubscribe</a> | ' .
                       '<a href="' . $preferencesUrl . '" style="color: #007bff; text-decoration: underline;">Update preferences</a> | ' .
                       '<a href="' . $viewInBrowserUrl . '" style="color: #007bff; text-decoration: underline;">View in browser</a>';
        
        $htmlContent = preg_replace($pattern1, $replacement1, $htmlContent);

        // Pattern 1.5: Fix bracketed syntax like [URL]Unsubscribe to proper anchor
        $htmlContent = preg_replace('/\[(https?:\/\/[^\]]+|\/[\w\-\/]+)\]\s*Unsubscribe/i', '<a href="' . $unsubscribeUrl . '" style="color: #007bff; text-decoration: underline;">Unsubscribe</a>', $htmlContent);
        $htmlContent = preg_replace('/\[(https?:\/\/[^\]]+|\/[\w\-\/]+)\]\s*Update preferences/i', '<a href="' . $preferencesUrl . '" style="color: #007bff; text-decoration: underline;">Update preferences</a>', $htmlContent);
        $htmlContent = preg_replace('/\[(https?:\/\/[^\]]+|\/[\w\-\/]+)\]\s*View in browser/i', '<a href="' . $viewInBrowserUrl . '" style="color: #007bff; text-decoration: underline;">View in browser</a>', $htmlContent);

        // Pattern 2: Individual text replacements
        $patterns = [
            '/\bUnsubscribe\b(?![^<]*<\/a>)/i' => '<a href="' . $unsubscribeUrl . '" style="color: #007bff; text-decoration: underline;">Unsubscribe</a>',
            '/\bUpdate preferences\b(?![^<]*<\/a>)/i' => '<a href="' . $preferencesUrl . '" style="color: #007bff; text-decoration: underline;">Update preferences</a>',
            '/\bView in browser\b(?![^<]*<\/a>)/i' => '<a href="' . $viewInBrowserUrl . '" style="color: #007bff; text-decoration: underline;">View in browser</a>',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $htmlContent = preg_replace($pattern, $replacement, $htmlContent);
        }

        return $htmlContent;
    }

    /**
     * Remove newsletter click-tracking wrappers from links and restore original URLs.
     */
    private function stripTrackingLinks(string $html): string
    {
        return preg_replace_callback(
            '/href=("|\')([^"\']+)(\1)/i',
            function ($m) {
                $quote = $m[1];
                $url = $m[2] ?? '';
                if ($url === '' || !preg_match('#/newsletter/(public/)?track-click/#i', $url)) {
                    return $m[0];
                }
                // Try to parse and extract base64 segment: /track-click/{campaign}/{subscriber}/{base64}[/{token}]
                try {
                    $parsed = parse_url($url);
                    $path = $parsed['path'] ?? '';
                    $parts = array_values(array_filter(explode('/', $path)));
                    $idx = null;
                    foreach ($parts as $i => $p) {
                        if (strtolower($p) === 'track-click') { $idx = $i; break; }
                    }
                    $b64 = ($idx !== null && isset($parts[$idx + 3])) ? $parts[$idx + 3] : null;
                    if ($b64) {
                        // URL decode then base64 decode
                        $b64 = rawurldecode($b64);
                        // Normalize potential URL-safe base64
                        $b64 = strtr($b64, '-_', '+/');
                        // Fix padding
                        $pad = strlen($b64) % 4;
                        if ($pad) { $b64 .= str_repeat('=', 4 - $pad); }
                        $orig = base64_decode($b64, true);
                        if ($orig && preg_match('#^https?://#i', $orig)) {
                            return 'href=' . $quote . $orig . $quote;
                        }
                    }
                } catch (\Throwable $e) {
                    // fall through
                }
                // Could not restore, make it inert
                return 'href=' . $quote . '#' . $quote;
            },
            $html
        );
    }
}
