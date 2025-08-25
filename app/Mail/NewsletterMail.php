<?php

namespace App\Mail;

use App\Models\Newsletter\Campaign;
use App\Models\Newsletter\Subscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewsletterMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Campaign $campaign,
        public Subscriber $subscriber
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->campaign->subject,
        );
    }

    public function content(): Content
    {
        // Add tracking pixels and personalization
        $htmlContent = $this->addTrackingAndPersonalization($this->campaign->html_content);

        return new Content(
            html: $htmlContent,
        );
    }

    public function attachments(): array
    {
        return [];
    }

    private function addTrackingAndPersonalization(string $htmlContent): string
    {
        // Replace personalization tokens
        $personalizations = [
            '{{subscriber_email}}' => $this->subscriber->email,
            '{{subscriber_name}}' => $this->subscriber->full_name,
            '{{subscriber_first_name}}' => $this->subscriber->first_name ?: $this->subscriber->name,
            '{{subscriber_last_name}}' => $this->subscriber->last_name ?: '',
            '{{unsubscribe_url}}' => route('newsletter.public.unsubscribe', $this->subscriber->unsubscribe_token),
            '{{campaign_name}}' => $this->campaign->name,
        ];

        foreach ($personalizations as $token => $value) {
            $htmlContent = str_replace($token, $value, $htmlContent);
        }

        // Add tracking pixel for open tracking
        $trackingPixel = '<img src="' . route('newsletter.public.track-open', [
            'campaign' => $this->campaign->id,
            'subscriber' => $this->subscriber->id,
            'token' => hash('sha256', $this->campaign->id . $this->subscriber->id . config('app.key'))
        ]) . '" width="1" height="1" style="display:none;" alt="" />';

        // Add tracking pixel before closing body tag
        $htmlContent = str_replace('</body>', $trackingPixel . '</body>', $htmlContent);

        // Add click tracking to all links
        $htmlContent = preg_replace_callback(
            '/<a\s+([^>]*href=["\']([^"\']+)["\'][^>]*)>/i',
            function ($matches) {
                $originalUrl = $matches[2];
                
                // Skip if already a tracking URL or unsubscribe link
                if (strpos($originalUrl, route('newsletter.public.track-click', '')) === 0 ||
                    strpos($originalUrl, route('newsletter.public.unsubscribe', '')) === 0) {
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

        return $htmlContent;
    }
}
