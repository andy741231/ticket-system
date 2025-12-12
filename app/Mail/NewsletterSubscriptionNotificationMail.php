<?php

namespace App\Mail;

use App\Models\Newsletter\Subscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewsletterSubscriptionNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Subscriber $subscriber
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Newsletter Subscription: ' . $this->subscriber->email,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.newsletter-subscription-notification',
            with: [
                'subscriber' => $this->subscriber,
                'subscriberUrl' => route('newsletter.subscribers.show', $this->subscriber->id),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
