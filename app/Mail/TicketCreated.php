<?php

namespace App\Mail;

use App\Mail\BaseTicketMailable;

class TicketCreated extends BaseTicketMailable
{
    public int $ticketId;
    public string $title;
    public string $status;
    public string $submitterName;
    public string $ticketUrl;
    public string $ticketTags;

    /**
     * Create a new message instance.
     */
    public function __construct(
        int $ticketId,
        string $title,
        string $status,
        string $submitterName,
        string $ticketUrl,
        string $ticketTags = '',
    ) {
        $this->ticketId = $ticketId;
        $this->title = $title;
        $this->status = $status;
        $this->submitterName = $submitterName;
        $this->ticketUrl = $ticketUrl;
        $this->ticketTags = $ticketTags;
    }

    /**
     * Build the message.
     */
    public function build(): static
    {
        $subject = 'New Ticket #' . $this->ticketId . ': ' . $this->title;

        return $this->buildMessage(
            subject: $subject,
            heading: 'New Ticket #' . $this->ticketId,
            intro: 'A new ticket has been created in The Hub.',
            meta: [
                'Title' => $this->title,
                'Tags' => $this->ticketTags ?: 'None',
                'Submitted by' => $this->submitterName,
            ],
            ticketUrl: $this->ticketUrl,
            buttonText: 'View Ticket',
            footer: 'You are receiving this because you are an admin of the Tickets app.'
        );
    }
}
