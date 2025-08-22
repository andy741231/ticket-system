<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Base mailable for Ticket notifications to remove duplication.
 * Child classes should call buildMessage(...) with their specific copy.
 */
abstract class BaseTicketMailable extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Helper to build a standardized ticket email with shared HTML and plain templates.
     *
     * @param string $subject
     * @param string $heading
     * @param string $intro
     * @param array<string,string> $meta  // label => value
     * @param string $ticketUrl
     * @param string $buttonText
     * @param string $footer
     */
    protected function buildMessage(
        string $subject,
        string $heading,
        string $intro,
        array $meta,
        string $ticketUrl,
        string $buttonText = 'View Ticket',
        string $footer = 'This is an automated notification from The Hub.'
    ): static {
        return $this->subject($subject)
            ->view('emails.tickets.base')
            ->text('emails.tickets.base_plain')
            ->with([
                'heading' => $heading,
                'intro' => $intro,
                'meta' => $meta,
                'ticketUrl' => $ticketUrl,
                'buttonText' => $buttonText,
                'footer' => $footer,
            ]);
    }
}
